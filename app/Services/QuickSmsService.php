<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class QuickSmsService
{
    private string $login;
    private string $password;
    private string $baseUrl;

    public function __construct()
    {
        $this->login = config('services.quicksms.login');
        $this->password = config('services.quicksms.password');
        $this->baseUrl = config('services.quicksms.base_url');
    }

    /**
     * Send SMS via QuickSMS API
     *
     * @param string $phone Phone number (994XXXXXXXXX format)
     * @param string $message Message text
     * @param string $sender Sender name (Alert.az, Sayt.az, Task.az, etc.)
     * @param bool $unicode Whether to use Unicode encoding
     * @return array Response with success status and transaction ID or error
     */
    public function sendSMS(string $phone, string $message, string $sender = 'Alert.az', bool $unicode = false): array
    {
        try {
            $key = $this->generateAuthKey($message, $phone, $sender);

            $response = Http::post($this->baseUrl . '/smssender', [
                'login' => $this->login,
                'key' => $key,
                'msisdn' => $phone,
                'text' => $message,
                'sender' => $sender,
                'scheduled' => 'NOW',
                'unicode' => $unicode,
            ]);

            $data = $response->json();

            // Log the response for debugging
            Log::info('QuickSMS response', [
                'status' => $response->status(),
                'data' => $data,
            ]);

            // Success: response has 'obj' and no errorCode (or errorCode is null/0)
            if ($response->successful() && isset($data['obj']) && !isset($data['errorCode'])) {
                return [
                    'success' => true,
                    'transaction_id' => (string) $data['obj'],
                    'message' => $data['successMessage'] ?? 'SMS sent successfully',
                ];
            }

            // Also check if errorCode exists and is 0 or null
            if ($response->successful() && isset($data['obj']) && isset($data['errorCode']) && ($data['errorCode'] === null || $data['errorCode'] === 0)) {
                return [
                    'success' => true,
                    'transaction_id' => (string) $data['obj'],
                    'message' => $data['successMessage'] ?? 'SMS sent successfully',
                ];
            }

            $errorMessage = $this->getErrorMessage($data['errorCode'] ?? -500);

            Log::error('QuickSMS send failed', [
                'phone' => $phone,
                'error_code' => $data['errorCode'] ?? null,
                'error_message' => $data['errorMessage'] ?? $errorMessage,
            ]);

            return [
                'success' => false,
                'error_code' => $data['errorCode'] ?? -500,
                'error_message' => $data['errorMessage'] ?? $errorMessage,
            ];

        } catch (Exception $e) {
            Log::error('QuickSMS exception', [
                'phone' => $phone,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_code' => -500,
                'error_message' => 'Failed to send SMS',
            ];
        }
    }

    /**
     * Check QuickSMS account balance
     *
     * @return array Response with balance or error
     */
    public function checkBalance(): array
    {
        try {
            $key = $this->generateBalanceKey();

            $response = Http::get($this->baseUrl . '/balance', [
                'login' => $this->login,
                'key' => $key,
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['obj'])) {
                return [
                    'success' => true,
                    'balance' => $data['obj'],
                ];
            }

            return [
                'success' => false,
                'error_message' => $data['errorMessage'] ?? 'Failed to check balance',
            ];

        } catch (Exception $e) {
            Log::error('QuickSMS balance check failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error_message' => 'Failed to check balance',
            ];
        }
    }

    /**
     * Get delivery report for a transaction
     *
     * @param string $transactionId Transaction ID from send SMS response
     * @return array Response with delivery status
     */
    public function getDeliveryReport(string $transactionId): array
    {
        try {
            $response = Http::get($this->baseUrl . '/report', [
                'login' => $this->login,
                'trans_id' => $transactionId,
            ]);

            $data = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status_code' => $data['obj'] ?? null,
                    'status_message' => $this->getStatusMessage($data['obj'] ?? 107),
                ];
            }

            return [
                'success' => false,
                'error_message' => $data['errorMessage'] ?? 'Failed to get delivery report',
            ];

        } catch (Exception $e) {
            Log::error('QuickSMS delivery report failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => 'Failed to get delivery report',
            ];
        }
    }

    /**
     * Generate authentication key for sending SMS
     * Formula: MD5(MD5(password) + LOGIN + MSG_BODY + MSISDN + SENDER)
     *
     * @param string $message Message text
     * @param string $phone Phone number
     * @param string $sender Sender name
     * @return string MD5 hash
     */
    private function generateAuthKey(string $message, string $phone, string $sender): string
    {
        $passwordHash = md5($this->password);
        return md5($passwordHash . $this->login . $message . $phone . $sender);
    }

    /**
     * Generate authentication key for balance check
     * Formula: MD5(MD5(password) + LOGIN)
     *
     * @return string MD5 hash
     */
    private function generateBalanceKey(): string
    {
        $passwordHash = md5($this->password);
        return md5($passwordHash . $this->login);
    }

    /**
     * Get human-readable error message based on error code
     *
     * @param int $errorCode Error code from QuickSMS API
     * @return string Error message
     */
    private function getErrorMessage(int $errorCode): string
    {
        $errors = [
            -100 => 'Invalid authentication key',
            -101 => 'Message text is too long',
            -102 => 'Invalid phone number format',
            -103 => 'Invalid sender name',
            -104 => 'Insufficient balance',
            -105 => 'Phone number is blacklisted',
            -106 => 'Invalid transaction ID',
            -107 => 'IP address not allowed',
            -108 => 'Invalid hash calculation',
            -109 => 'Host not found',
            -110 => 'Reporting limit exceeded',
            -500 => 'Internal server error',
        ];

        return $errors[$errorCode] ?? 'Unknown error occurred';
    }

    /**
     * Get human-readable status message based on status code
     *
     * @param int $statusCode Status code from QuickSMS API
     * @return string Status message
     */
    private function getStatusMessage(int $statusCode): string
    {
        $statuses = [
            100 => 'In queue',
            101 => 'Delivered',
            102 => 'Undelivered',
            103 => 'Expired',
            104 => 'Rejected',
            105 => 'Cancelled',
            106 => 'Error',
            107 => 'Unknown',
            108 => 'Sent',
            109 => 'Blacklisted',
        ];

        return $statuses[$statusCode] ?? 'Unknown status';
    }

    /**
     * Check if message requires Unicode encoding
     * (contains non-Latin characters)
     *
     * @param string $message Message text
     * @return bool True if Unicode is required
     */
    public function requiresUnicode(string $message): bool
    {
        return preg_match('/[^\x00-\x7F]/', $message) === 1;
    }

    /**
     * Error codes that are permanent (never retry)
     */
    private const PERMANENT_ERRORS = [
        -102, // Invalid phone number format
        -103, // Invalid sender name
        -105, // Phone number is blacklisted
    ];

    /**
     * Error codes that are temporary (retry with backoff)
     */
    private const TEMPORARY_ERRORS = [
        -500, // Internal server error
        -109, // Host not found
    ];

    /**
     * Error codes related to balance
     */
    private const BALANCE_ERRORS = [
        -104, // Insufficient balance
    ];

    /**
     * Error codes related to authentication
     */
    private const AUTH_ERRORS = [
        -100, // Invalid authentication key
        -107, // IP address not allowed
        -108, // Invalid hash calculation
    ];

    /**
     * Categorize an error code
     *
     * @param int $errorCode
     * @return string One of: 'permanent', 'temporary', 'balance', 'auth', 'unknown'
     */
    public function categorizeError(int $errorCode): string
    {
        if (in_array($errorCode, self::PERMANENT_ERRORS)) {
            return 'permanent';
        }

        if (in_array($errorCode, self::TEMPORARY_ERRORS)) {
            return 'temporary';
        }

        if (in_array($errorCode, self::BALANCE_ERRORS)) {
            return 'balance';
        }

        if (in_array($errorCode, self::AUTH_ERRORS)) {
            return 'auth';
        }

        // Unknown errors are treated as temporary (retry)
        return 'temporary';
    }

    /**
     * Check if error is permanent (should never retry)
     *
     * @param int $errorCode
     * @return bool
     */
    public function isPermanentError(int $errorCode): bool
    {
        return in_array($errorCode, self::PERMANENT_ERRORS);
    }

    /**
     * Check if error is temporary (should retry with backoff)
     *
     * @param int $errorCode
     * @return bool
     */
    public function isTemporaryError(int $errorCode): bool
    {
        return in_array($errorCode, self::TEMPORARY_ERRORS) || !$this->isPermanentError($errorCode);
    }

    /**
     * Check if error is related to balance
     *
     * @param int $errorCode
     * @return bool
     */
    public function isBalanceError(int $errorCode): bool
    {
        return in_array($errorCode, self::BALANCE_ERRORS);
    }

    /**
     * Check if error is related to authentication
     *
     * @param int $errorCode
     * @return bool
     */
    public function isAuthError(int $errorCode): bool
    {
        return in_array($errorCode, self::AUTH_ERRORS);
    }
}
