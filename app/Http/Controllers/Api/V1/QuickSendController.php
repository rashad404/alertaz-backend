<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\Message;
use App\Services\MessageSender;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuickSendController extends BaseController
{
    protected MessageSender $messageSender;
    protected TemplateRenderer $templateRenderer;

    public function __construct(MessageSender $messageSender, TemplateRenderer $templateRenderer)
    {
        $this->messageSender = $messageSender;
        $this->templateRenderer = $templateRenderer;
    }

    /**
     * Send message to a single customer
     */
    public function sendToCustomer(Request $request, int $customerId): JsonResponse
    {
        $clientId = $this->getClientId($request);

        $customer = Customer::forClient($clientId)->find($customerId);
        if (!$customer) {
            return $this->notFound('Customer not found');
        }

        $validation = $this->validateSendRequest($request);
        if ($validation->fails()) {
            return $this->validationError($validation->errors()->toArray());
        }

        $result = $this->sendToTarget(
            $request,
            $customer->getTemplateVariables(),
            $customer->phone,
            $customer->email,
            $customerId,
            null
        );

        return $this->success($result);
    }

    /**
     * Send message to a single service
     */
    public function sendToService(Request $request, string $type, int $serviceId): JsonResponse
    {
        $clientId = $this->getClientId($request);

        $serviceType = ServiceType::forClient($clientId)->where('key', $type)->first();
        if (!$serviceType) {
            return $this->notFound("Service type '{$type}' not found");
        }

        $service = Service::forClient($clientId)
            ->where('service_type_id', $serviceType->id)
            ->with('customer')
            ->find($serviceId);

        if (!$service) {
            return $this->notFound('Service not found');
        }

        $validation = $this->validateSendRequest($request);
        if ($validation->fails()) {
            return $this->validationError($validation->errors()->toArray());
        }

        $variables = $service->getTemplateVariables();
        $phone = $service->customer?->phone;
        $email = $service->customer?->email;

        $result = $this->sendToTarget(
            $request,
            $variables,
            $phone,
            $email,
            $service->customer_id,
            $serviceId
        );

        return $this->success($result);
    }

    /**
     * Bulk send to multiple customers
     */
    public function bulkSendToCustomers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_ids' => 'required|array|max:1000',
            'customer_ids.*' => 'required|integer',
            'channel' => 'required|in:sms,email,both',
            'message' => 'required_if:channel,sms,both|nullable|string|max:1000',
            'email_subject' => 'required_if:channel,email,both|nullable|string|max:255',
            'email_body' => 'required_if:channel,email,both|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $clientId = $this->getClientId($request);
        $customerIds = $request->input('customer_ids');

        $customers = Customer::forClient($clientId)
            ->whereIn('id', $customerIds)
            ->get();

        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];

        foreach ($customers as $customer) {
            $result = $this->sendToTarget(
                $request,
                $customer->getTemplateVariables(),
                $customer->phone,
                $customer->email,
                $customer->id,
                null
            );

            if ($result['sms']['status'] === 'sent' || $result['email']['status'] === 'sent') {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'customer_id' => $customer->id,
                    'sms_error' => $result['sms']['error'] ?? null,
                    'email_error' => $result['email']['error'] ?? null,
                ];
            }
        }

        return $this->success($results);
    }

    /**
     * Bulk send to multiple services
     */
    public function bulkSendToServices(Request $request, string $type): JsonResponse
    {
        $clientId = $this->getClientId($request);

        $serviceType = ServiceType::forClient($clientId)->where('key', $type)->first();
        if (!$serviceType) {
            return $this->notFound("Service type '{$type}' not found");
        }

        $validator = Validator::make($request->all(), [
            'service_ids' => 'required|array|max:1000',
            'service_ids.*' => 'required|integer',
            'channel' => 'required|in:sms,email,both',
            'message' => 'required_if:channel,sms,both|nullable|string|max:1000',
            'email_subject' => 'required_if:channel,email,both|nullable|string|max:255',
            'email_body' => 'required_if:channel,email,both|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $serviceIds = $request->input('service_ids');

        $services = Service::forClient($clientId)
            ->where('service_type_id', $serviceType->id)
            ->whereIn('id', $serviceIds)
            ->with('customer')
            ->get();

        $results = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($services as $service) {
            if (!$service->customer) {
                $results['skipped']++;
                continue;
            }

            $result = $this->sendToTarget(
                $request,
                $service->getTemplateVariables(),
                $service->customer->phone,
                $service->customer->email,
                $service->customer_id,
                $service->id
            );

            if ($result['sms']['status'] === 'sent' || $result['email']['status'] === 'sent') {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'service_id' => $service->id,
                    'sms_error' => $result['sms']['error'] ?? null,
                    'email_error' => $result['email']['error'] ?? null,
                ];
            }
        }

        return $this->success($results);
    }

    /**
     * Preview message with variables rendered
     */
    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'nullable|string',
            'email_subject' => 'nullable|string',
            'email_body' => 'nullable|string',
            'variables' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $variables = $request->input('variables');

        $result = [
            'message' => $request->input('message')
                ? $this->templateRenderer->render($request->input('message'), $variables)
                : null,
            'email_subject' => $request->input('email_subject')
                ? $this->templateRenderer->render($request->input('email_subject'), $variables)
                : null,
            'email_body' => $request->input('email_body')
                ? $this->templateRenderer->render($request->input('email_body'), $variables)
                : null,
            'sms_segments' => $request->input('message')
                ? $this->templateRenderer->calculateSMSSegments(
                    $this->templateRenderer->render($request->input('message'), $variables)
                  )
                : 0,
        ];

        return $this->success($result);
    }

    /**
     * Validate send request
     */
    private function validateSendRequest(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'channel' => 'required|in:sms,email,both',
            'message' => 'required_if:channel,sms,both|nullable|string|max:1000',
            'email_subject' => 'required_if:channel,email,both|nullable|string|max:255',
            'email_body' => 'required_if:channel,email,both|nullable|string',
            'sender' => 'nullable|string|max:50',
            'email_sender' => 'nullable|email|max:255',
        ]);
    }

    /**
     * Send message to a target
     */
    private function sendToTarget(
        Request $request,
        array $variables,
        ?string $phone,
        ?string $email,
        ?int $customerId,
        ?int $serviceId
    ): array {
        $clientId = $this->getClientId($request);
        $client = $this->getClient($request);
        $channel = $request->input('channel');

        $result = [
            'sms' => ['status' => 'skipped'],
            'email' => ['status' => 'skipped'],
        ];

        // Send SMS
        if (in_array($channel, ['sms', 'both']) && $phone) {
            $messageText = $this->templateRenderer->render($request->input('message'), $variables);
            $sender = $request->input('sender', $client->getSetting('default_sms_sender', 'Alert.az'));

            try {
                $smsResult = $this->messageSender->sendSms($phone, $messageText, $sender);

                $message = Message::createSms([
                    'client_id' => $clientId,
                    'customer_id' => $customerId,
                    'service_id' => $serviceId,
                    'recipient' => $phone,
                    'content' => $messageText,
                    'sender' => $sender,
                    'status' => $smsResult['success'] ? Message::STATUS_SENT : Message::STATUS_FAILED,
                    'is_test' => $smsResult['test_mode'] ?? false,
                    'source' => 'api',
                    'provider_message_id' => $smsResult['message_id'] ?? null,
                    'error_message' => $smsResult['error'] ?? null,
                    'cost' => $smsResult['cost'] ?? 0,
                    'segments' => $this->templateRenderer->calculateSMSSegments($messageText),
                    'sent_at' => now(),
                ]);

                $result['sms'] = [
                    'status' => $smsResult['success'] ? 'sent' : 'failed',
                    'message_id' => $message->id,
                    'error' => $smsResult['error'] ?? null,
                ];
            } catch (\Exception $e) {
                $result['sms'] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Send Email
        if (in_array($channel, ['email', 'both']) && $email) {
            $subject = $this->templateRenderer->render($request->input('email_subject'), $variables);
            $body = $this->templateRenderer->render($request->input('email_body'), $variables);
            $emailSender = $request->input('email_sender', $client->getSetting('default_email_sender'));

            try {
                $emailResult = $this->messageSender->sendEmail($email, $subject, $body, $emailSender);

                $message = Message::createEmail([
                    'client_id' => $clientId,
                    'customer_id' => $customerId,
                    'service_id' => $serviceId,
                    'recipient' => $email,
                    'subject' => $subject,
                    'content' => $body,
                    'sender' => $emailSender,
                    'status' => $emailResult['success'] ? Message::STATUS_SENT : Message::STATUS_FAILED,
                    'is_test' => $emailResult['test_mode'] ?? false,
                    'source' => 'api',
                    'provider_message_id' => $emailResult['message_id'] ?? null,
                    'error_message' => $emailResult['error'] ?? null,
                    'cost' => $emailResult['cost'] ?? 0,
                    'sent_at' => now(),
                ]);

                $result['email'] = [
                    'status' => $emailResult['success'] ? 'sent' : 'failed',
                    'message_id' => $message->id,
                    'error' => $emailResult['error'] ?? null,
                ];
            } catch (\Exception $e) {
                $result['email'] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }
}
