<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\PersonalAlert;

interface NotificationChannel
{
    /**
     * Send a notification through this channel.
     *
     * @param User $user
     * @param string $message
     * @param PersonalAlert $alert
     * @param array $data
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function send(User $user, string $message, PersonalAlert $alert, array $data = []): array;

    /**
     * Send a test notification through this channel.
     *
     * @param User $user
     * @param string $message
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function sendTest(User $user, string $message): array;

    /**
     * Check if the channel is properly configured for the user.
     *
     * @param User $user
     * @return bool
     */
    public function isConfigured(User $user): bool;
}