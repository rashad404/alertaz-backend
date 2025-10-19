<?php

/**
 * Test script for Alert.az Monitoring System
 * Run with: php test-monitoring.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\AlertType;
use App\Models\PersonalAlert;
use App\Services\Monitoring\CryptoMonitor;
use App\Services\Monitoring\WebsiteMonitor;
use App\Services\Monitoring\WeatherMonitor;
use Illuminate\Support\Facades\Log;

echo "===============================================\n";
echo "Alert.az Monitoring System Test\n";
echo "===============================================\n\n";

// 1. Create or get test user
echo "1. Setting up test user...\n";
$user = User::firstOrCreate(
    ['email' => 'test@alert.az'],
    [
        'name' => 'Test User',
        'password' => bcrypt('password'),
        'phone' => '+994501234567',
        'telegram_chat_id' => '123456789',
    ]
);
echo "   ✓ Test user ready (ID: {$user->id})\n\n";

// 2. Get alert types
echo "2. Checking alert types...\n";
$alertTypes = AlertType::all();
foreach ($alertTypes as $type) {
    echo "   ✓ {$type->name} ({$type->slug})\n";
}
echo "\n";

// 3. Create sample alerts for testing
echo "3. Creating sample alerts...\n";

// Crypto Alert
$cryptoType = AlertType::where('slug', 'crypto')->first();
if ($cryptoType) {
    $cryptoAlert = PersonalAlert::firstOrCreate(
        [
            'user_id' => $user->id,
            'alert_type_id' => $cryptoType->id,
            'asset' => 'BTC',
        ],
        [
            'name' => 'Bitcoin Price Alert',
            'conditions' => [
                'field' => 'price',
                'operator' => '>',
                'value' => 50000,
            ],
            'notification_channels' => ['email'],
            'check_frequency' => 300, // 5 minutes
            'is_recurring' => true,
            'is_active' => true,
        ]
    );
    echo "   ✓ Created crypto alert: Monitor BTC when price > $50,000\n";
}

// Website Alert
$websiteType = AlertType::where('slug', 'website')->first();
if ($websiteType) {
    $websiteAlert = PersonalAlert::firstOrCreate(
        [
            'user_id' => $user->id,
            'alert_type_id' => $websiteType->id,
            'asset' => 'https://google.com',
        ],
        [
            'name' => 'Google Uptime Monitor',
            'conditions' => [
                'field' => 'is_down',
                'operator' => '=',
                'value' => 1,
            ],
            'notification_channels' => ['email', 'sms'],
            'check_frequency' => 120, // 2 minutes
            'is_recurring' => true,
            'is_active' => true,
        ]
    );
    echo "   ✓ Created website alert: Monitor google.com uptime\n";
}

// Weather Alert
$weatherType = AlertType::where('slug', 'weather')->first();
if ($weatherType) {
    $weatherAlert = PersonalAlert::firstOrCreate(
        [
            'user_id' => $user->id,
            'alert_type_id' => $weatherType->id,
            'asset' => 'Baku, Azerbaijan',
        ],
        [
            'name' => 'Baku Temperature Alert',
            'conditions' => [
                'field' => 'temperature',
                'operator' => '>',
                'value' => 30,
            ],
            'notification_channels' => ['email', 'telegram'],
            'check_frequency' => 600, // 10 minutes
            'is_recurring' => true,
            'is_active' => true,
        ]
    );
    echo "   ✓ Created weather alert: Notify when Baku temp > 30°C\n";
}

echo "\n";

// 4. Test monitoring services
echo "4. Testing monitoring services...\n\n";

// Test Crypto Monitor
echo "   Testing Crypto Monitor:\n";
try {
    $cryptoMonitor = new CryptoMonitor();
    $reflection = new ReflectionClass($cryptoMonitor);
    $method = $reflection->getMethod('fetchCurrentData');
    $method->setAccessible(true);

    if (isset($cryptoAlert)) {
        $cryptoData = $method->invoke($cryptoMonitor, $cryptoAlert);
        if ($cryptoData) {
            echo "   ✓ BTC Price: $" . number_format($cryptoData['price'], 2) . "\n";
            echo "   ✓ 24h Change: " . number_format($cryptoData['change_24h'], 2) . "%\n";
            echo "   ✓ Source: " . $cryptoData['source'] . "\n";
        } else {
            echo "   ⚠ Failed to fetch crypto data\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test Website Monitor
echo "   Testing Website Monitor:\n";
try {
    $websiteMonitor = new WebsiteMonitor();
    $reflection = new ReflectionClass($websiteMonitor);
    $method = $reflection->getMethod('fetchCurrentData');
    $method->setAccessible(true);

    if (isset($websiteAlert)) {
        $websiteData = $method->invoke($websiteMonitor, $websiteAlert);
        if ($websiteData) {
            echo "   ✓ Google.com Status: " . ($websiteData['is_online'] ? 'ONLINE' : 'OFFLINE') . "\n";
            echo "   ✓ Response Time: " . $websiteData['response_time'] . " ms\n";
            echo "   ✓ Status Code: " . $websiteData['status_code'] . "\n";
        } else {
            echo "   ⚠ Failed to fetch website data\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test Weather Monitor
echo "   Testing Weather Monitor:\n";
try {
    $weatherMonitor = new WeatherMonitor();
    $reflection = new ReflectionClass($weatherMonitor);
    $method = $reflection->getMethod('fetchCurrentData');
    $method->setAccessible(true);

    if (isset($weatherAlert)) {
        $weatherData = $method->invoke($weatherMonitor, $weatherAlert);
        if ($weatherData) {
            echo "   ✓ Baku Temperature: " . $weatherData['temperature'] . "°C\n";
            echo "   ✓ Humidity: " . $weatherData['humidity'] . "%\n";
            echo "   ✓ Description: " . $weatherData['description'] . "\n";
        } else {
            echo "   ⚠ Failed to fetch weather data\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Test alert checking
echo "5. Running alert checks...\n";
echo "   Execute: php artisan alerts:check --sync\n";
echo "   Or for specific type: php artisan alerts:check --type=crypto --sync\n\n";

// 6. Show scheduler configuration
echo "6. Scheduler Configuration:\n";
echo "   The following schedules are configured:\n";
echo "   - All alerts: Every minute\n";
echo "   - Crypto: Every 30 seconds (9:00-17:00 weekdays)\n";
echo "   - Website: Every 2 minutes\n";
echo "   - Weather: Every 10 minutes\n";
echo "   - Stock: Every minute (9:30-16:00 weekdays)\n";
echo "   - Currency: Every 5 minutes\n\n";

echo "7. Queue Processing:\n";
echo "   Start queue worker: php artisan queue:work\n";
echo "   Or use: php artisan queue:listen\n\n";

echo "8. Running the scheduler:\n";
echo "   Local development: php artisan schedule:work\n";
echo "   Production (cron): * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1\n\n";

// Summary
echo "===============================================\n";
echo "Summary\n";
echo "===============================================\n";
echo "✓ Test user created: {$user->email}\n";
echo "✓ " . PersonalAlert::where('user_id', $user->id)->count() . " test alerts created\n";
echo "✓ Monitoring services tested\n";
echo "✓ Ready to process alerts\n\n";

echo "Next Steps:\n";
echo "1. Configure API keys in .env (see .env.monitoring)\n";
echo "2. Start queue worker: php artisan queue:work\n";
echo "3. Run scheduler: php artisan schedule:work\n";
echo "4. Test notifications: php artisan alerts:check --sync\n\n";

echo "Done!\n";