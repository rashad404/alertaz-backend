<?php

namespace App\Services\Monitoring;

use App\Models\PersonalAlert;
use App\Models\AlertType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebsiteMonitor extends BaseMonitor
{
    /**
     * Check all active website alerts.
     */
    public function checkAlerts(): void
    {
        $alertType = AlertType::where('slug', 'website')->first();

        if (!$alertType) {
            Log::warning('Website alert type not found');
            return;
        }

        $alerts = PersonalAlert::active()
            ->where('alert_type_id', $alertType->id)
            ->needsChecking()
            ->get();

        Log::info("Checking {$alerts->count()} website alerts");

        foreach ($alerts as $alert) {
            $this->processAlert($alert);
        }
    }

    /**
     * Fetch current website status.
     */
    protected function fetchCurrentData(PersonalAlert $alert): ?array
    {
        $url = $alert->asset; // URL is stored in asset field

        if (!$url) {
            return null;
        }

        // Ensure URL has protocol
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }

        return $this->checkWebsite($url);
    }

    /**
     * Check website status and response time.
     */
    private function checkWebsite(string $url): ?array
    {
        try {
            $startTime = microtime(true);

            $response = Http::timeout(30)
                ->withOptions([
                    'verify' => false, // Allow self-signed certificates
                    'allow_redirects' => [
                        'max' => 5,
                        'track_redirects' => true,
                    ],
                ])
                ->get($url);

            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            $statusCode = $response->status();
            $isOnline = $statusCode >= 200 && $statusCode < 400;

            // Get redirect chain if any
            $redirects = [];
            $headers = $response->headers();
            if (isset($headers['X-Guzzle-Redirect-History'])) {
                $redirects = $headers['X-Guzzle-Redirect-History'];
            }

            return [
                'url' => $url,
                'status_code' => $statusCode,
                'response_time' => round($responseTime, 2),
                'is_online' => $isOnline ? 1 : 0,
                'is_up' => $isOnline ? 1 : 0,
                'is_down' => !$isOnline ? 1 : 0,
                'redirect_count' => count($redirects),
                'redirects' => $redirects,
                'content_length' => strlen($response->body()),
                'error' => null,
                'timestamp' => now()->toIso8601String(),
            ];

        } catch (\Illuminate\Http\Client\RequestException $e) {
            // HTTP error (4xx or 5xx)
            $statusCode = $e->response->status() ?? 0;

            return [
                'url' => $url,
                'status_code' => $statusCode,
                'response_time' => 0,
                'is_online' => 0,
                'is_up' => 0,
                'is_down' => 1,
                'redirect_count' => 0,
                'redirects' => [],
                'content_length' => 0,
                'error' => 'HTTP Error: ' . $statusCode,
                'timestamp' => now()->toIso8601String(),
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Connection error (timeout, DNS, etc.)
            return [
                'url' => $url,
                'status_code' => 0,
                'response_time' => 0,
                'is_online' => 0,
                'is_up' => 0,
                'is_down' => 1,
                'redirect_count' => 0,
                'redirects' => [],
                'content_length' => 0,
                'error' => 'Connection failed: ' . $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ];

        } catch (\Exception $e) {
            Log::error("Website check failed for {$url}: " . $e->getMessage());

            return [
                'url' => $url,
                'status_code' => 0,
                'response_time' => 0,
                'is_online' => 0,
                'is_up' => 0,
                'is_down' => 1,
                'redirect_count' => 0,
                'redirects' => [],
                'content_length' => 0,
                'error' => 'Check failed: ' . $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Format alert message specifically for websites.
     */
    protected function formatAlertMessage(PersonalAlert $alert, array $currentData): string
    {
        $url = $alert->asset;
        $condition = $alert->conditions;
        $field = $condition['field'];

        $message = "🌐 **Website Alert: {$alert->name}**\n\n";
        $message .= "🔗 **{$url}**\n\n";

        // Check what kind of alert it is
        if ($field === 'is_online' || $field === 'is_up' || $field === 'is_down') {
            $isOnline = $currentData['is_online'];

            if ($isOnline) {
                $message .= "✅ **Website is UP**\n\n";
                $message .= "📊 **Status Details:**\n";
                $message .= "• Status Code: {$currentData['status_code']}\n";
                $message .= "• Response Time: {$currentData['response_time']} ms\n";
            } else {
                $message .= "🔴 **Website is DOWN**\n\n";
                $message .= "⚠️ **Error Details:**\n";

                if ($currentData['status_code'] > 0) {
                    $message .= "• Status Code: {$currentData['status_code']}\n";
                }

                if ($currentData['error']) {
                    $message .= "• Error: {$currentData['error']}\n";
                }
            }
        } elseif ($field === 'response_time') {
            $message .= "⏱️ **Response Time Alert**\n\n";
            $message .= "• Condition: {$field} {$condition['operator']} {$condition['value']} ms\n";
            $message .= "• Current Response Time: {$currentData['response_time']} ms\n";
            $message .= "• Status Code: {$currentData['status_code']}\n";
        } elseif ($field === 'status_code') {
            $message .= "📊 **Status Code Alert**\n\n";
            $message .= "• Expected: {$field} {$condition['operator']} {$condition['value']}\n";
            $message .= "• Current Status Code: {$currentData['status_code']}\n";
            $message .= "• Response Time: {$currentData['response_time']} ms\n";
        }

        if ($currentData['redirect_count'] > 0) {
            $message .= "• Redirects: {$currentData['redirect_count']}\n";
        }

        $message .= "\n⏰ " . now()->format('Y-m-d H:i:s') . " (Asia/Baku)";

        return $message;
    }
}