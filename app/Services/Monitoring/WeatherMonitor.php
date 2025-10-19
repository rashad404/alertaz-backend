<?php

namespace App\Services\Monitoring;

use App\Models\PersonalAlert;
use App\Models\AlertType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WeatherMonitor extends BaseMonitor
{
    private const OPENWEATHER_API_URL = 'https://api.openweathermap.org/data/2.5';
    private const CACHE_TTL = 600; // Cache for 10 minutes

    /**
     * Check all active weather alerts.
     */
    public function checkAlerts(): void
    {
        $alertType = AlertType::where('slug', 'weather')->first();

        if (!$alertType) {
            Log::warning('Weather alert type not found');
            return;
        }

        $alerts = PersonalAlert::active()
            ->where('alert_type_id', $alertType->id)
            ->needsChecking()
            ->get();

        Log::info("Checking {$alerts->count()} weather alerts");

        foreach ($alerts as $alert) {
            $this->processAlert($alert);
        }
    }

    /**
     * Fetch current weather data for the alert.
     */
    protected function fetchCurrentData(PersonalAlert $alert): ?array
    {
        $location = $alert->asset; // Location is stored in asset field

        if (!$location) {
            return null;
        }

        // Try to get cached data first
        $cacheKey = "weather_" . md5($location);
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        $data = $this->fetchWeatherData($location);

        if ($data) {
            // Cache the data
            Cache::put($cacheKey, $data, self::CACHE_TTL);
        }

        return $data;
    }

    /**
     * Fetch weather data from OpenWeatherMap.
     */
    private function fetchWeatherData(string $location): ?array
    {
        try {
            $apiKey = config('services.openweather.api_key', env('OPENWEATHER_API_KEY'));

            if (!$apiKey) {
                Log::error('OpenWeatherMap API key not configured');
                return $this->getMockWeatherData($location);
            }

            $response = Http::timeout(10)->get(self::OPENWEATHER_API_URL . '/weather', [
                'q' => $location,
                'appid' => $apiKey,
                'units' => 'metric', // Use Celsius
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'location' => $location,
                    'temperature' => (float) $data['main']['temp'],
                    'feels_like' => (float) $data['main']['feels_like'],
                    'humidity' => (float) $data['main']['humidity'],
                    'pressure' => (float) $data['main']['pressure'],
                    'wind_speed' => (float) $data['wind']['speed'],
                    'wind_direction' => (float) ($data['wind']['deg'] ?? 0),
                    'clouds' => (float) $data['clouds']['all'],
                    'rain_1h' => (float) ($data['rain']['1h'] ?? 0),
                    'rain_3h' => (float) ($data['rain']['3h'] ?? 0),
                    'rain_chance' => $this->calculateRainChance($data),
                    'description' => $data['weather'][0]['description'] ?? '',
                    'icon' => $data['weather'][0]['icon'] ?? '',
                    'visibility' => (float) ($data['visibility'] ?? 10000) / 1000, // Convert to km
                    'timestamp' => now()->toIso8601String(),
                ];
            }
        } catch (\Exception $e) {
            Log::warning("OpenWeatherMap API error for {$location}: " . $e->getMessage());
            return $this->getMockWeatherData($location);
        }

        return null;
    }

    /**
     * Get mock weather data for development.
     */
    private function getMockWeatherData(string $location): array
    {
        // Generate random but realistic weather data
        $temp = rand(15, 35);
        $humidity = rand(30, 80);

        return [
            'location' => $location,
            'temperature' => $temp,
            'feels_like' => $temp + rand(-3, 3),
            'humidity' => $humidity,
            'pressure' => rand(1000, 1030),
            'wind_speed' => rand(0, 20),
            'wind_direction' => rand(0, 360),
            'clouds' => rand(0, 100),
            'rain_1h' => rand(0, 100) > 70 ? rand(1, 10) : 0,
            'rain_3h' => rand(0, 100) > 70 ? rand(1, 30) : 0,
            'rain_chance' => rand(0, 100),
            'description' => $this->getRandomDescription(),
            'icon' => '01d',
            'visibility' => rand(5, 10),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Calculate rain chance based on weather data.
     */
    private function calculateRainChance(array $data): float
    {
        $rainChance = 0;

        // Check if it's already raining
        if (isset($data['rain'])) {
            $rainChance = 100;
        } elseif (isset($data['clouds']['all'])) {
            // Estimate based on cloud coverage
            $clouds = $data['clouds']['all'];
            if ($clouds > 80) {
                $rainChance = 70;
            } elseif ($clouds > 60) {
                $rainChance = 40;
            } elseif ($clouds > 40) {
                $rainChance = 20;
            } else {
                $rainChance = 5;
            }
        }

        return $rainChance;
    }

    /**
     * Get random weather description for mock data.
     */
    private function getRandomDescription(): string
    {
        $descriptions = [
            'clear sky',
            'few clouds',
            'scattered clouds',
            'broken clouds',
            'light rain',
            'moderate rain',
            'overcast clouds',
        ];

        return $descriptions[array_rand($descriptions)];
    }

    /**
     * Format alert message specifically for weather.
     */
    protected function formatAlertMessage(PersonalAlert $alert, array $currentData): string
    {
        $location = $alert->asset;
        $condition = $alert->conditions;
        $field = $condition['field'];

        $message = "🌤️ **Weather Alert: {$alert->name}**\n\n";
        $message .= "📍 **{$location}**\n\n";
        $message .= "⚠️ **Alert Triggered:**\n";
        $message .= "• Condition: {$field} {$condition['operator']} {$condition['value']}\n";
        $message .= "• Current Value: " . $currentData[$field] . "\n\n";
        $message .= "🌡️ **Current Conditions:**\n";
        $message .= "• Temperature: {$currentData['temperature']}°C (feels like {$currentData['feels_like']}°C)\n";
        $message .= "• Humidity: {$currentData['humidity']}%\n";
        $message .= "• Wind: {$currentData['wind_speed']} m/s\n";

        if ($currentData['rain_1h'] > 0) {
            $message .= "• Rain (1h): {$currentData['rain_1h']} mm\n";
        }

        $message .= "• Description: {$currentData['description']}\n";
        $message .= "\n⏰ " . now()->format('Y-m-d H:i:s') . " (Asia/Baku)";

        return $message;
    }
}