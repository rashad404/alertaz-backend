<?php

namespace App\Services\Monitoring;

use App\Models\PersonalAlert;
use App\Models\AlertHistory;
use App\Services\NotificationDispatcher;
use Illuminate\Support\Facades\Log;

abstract class BaseMonitor
{
    protected NotificationDispatcher $notificationDispatcher;

    public function __construct()
    {
        $this->notificationDispatcher = new NotificationDispatcher();
    }

    /**
     * Check all active alerts for this monitor type.
     */
    abstract public function checkAlerts(): void;

    /**
     * Fetch current data for the given asset/configuration.
     */
    abstract protected function fetchCurrentData(PersonalAlert $alert): ?array;

    /**
     * Process a single alert.
     */
    protected function processAlert(PersonalAlert $alert): void
    {
        try {
            // Skip if alert is not active
            if (!$alert->is_active) {
                return;
            }

            // Skip if not time to check yet
            if ($alert->last_checked_at &&
                $alert->last_checked_at->addSeconds($alert->check_frequency) > now()) {
                return;
            }

            // Update last checked time
            $alert->update(['last_checked_at' => now()]);

            // Fetch current data
            $currentData = $this->fetchCurrentData($alert);

            if (!$currentData) {
                Log::warning("Failed to fetch data for alert {$alert->id}");
                return;
            }

            // Check if conditions are met
            if ($alert->checkConditions($currentData)) {
                $this->triggerAlert($alert, $currentData);
            }

        } catch (\Exception $e) {
            Log::error("Error processing alert {$alert->id}: " . $e->getMessage());
        }
    }

    /**
     * Trigger the alert and send notifications.
     */
    protected function triggerAlert(PersonalAlert $alert, array $currentData): void
    {
        // Check if it's a one-time alert that has already been triggered
        if (!$alert->is_recurring && $alert->trigger_count > 0) {
            $alert->update(['is_active' => false]);
            return;
        }

        // Create alert history record
        $history = AlertHistory::create([
            'personal_alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'triggered_conditions' => $alert->conditions,
            'current_values' => $currentData,
            'notification_channels' => $alert->notification_channels,
            'delivery_status' => [],
            'triggered_at' => now(),
        ]);

        // Prepare the message
        $message = $this->formatAlertMessage($alert, $currentData);
        $history->update(['message' => $message]);

        // Send notifications through all channels
        $deliveryStatus = $this->notificationDispatcher->dispatch(
            $alert->user,
            $alert->notification_channels,
            $message,
            $alert,
            $currentData
        );

        // Update history with delivery status
        $history->update(['delivery_status' => $deliveryStatus]);

        // Update alert statistics
        $alert->update([
            'last_triggered_at' => now(),
            'trigger_count' => $alert->trigger_count + 1,
        ]);

        // Deactivate if it's a one-time alert
        if (!$alert->is_recurring) {
            $alert->update(['is_active' => false]);
        }

        Log::info("Alert {$alert->id} triggered for user {$alert->user_id}");
    }

    /**
     * Format the alert message for notifications.
     * Child classes MUST return a simple type key (e.g., "website_up", "crypto_target_reached")
     * that will be translated by the frontend based on user's language.
     *
     * DO NOT return formatted text with emojis or translations here.
     */
    abstract protected function formatAlertMessage(PersonalAlert $alert, array $currentData): string;

    /**
     * Parse API response safely.
     */
    protected function parseApiResponse($response): ?array
    {
        if (!$response) {
            return null;
        }

        try {
            if (is_string($response)) {
                return json_decode($response, true);
            }

            if (is_array($response)) {
                return $response;
            }

            if (is_object($response) && method_exists($response, 'json')) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Failed to parse API response: " . $e->getMessage());
            return null;
        }
    }
}