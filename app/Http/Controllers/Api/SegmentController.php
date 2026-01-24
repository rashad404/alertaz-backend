<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientAttributeSchema;
use App\Services\SegmentQueryBuilder;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SegmentController extends Controller
{
    protected SegmentQueryBuilder $queryBuilder;
    protected TemplateRenderer $templateRenderer;

    public function __construct(SegmentQueryBuilder $queryBuilder, TemplateRenderer $templateRenderer)
    {
        $this->queryBuilder = $queryBuilder;
        $this->templateRenderer = $templateRenderer;
    }

    /**
     * Get available attributes for segment building
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAttributes(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $schemas = ClientAttributeSchema::where('client_id', $client->id)
            ->get()
            ->map(function ($schema) {
                return [
                    'key' => $schema->attribute_key,
                    'label' => $schema->label,
                    'type' => $schema->attribute_type,
                    'conditions' => $schema->getConditionsForType(),
                    'options' => $schema->options,
                    'item_type' => $schema->item_type,
                    'properties' => $schema->properties,
                    'required' => $schema->required,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'attributes' => $schemas,
            ],
        ], 200);
    }

    /**
     * Preview segment (count and sample contacts)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function preview(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $validator = Validator::make($request->all(), [
            'filter' => ['required', 'array'],
            'filter.logic' => ['nullable', 'in:AND,OR'],
            'filter.conditions' => ['required', 'array', 'min:1'],
            'filter.conditions.*.key' => ['required', 'string'],
            'filter.conditions.*.operator' => ['required', 'string'],
            'filter.conditions.*.value' => ['nullable'],
            'preview_limit' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $filter = $request->input('filter');
        $previewLimit = $request->input('preview_limit', 10);

        try {
            // Count total matches
            $totalCount = $this->queryBuilder->countMatches($client->id, $filter);

            // Get sample contacts (skip if preview_limit is 0)
            $sampleContacts = $previewLimit > 0
                ? $this->queryBuilder->getMatches($client->id, $filter, $previewLimit)
                : collect();

            $responseData = [
                'total_count' => $totalCount,
                'preview_count' => $sampleContacts->count(),
                'preview_contacts' => $sampleContacts->map(function ($contact) {
                    return [
                        'id' => $contact->id,
                        'phone' => $contact->phone,
                        'attributes' => $contact->attributes,
                        'created_at' => $contact->created_at->toIso8601String(),
                    ];
                }),
            ];

            // Add debug SQL for client_id 1 only
            if ($client->id === 1) {
                $responseData['debug_sql'] = $this->queryBuilder->getDebugSql($client->id, $filter);
                $responseData['debug_filter'] = $filter;
            }

            return response()->json([
                'status' => 'success',
                'data' => $responseData,
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Segment preview error', [
                'client_id' => $client->id,
                'filter' => $filter,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to preview segment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate segment filter
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validate(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $validator = Validator::make($request->all(), [
            'filter' => ['required', 'array'],
            'filter.logic' => ['nullable', 'in:AND,OR'],
            'filter.conditions' => ['required', 'array', 'min:1'],
            'filter.conditions.*.key' => ['required', 'string'],
            'filter.conditions.*.operator' => ['required', 'string'],
            'filter.conditions.*.value' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $filter = $request->input('filter');

        // Validate that all condition keys exist in schema
        $schemas = ClientAttributeSchema::where('client_id', $client->id)
            ->pluck('attribute_key')
            ->toArray();

        $errors = [];
        foreach ($filter['conditions'] as $index => $condition) {
            if (!in_array($condition['key'], $schemas)) {
                $errors[] = "Condition {$index}: attribute '{$condition['key']}' does not exist in schema";
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid filter conditions',
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Filter is valid',
        ], 200);
    }

    /**
     * Preview rendered messages for a segment filter with custom templates
     * Used by campaign edit page to preview messages before saving
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function previewMessages(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $validator = Validator::make($request->all(), [
            'filter' => ['required', 'array'],
            'filter.logic' => ['nullable', 'in:AND,OR'],
            'filter.conditions' => ['required', 'array', 'min:1'],
            'filter.conditions.*.key' => ['required', 'string'],
            'filter.conditions.*.operator' => ['required', 'string'],
            'filter.conditions.*.value' => ['nullable'],
            'channel' => ['required', 'in:sms,email,both'],
            'message_template' => ['nullable', 'string'],
            'email_subject_template' => ['nullable', 'string'],
            'email_body_template' => ['nullable', 'string'],
            'email_display_name' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $filter = $request->input('filter');
        $channel = $request->input('channel');
        $messageTemplate = $request->input('message_template', '');
        $emailSubjectTemplate = $request->input('email_subject_template', '');
        $emailBodyTemplate = $request->input('email_body_template', '');
        $emailDisplayName = $request->input('email_display_name', 'Alert.az');
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        try {
            // Get query for matching contacts
            $query = $this->queryBuilder->getMatchesQuery($client->id, $filter);

            // Initialize channel-specific totals
            $smsTotal = 0;
            $emailTotal = 0;

            // For "both" channel, include contacts with valid phone OR valid email
            if ($channel === 'both') {
                $allContacts = $query->get();
                $filteredContacts = $allContacts->filter(function ($contact) {
                    return $contact->canReceiveSms() || $contact->canReceiveEmail();
                });
                $totalCount = $filteredContacts->count();
                // Calculate channel-specific totals from ALL filtered contacts
                $smsTotal = $filteredContacts->filter(fn($c) => $c->canReceiveSms())->count();
                $emailTotal = $filteredContacts->filter(fn($c) => $c->canReceiveEmail())->count();
                // Paginate manually
                $contacts = $filteredContacts->slice(($page - 1) * $perPage, $perPage)->values();
            } elseif ($channel === 'email') {
                $allContacts = $query->get();
                $filteredContacts = $allContacts->filter(function ($contact) {
                    return $contact->canReceiveEmail();
                });
                $totalCount = $filteredContacts->count();
                $emailTotal = $totalCount;
                $contacts = $filteredContacts->slice(($page - 1) * $perPage, $perPage)->values();
            } else {
                // SMS only - no additional filtering needed
                $totalCount = $query->count();
                $smsTotal = $totalCount;
                $contacts = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
            }

            // Render messages for each contact
            $plannedContacts = $contacts->map(function ($contact) use ($filter, $channel, $messageTemplate, $emailSubjectTemplate, $emailBodyTemplate, $emailDisplayName) {
                $contactData = [
                    'contact_id' => $contact->id,
                    'phone' => $contact->phone,
                    'email' => $contact->getEmailForValidation(),
                    'can_receive_sms' => $contact->canReceiveSms(),
                    'can_receive_email' => $contact->canReceiveEmail(),
                    'message' => null,
                    'email_subject' => null,
                    'email_body' => null,
                    'email_body_html' => null,
                    'segments' => 0,
                    'attributes' => $contact->attributes,
                ];

                // Render SMS message if needed
                if (($channel === 'sms' || $channel === 'both') && $messageTemplate) {
                    $renderedMessage = $this->templateRenderer->render($messageTemplate, $contact, $filter);
                    $contactData['message'] = $this->templateRenderer->sanitizeForSMS($renderedMessage);
                    $contactData['segments'] = $this->templateRenderer->calculateSMSSegments($contactData['message']);
                }

                // Render email if needed
                if (($channel === 'email' || $channel === 'both') && $emailBodyTemplate) {
                    $subject = $emailSubjectTemplate
                        ? $this->templateRenderer->render($emailSubjectTemplate, $contact, $filter)
                        : '';
                    $bodyText = $this->templateRenderer->render($emailBodyTemplate, $contact, $filter);

                    $contactData['email_subject'] = $subject ?: null;
                    $contactData['email_body'] = $bodyText;
                    $contactData['email_body_html'] = $this->convertToHtmlEmail($bodyText, $subject, $emailDisplayName);
                }

                return $contactData;
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'contacts' => $plannedContacts,
                    'total' => $totalCount,
                    'sms_total' => $smsTotal,
                    'email_total' => $emailTotal,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => ceil($totalCount / $perPage),
                ],
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Segment preview messages error', [
                'client_id' => $client->id,
                'filter' => $filter,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to preview messages',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convert plain text email body to HTML with styled template
     * Mirrors CampaignExecutionEngine::convertToHtmlEmail for preview consistency
     *
     * @param string $body
     * @param string $subject
     * @param string|null $senderName
     * @return string
     */
    protected function convertToHtmlEmail(string $body, string $subject, ?string $senderName = null): string
    {
        // Convert newlines to <br> and escape HTML entities
        $htmlBody = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));

        $senderDisplay = $senderName ?? 'Alert.az';
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellspacing="0" cellpadding="0" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">
                    <tr>
                        <td style="background-color: #515BC3; padding: 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px;">{$senderDisplay}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; padding: 30px;">
                            <div style="color: #4B5563; font-size: 15px; line-height: 1.6;">{$htmlBody}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #F9FAFB; padding: 20px 30px; border-radius: 0 0 12px 12px; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #9CA3AF;">&copy; {$year} {$senderDisplay}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
