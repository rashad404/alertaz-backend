<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Message;
use App\Models\Cooldown;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignExecutor
{
    protected MessageSender $messageSender;
    protected TemplateRenderer $templateRenderer;

    public function __construct(MessageSender $messageSender, TemplateRenderer $templateRenderer)
    {
        $this->messageSender = $messageSender;
        $this->templateRenderer = $templateRenderer;
    }

    /**
     * Execute a campaign
     */
    public function execute(Campaign $campaign): array
    {
        $campaign->markAsSending();

        try {
            if ($campaign->targetsServices()) {
                $result = $this->executeForServices($campaign);
            } else {
                $result = $this->executeForCustomers($campaign);
            }

            if ($campaign->isOneTime()) {
                $campaign->markAsCompleted();
            } else {
                $campaign->scheduleNextRun();
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Campaign execution failed', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);
            $campaign->markAsFailed();
            throw $e;
        }
    }

    /**
     * Execute for customers (1 message per customer)
     */
    protected function executeForCustomers(Campaign $campaign): array
    {
        $customers = Customer::forClient($campaign->client_id)
            ->applyFilter($campaign->filter)
            ->get();

        $campaign->update(['target_count' => $customers->count()]);

        $result = ['sent' => 0, 'failed' => 0];

        foreach ($customers as $customer) {
            // Check cooldown
            if (Cooldown::isInCooldown($campaign->id, 'customer', $customer->id, $campaign->cooldown_days)) {
                continue;
            }

            $variables = $customer->getTemplateVariables();
            $sendResult = $this->sendToTarget($campaign, $variables, $customer->phone, $customer->email, $customer->id, null);

            if ($sendResult['success']) {
                $result['sent']++;
                Cooldown::recordForCustomer($campaign->client_id, $campaign->id, $customer->id);
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Execute for services (1 message per service)
     */
    protected function executeForServices(Campaign $campaign): array
    {
        $services = Service::forClient($campaign->client_id)
            ->where('service_type_id', $campaign->service_type_id)
            ->applyFilter($campaign->filter)
            ->notInCooldown($campaign->id, $campaign->cooldown_days)
            ->with('customer')
            ->get();

        $campaign->update(['target_count' => $services->count()]);

        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($services as $service) {
            if (!$service->customer) {
                $result['skipped']++;
                continue;
            }

            $variables = $service->getTemplateVariables();
            $sendResult = $this->sendToTarget(
                $campaign,
                $variables,
                $service->customer->phone,
                $service->customer->email,
                $service->customer_id,
                $service->id
            );

            if ($sendResult['success']) {
                $result['sent']++;
                Cooldown::recordForService($campaign->client_id, $campaign->id, $service->id);
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Send message to a target
     */
    protected function sendToTarget(
        Campaign $campaign,
        array $variables,
        ?string $phone,
        ?string $email,
        ?int $customerId,
        ?int $serviceId
    ): array {
        $success = false;

        // Send SMS
        if ($campaign->requiresPhone() && $phone) {
            $message = $this->templateRenderer->render($campaign->message_template, $variables);
            $message = $this->templateRenderer->sanitizeForSMS($message);

            $smsResult = $this->messageSender->sendSms($phone, $message, $campaign->sender ?? 'Alert.az');

            $this->logMessage($campaign, 'sms', $phone, $message, $smsResult, $customerId, $serviceId);

            if ($smsResult['success']) {
                $campaign->incrementSentCount();
                $campaign->addCost($smsResult['cost'] ?? 0);
                $success = true;
            } else {
                $campaign->incrementFailedCount();
            }
        }

        // Send Email
        if ($campaign->requiresEmail() && $email) {
            $subject = $this->templateRenderer->render($campaign->email_subject ?? '', $variables);
            $body = $this->templateRenderer->render($campaign->email_body ?? '', $variables);

            $emailResult = $this->messageSender->sendEmail($email, $subject, $body, $campaign->email_sender);

            $this->logMessage($campaign, 'email', $email, $body, $emailResult, $customerId, $serviceId, $subject);

            if ($emailResult['success']) {
                $campaign->incrementEmailSentCount();
                $campaign->addEmailCost($emailResult['cost'] ?? 0);
                $success = true;
            } else {
                $campaign->incrementEmailFailedCount();
            }
        }

        return ['success' => $success];
    }

    /**
     * Log message
     */
    protected function logMessage(
        Campaign $campaign,
        string $channel,
        string $recipient,
        string $content,
        array $result,
        ?int $customerId,
        ?int $serviceId,
        ?string $subject = null,
        ?bool $isTest = null
    ): void {
        Message::create([
            'client_id' => $campaign->client_id,
            'campaign_id' => $campaign->id,
            'customer_id' => $customerId,
            'service_id' => $serviceId,
            'channel' => $channel,
            'recipient' => $recipient,
            'content' => $content,
            'subject' => $subject,
            'sender' => $channel === 'sms' ? $campaign->sender : $campaign->email_sender,
            'status' => $result['success'] ? Message::STATUS_SENT : Message::STATUS_FAILED,
            'is_test' => $isTest ?? ($campaign->is_test ?? false),
            'source' => 'campaign',
            'provider_message_id' => $result['message_id'] ?? null,
            'error_message' => $result['error'] ?? null,
            'cost' => $result['cost'] ?? 0,
            'segments' => $channel === 'sms' ? $this->templateRenderer->calculateSMSSegments($content) : 1,
            'sent_at' => $result['success'] ? now() : null,
        ]);
    }

    /**
     * Test-send to the first N real recipients matching the campaign's segment.
     * Uses the same recipient query, rendering and channels as a real run, but
     * marks messages is_test and does NOT touch campaign counters or cooldowns.
     */
    public function testSendToMatches(Campaign $campaign, int $count): array
    {
        $targets = $this->collectTargets($campaign, $count);

        if ($targets->isEmpty()) {
            return ['matched' => 0, 'results' => []];
        }

        $results = $targets->map(fn ($t) => $this->testDeliver(
            $campaign, $t['variables'], $t['phone'], $t['email'], $t['customer_id'], $t['service_id']
        ))->all();

        return ['matched' => $targets->count(), 'results' => $results];
    }

    /**
     * Test-send to a custom phone/email. Renders the template with a real sample
     * recipient's variables (so {{name}}, {{expiry_date}} etc. look realistic),
     * falling back to placeholders when the segment matches nobody yet.
     */
    public function testSendToCustom(Campaign $campaign, ?string $phone, ?string $email): array
    {
        $sample = $this->collectTargets($campaign, 1)->first();
        $variables = $sample['variables'] ?? $this->placeholderVariables($campaign);

        return $this->testDeliver(
            $campaign,
            $variables,
            $phone,
            $email,
            $sample['customer_id'] ?? null,
            $sample['service_id'] ?? null
        );
    }

    /**
     * Build the recipient list for a campaign as normalised targets.
     * Mirrors executeForServices()/executeForCustomers() recipient selection.
     */
    protected function collectTargets(Campaign $campaign, ?int $limit = null)
    {
        if ($campaign->targetsServices()) {
            $query = Service::forClient($campaign->client_id)
                ->where('service_type_id', $campaign->service_type_id)
                ->applyFilter($campaign->filter)
                ->has('customer')
                ->with('customer');

            if ($limit) {
                $query->limit($limit);
            }

            return $query->get()->map(fn (Service $s) => [
                'variables' => $s->getTemplateVariables(),
                'phone' => $s->customer->phone,
                'email' => $s->customer->email,
                'customer_id' => $s->customer_id,
                'service_id' => $s->id,
            ])->values();
        }

        $query = Customer::forClient($campaign->client_id)->applyFilter($campaign->filter);

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (Customer $c) => [
            'variables' => $c->getTemplateVariables(),
            'phone' => $c->phone,
            'email' => $c->email,
            'customer_id' => $c->id,
            'service_id' => null,
        ])->values();
    }

    /**
     * Render + send a single test message (channel-guarded), logged as is_test.
     */
    protected function testDeliver(
        Campaign $campaign,
        array $variables,
        ?string $phone,
        ?string $email,
        ?int $customerId,
        ?int $serviceId
    ): array {
        $out = ['sms' => null, 'email' => null];

        if ($campaign->requiresPhone() && $phone) {
            $message = $this->templateRenderer->sanitizeForSMS(
                $this->templateRenderer->render($campaign->message_template ?? '', $variables)
            );
            $res = $this->messageSender->sendSms($phone, $message, $campaign->sender ?? 'Alert.az');
            $this->logMessage($campaign, 'sms', $phone, $message, $res, $customerId, $serviceId, null, true);
            $out['sms'] = [
                'recipient' => $phone,
                'content' => $message,
                'status' => $res['success'] ? 'sent' : 'failed',
                'error' => $res['error'] ?? null,
            ];
        }

        if ($campaign->requiresEmail() && $email) {
            $subject = $this->templateRenderer->render($campaign->email_subject ?? '', $variables);
            $body = $this->templateRenderer->render($campaign->email_body ?? '', $variables);
            $res = $this->messageSender->sendEmail($email, $subject, $body, $campaign->email_sender);
            $this->logMessage($campaign, 'email', $email, $body, $res, $customerId, $serviceId, $subject, true);
            $out['email'] = [
                'recipient' => $email,
                'subject' => $subject,
                'content' => $body,
                'status' => $res['success'] ? 'sent' : 'failed',
                'error' => $res['error'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Sample variables used when a custom test has no matching real recipient yet.
     */
    protected function placeholderVariables(Campaign $campaign): array
    {
        if ($campaign->targetsServices()) {
            $expiry = now()->addDay()->format('d.m.Y');
            return [
                'service_name' => 'example.az',
                'name' => 'example.az',
                'expiry_at' => $expiry,
                'expiry_date' => $expiry,
                'days_until_expiry' => 1,
                'status' => 'active',
                'customer_name' => 'Test',
                'customer_email' => '',
                'customer_phone' => '',
            ];
        }

        return [
            'customer_name' => 'Test',
            'name' => 'Test',
            'customer_email' => '',
            'email' => '',
            'customer_phone' => '',
            'phone' => '',
        ];
    }
}
