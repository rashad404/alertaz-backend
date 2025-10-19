<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriberController extends Controller
{
    /**
     * Subscribe a new email to newsletter
     */
    public function subscribe(Request $request, $lang = null)
    {
        // Set locale
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $this->getErrorMessage($lang, 'invalid_email'),
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->input('email');
        $ipAddress = $request->ip();

        // Check if email already exists
        $existingSubscriber = Subscriber::where('email', $email)->first();

        if ($existingSubscriber) {
            // If already subscribed and active
            if ($existingSubscriber->status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => $this->getErrorMessage($lang, 'already_subscribed')
                ], 409);
            }

            // If previously unsubscribed, reactivate
            $existingSubscriber->resubscribe();
            $existingSubscriber->language = $lang;
            $existingSubscriber->ip_address = $ipAddress;
            $existingSubscriber->save();

            return response()->json([
                'success' => true,
                'message' => $this->getSuccessMessage($lang)
            ], 200);
        }

        // Create new subscriber
        try {
            Subscriber::create([
                'email' => $email,
                'language' => $lang,
                'ip_address' => $ipAddress,
                'status' => 'active'
            ]);

            return response()->json([
                'success' => true,
                'message' => $this->getSuccessMessage($lang)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $this->getErrorMessage($lang, 'server_error')
            ], 500);
        }
    }

    /**
     * Unsubscribe using token
     */
    public function unsubscribe($token)
    {
        $subscriber = Subscriber::where('token', $token)->first();

        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid unsubscribe link'
            ], 404);
        }

        if ($subscriber->status === 'unsubscribed') {
            return response()->json([
                'success' => false,
                'message' => 'Already unsubscribed'
            ], 409);
        }

        $subscriber->unsubscribe();

        return response()->json([
            'success' => true,
            'message' => 'Successfully unsubscribed'
        ], 200);
    }

    /**
     * Get success message based on language
     */
    private function getSuccessMessage($lang)
    {
        $messages = [
            'az' => 'Uğurla abunə oldunuz! Yeniliklər üçün e-poçtunuzu yoxlayın.',
            'en' => 'Successfully subscribed! Check your email for updates.',
            'ru' => 'Успешно подписались! Проверьте свою почту для обновлений.'
        ];

        return $messages[$lang] ?? $messages['az'];
    }

    /**
     * Get error message based on language and type
     */
    private function getErrorMessage($lang, $type)
    {
        $messages = [
            'az' => [
                'invalid_email' => 'Zəhmət olmasa düzgün e-poçt ünvanı daxil edin.',
                'already_subscribed' => 'Bu e-poçt ünvanı artıq abunədir.',
                'server_error' => 'Xəta baş verdi. Zəhmət olmasa yenidən cəhd edin.'
            ],
            'en' => [
                'invalid_email' => 'Please enter a valid email address.',
                'already_subscribed' => 'This email is already subscribed.',
                'server_error' => 'An error occurred. Please try again.'
            ],
            'ru' => [
                'invalid_email' => 'Пожалуйста, введите правильный адрес электронной почты.',
                'already_subscribed' => 'Этот адрес уже подписан.',
                'server_error' => 'Произошла ошибка. Пожалуйста, попробуйте еще раз.'
            ]
        ];

        return $messages[$lang][$type] ?? $messages['az'][$type] ?? 'An error occurred';
    }
}