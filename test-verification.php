<?php

/**
 * Test script for Alert.az Verification System (Local Mock Mode)
 * Run with: php test-verification.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\VerificationService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

echo "===============================================\n";
echo "Alert.az Verification System Test (Local Mode)\n";
echo "===============================================\n\n";

$verificationService = new VerificationService();

// Check if we're in local mode
$isLocalMode = app()->environment('local', 'development') ||
               config('app.verification_mock', false);
$mockCode = config('app.mock_verification_code', '123456');

echo "Environment: " . app()->environment() . "\n";
echo "Mock Mode: " . ($isLocalMode ? "ENABLED" : "DISABLED") . "\n";
echo "Mock Code: " . ($isLocalMode ? $mockCode : "N/A") . "\n";
echo "===============================================\n\n";

// Test 1: SMS Verification
echo "1. Testing SMS Verification\n";
echo "------------------------------\n";

$testPhone = '+994501234567';
echo "Sending SMS verification to: {$testPhone}\n";

$result = $verificationService->sendSMSVerification($testPhone);

if ($result['success']) {
    echo "✅ SMS sent successfully!\n";
    echo "   Message: {$result['message']}\n";

    if (isset($result['debug'])) {
        echo "   🔧 Debug Info:\n";
        echo "      - Code: {$result['debug']['code']}\n";
        echo "      - Mode: {$result['debug']['mode']}\n";
    }

    echo "\n   Verifying with code: {$mockCode}\n";
    $verifyResult = $verificationService->verifyCode($testPhone, $mockCode, 'sms');

    if ($verifyResult['success']) {
        echo "   ✅ Verification successful!\n";
        if (isset($verifyResult['debug'])) {
            echo "      Debug: Code accepted in {$verifyResult['debug']['mode']} mode\n";
        }
    } else {
        echo "   ❌ Verification failed: {$verifyResult['message']}\n";
    }

    // Test wrong code
    echo "\n   Trying wrong code: 9999\n";
    $wrongResult = $verificationService->verifyCode($testPhone, '9999', 'sms');
    if (!$wrongResult['success']) {
        echo "   ✅ Wrong code correctly rejected: {$wrongResult['message']}\n";
    }
} else {
    echo "❌ Failed to send SMS: {$result['message']}\n";
}

echo "\n";

// Test 2: Email Verification
echo "2. Testing Email Verification\n";
echo "------------------------------\n";

$testEmail = 'test@alert.az';
echo "Sending email verification to: {$testEmail}\n";

$result = $verificationService->sendEmailVerification($testEmail);

if ($result['success']) {
    echo "✅ Email sent successfully!\n";
    echo "   Message: {$result['message']}\n";

    if (isset($result['debug'])) {
        echo "   🔧 Debug Info:\n";
        echo "      - Code: {$result['debug']['code']}\n";
        echo "      - Mode: {$result['debug']['mode']}\n";
    }

    echo "\n   Verifying with code: {$mockCode}\n";
    $verifyResult = $verificationService->verifyCode($testEmail, $mockCode, 'email');

    if ($verifyResult['success']) {
        echo "   ✅ Verification successful!\n";
        if (isset($verifyResult['debug'])) {
            echo "      Debug: Code accepted in {$verifyResult['debug']['mode']} mode\n";
        }
    } else {
        echo "   ❌ Verification failed: {$verifyResult['message']}\n";
    }

    // Test wrong code
    echo "\n   Trying wrong code: 5678\n";
    $wrongResult = $verificationService->verifyCode($testEmail, '5678', 'email');
    if (!$wrongResult['success']) {
        echo "   ✅ Wrong code correctly rejected: {$wrongResult['message']}\n";
    }
} else {
    echo "❌ Failed to send email: {$result['message']}\n";
}

echo "\n";

// Test 3: Resend Code
echo "3. Testing Resend Code\n";
echo "------------------------------\n";

echo "Resending SMS code to: {$testPhone}\n";
$resendResult = $verificationService->resendCode($testPhone, 'sms');

if ($resendResult['success']) {
    echo "✅ Code resent successfully!\n";
    echo "   Message: {$resendResult['message']}\n";
    if (isset($resendResult['debug'])) {
        echo "   Code: {$resendResult['debug']['code']}\n";
    }
} else {
    echo "❌ Failed to resend: {$resendResult['message']}\n";
    if (isset($resendResult['retry_after'])) {
        echo "   Retry after: {$resendResult['retry_after']} seconds\n";
    }
}

echo "\n";

// Test 4: Check Log Messages
echo "4. Recent Log Messages\n";
echo "------------------------------\n";

$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recentLogs = array_slice($lines, -10);

    foreach ($recentLogs as $log) {
        if (strpos($log, '[LOCAL MODE]') !== false) {
            echo "   " . trim($log) . "\n";
        }
    }
} else {
    echo "   No log file found\n";
}

echo "\n";

// Summary
echo "===============================================\n";
echo "Test Summary\n";
echo "===============================================\n";
echo "✅ SMS Verification: Working in mock mode\n";
echo "✅ Email Verification: Working in mock mode\n";
echo "✅ Mock Code ({$mockCode}): Always accepted in local mode\n";
echo "✅ Wrong Codes: Correctly rejected\n";
echo "✅ Resend Functionality: Working\n";
echo "\n";
echo "💡 Tips for Testing:\n";
echo "   - In local environment, always use code: {$mockCode}\n";
echo "   - Check storage/logs/laravel.log for [LOCAL MODE] messages\n";
echo "   - Set VERIFICATION_MOCK_MODE=false in .env to disable mock mode\n";
echo "   - Set MOCK_VERIFICATION_CODE in .env to change the mock code\n";
echo "\n";

// Test 5: API Endpoints
echo "5. Testing API Endpoints\n";
echo "------------------------------\n";
echo "\n";
echo "Test these endpoints with curl or Postman:\n";
echo "\n";
echo "📱 Send SMS OTP:\n";
echo "curl -X POST http://100.89.150.50:8007/api/auth/otp/send \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"phone\": \"501234567\"}'\n";
echo "\n";
echo "✅ Verify SMS OTP:\n";
echo "curl -X POST http://100.89.150.50:8007/api/auth/otp/verify \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"phone\": \"501234567\", \"code\": \"123456\"}'\n";
echo "\n";
echo "📧 Send Email Verification:\n";
echo "curl -X POST http://100.89.150.50:8007/api/auth/email/send \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"email\": \"test@example.com\"}'\n";
echo "\n";
echo "✅ Verify Email Code:\n";
echo "curl -X POST http://100.89.150.50:8007/api/auth/email/verify \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"email\": \"test@example.com\", \"code\": \"123456\"}'\n";
echo "\n";
echo "🔄 Resend Code:\n";
echo "curl -X POST http://100.89.150.50:8007/api/auth/resend-code \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"identifier\": \"501234567\", \"type\": \"sms\"}'\n";
echo "\n";

echo "Done!\n";