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
        ?string $subject = null
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
            'is_test' => $campaign->is_test ?? false,
            'provider_message_id' => $result['message_id'] ?? null,
            'error_message' => $result['error'] ?? null,
            'cost' => $result['cost'] ?? 0,
            'segments' => $channel === 'sms' ? $this->templateRenderer->calculateSMSSegments($content) : 1,
            'sent_at' => $result['success'] ? now() : null,
        ]);
    }
}
