<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TemplateController extends BaseController
{
    /**
     * List templates
     */
    public function index(Request $request): JsonResponse
    {
        $query = Template::forClient($this->getClientId($request));

        // Filter by channel
        if ($channel = $request->input('channel')) {
            if ($channel === 'sms') {
                $query->sms();
            } elseif ($channel === 'email') {
                $query->email();
            }
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $query->orderBy('name');

        $templates = $query->get()->map(fn($t) => $this->formatTemplate($t));

        return $this->success($templates);
    }

    /**
     * Create a template
     */
    public function store(Request $request): JsonResponse
    {
        $validator = $this->validateTemplate($request);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $template = Template::create([
            'client_id' => $this->getClientId($request),
            'name' => $request->input('name'),
            'channel' => $request->input('channel'),
            'message_template' => $request->input('message_template'),
            'email_subject' => $request->input('email_subject'),
            'email_body' => $request->input('email_body'),
            'is_default' => $request->boolean('is_default', false),
        ]);

        return $this->created($this->formatTemplate($template));
    }

    /**
     * Get a template
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $template = Template::forClient($this->getClientId($request))->find($id);

        if (!$template) {
            return $this->notFound('Template not found');
        }

        return $this->success($this->formatTemplate($template));
    }

    /**
     * Update a template
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $template = Template::forClient($this->getClientId($request))->find($id);

        if (!$template) {
            return $this->notFound('Template not found');
        }

        $validator = $this->validateTemplate($request, true);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $updateData = $request->only([
            'name',
            'channel',
            'message_template',
            'email_subject',
            'email_body',
            'is_default',
        ]);

        $template->update(array_filter($updateData, fn($v) => $v !== null));

        return $this->success($this->formatTemplate($template->fresh()));
    }

    /**
     * Delete a template
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $template = Template::forClient($this->getClientId($request))->find($id);

        if (!$template) {
            return $this->notFound('Template not found');
        }

        $template->delete();

        return $this->success(null, 'Template deleted');
    }

    /**
     * Validate template request
     */
    private function validateTemplate(Request $request, bool $isUpdate = false): \Illuminate\Validation\Validator
    {
        $rules = [
            'name' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'channel' => ($isUpdate ? 'sometimes|' : '') . 'required|in:sms,email,both',
            'message_template' => 'required_if:channel,sms,both|nullable|string|max:1000',
            'email_subject' => 'required_if:channel,email,both|nullable|string|max:255',
            'email_body' => 'required_if:channel,email,both|nullable|string',
            'is_default' => 'nullable|boolean',
        ];

        return Validator::make($request->all(), $rules);
    }

    /**
     * Format template for response
     */
    private function formatTemplate(Template $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'channel' => $template->channel,
            'message_template' => $template->message_template,
            'email_subject' => $template->email_subject,
            'email_body' => $template->email_body,
            'variables' => $template->variables,
            'is_default' => $template->is_default,
            'created_at' => $template->created_at->toIso8601String(),
            'updated_at' => $template->updated_at->toIso8601String(),
        ];
    }
}
