<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use App\Models\ContactInfo;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    /**
     * Get contact information
     */
    public function info(Request $request, $locale = null): JsonResponse
    {
        if ($locale) {
            app()->setLocale($locale);
        }
        
        $lang = $locale ?? app()->getLocale();
        
        $contactInfo = ContactInfo::first();
        
        if (!$contactInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Contact information not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'company_name' => $contactInfo->company_name,
                'legal_name' => $contactInfo->legal_name,
                'voen' => $contactInfo->voen,
                'chief_editor' => $contactInfo->getTranslation('chief_editor', $lang),
                'domain_owner' => $contactInfo->domain_owner,
                'address' => $contactInfo->getTranslation('address', $lang),
                'phone' => $contactInfo->phone,
                'phone_2' => $contactInfo->phone_2,
                'email' => $contactInfo->email,
                'email_2' => $contactInfo->email_2,
                'working_hours' => $contactInfo->getTranslation('working_hours', $lang),
                'social_links' => $contactInfo->social_links,
                'coordinates' => [
                    'latitude' => $contactInfo->latitude,
                    'longitude' => $contactInfo->longitude,
                ],
                'map_embed_url' => $contactInfo->map_embed_url,
            ]
        ]);
    }

    /**
     * Submit contact form
     */
    public function send(Request $request, $locale = null): JsonResponse
    {
        if ($locale) {
            app()->setLocale($locale);
        }
        
        $lang = $locale ?? app()->getLocale();
        
        // Validation messages based on language
        $messages = [
            'az' => [
                'first_name.required' => 'Ad boş ola bilməz.',
                'last_name.required' => 'Soyad boş ola bilməz.',
                'phone.required' => 'Telefon boş ola bilməz.',
                'email.required' => 'E-mail boş ola bilməz.',
                'email.email' => 'E-mail düzgün formatda deyil.',
                'message.required' => 'Mesaj boş ola bilməz.',
                'terms.accepted' => 'Qaydalar və şərtlərlə razılaşmalısınız.',
            ],
            'en' => [
                'first_name.required' => 'First name is required.',
                'last_name.required' => 'Last name is required.',
                'phone.required' => 'Phone is required.',
                'email.required' => 'Email is required.',
                'email.email' => 'Email format is invalid.',
                'message.required' => 'Message is required.',
                'terms.accepted' => 'You must agree to the terms and conditions.',
            ],
            'ru' => [
                'first_name.required' => 'Имя обязательно.',
                'last_name.required' => 'Фамилия обязательна.',
                'phone.required' => 'Телефон обязателен.',
                'email.required' => 'Email обязателен.',
                'email.email' => 'Неверный формат email.',
                'message.required' => 'Сообщение обязательно.',
                'terms.accepted' => 'Вы должны согласиться с условиями.',
            ],
        ];
        
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|max:32',
            'email' => 'required|email|max:255',
            'message' => 'required|string',
            'terms' => 'accepted',
        ], $messages[$lang] ?? $messages['az']);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Save to database
        $submission = ContactSubmission::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'message' => $data['message'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Send email notification (optional)
        try {
            $contactInfo = ContactInfo::first();
            $adminEmail = $contactInfo ? $contactInfo->email : config('mail.from.address');
            
            if ($adminEmail) {
                Mail::raw(
                    "New contact form submission:\n\n" .
                    "Name: {$submission->full_name}\n" .
                    "Phone: {$submission->phone}\n" .
                    "Email: {$submission->email}\n" .
                    "Message: {$submission->message}\n\n" .
                    "Submitted at: {$submission->created_at}",
                    function ($message) use ($adminEmail, $submission) {
                        $message->to($adminEmail)
                                ->subject('New Contact Form Submission - ' . $submission->full_name);
                    }
                );
            }
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Log::error('Failed to send contact form email: ' . $e->getMessage());
        }

        // Return success message based on language
        $successMessages = [
            'az' => 'Müraciətiniz uğurla göndərildi! Tezliklə sizinlə əlaqə saxlanılacaq.',
            'en' => 'Your message has been sent successfully! We will contact you soon.',
            'ru' => 'Ваше сообщение успешно отправлено! Мы свяжемся с вами в ближайшее время.',
        ];

        return response()->json([
            'success' => true,
            'message' => $successMessages[$lang] ?? $successMessages['az']
        ], 200);
    }
}