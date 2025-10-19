<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AlertType;

class AlertTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $alertTypes = [
            [
                'slug' => 'crypto',
                'name' => [
                    'az' => 'Kriptovalyuta',
                    'en' => 'Cryptocurrency',
                    'ru' => 'Криптовалюта'
                ],
                'description' => [
                    'az' => 'Kriptovalyuta qiymət xəbərdarlıqları',
                    'en' => 'Cryptocurrency price alerts',
                    'ru' => 'Оповещения о ценах криптовалют'
                ],
                'icon' => 'bitcoin',
                'configuration_schema' => [
                    'exchange' => 'binance',
                    'update_interval' => 60
                ],
                'condition_fields' => [
                    'price' => 'Current Price',
                    'change_24h' => '24h Change %',
                    'volume' => '24h Volume'
                ],
                'data_source' => 'binance_api',
                'check_interval' => 300,
                'is_active' => true,
                'sort_order' => 1
            ],
            [
                'slug' => 'weather',
                'name' => [
                    'az' => 'Hava',
                    'en' => 'Weather',
                    'ru' => 'Погода'
                ],
                'description' => [
                    'az' => 'Hava xəbərdarlıqları',
                    'en' => 'Weather alerts',
                    'ru' => 'Погодные оповещения'
                ],
                'icon' => 'cloud',
                'configuration_schema' => [
                    'location' => 'Baku',
                    'units' => 'metric'
                ],
                'condition_fields' => [
                    'temperature' => 'Temperature',
                    'humidity' => 'Humidity %',
                    'wind_speed' => 'Wind Speed',
                    'rain_chance' => 'Rain Chance %'
                ],
                'data_source' => 'openweather_api',
                'check_interval' => 3600,
                'is_active' => true,
                'sort_order' => 2
            ],
            [
                'slug' => 'website',
                'name' => [
                    'az' => 'Vebsayt',
                    'en' => 'Website',
                    'ru' => 'Веб-сайт'
                ],
                'description' => [
                    'az' => 'Vebsayt monitorinq xəbərdarlıqları',
                    'en' => 'Website monitoring alerts',
                    'ru' => 'Мониторинг веб-сайтов'
                ],
                'icon' => 'globe',
                'configuration_schema' => [
                    'method' => 'GET',
                    'timeout' => 30
                ],
                'condition_fields' => [
                    'status_code' => 'HTTP Status Code',
                    'response_time' => 'Response Time (ms)',
                    'is_online' => 'Online Status'
                ],
                'data_source' => 'http_check',
                'check_interval' => 300,
                'is_active' => true,
                'sort_order' => 3
            ],
            [
                'slug' => 'stock',
                'name' => [
                    'az' => 'Səhm',
                    'en' => 'Stock',
                    'ru' => 'Акции'
                ],
                'description' => [
                    'az' => 'Səhm bazarı xəbərdarlıqları',
                    'en' => 'Stock market alerts',
                    'ru' => 'Биржевые оповещения'
                ],
                'icon' => 'chart',
                'configuration_schema' => [
                    'market' => 'US',
                    'update_interval' => 60
                ],
                'condition_fields' => [
                    'price' => 'Current Price',
                    'change_percent' => 'Change %',
                    'volume' => 'Volume'
                ],
                'data_source' => 'yahoo_finance_api',
                'check_interval' => 300,
                'is_active' => true,
                'sort_order' => 4
            ],
            [
                'slug' => 'currency',
                'name' => [
                    'az' => 'Valyuta',
                    'en' => 'Currency',
                    'ru' => 'Валюта'
                ],
                'description' => [
                    'az' => 'Valyuta məzənnəsi xəbərdarlıqları',
                    'en' => 'Currency exchange rate alerts',
                    'ru' => 'Оповещения о курсах валют'
                ],
                'icon' => 'dollar',
                'configuration_schema' => [
                    'base_currency' => 'AZN'
                ],
                'condition_fields' => [
                    'rate' => 'Exchange Rate',
                    'change_24h' => '24h Change'
                ],
                'data_source' => 'cbar_api',
                'check_interval' => 3600,
                'is_active' => true,
                'sort_order' => 5
            ]
        ];

        foreach ($alertTypes as $type) {
            AlertType::updateOrCreate(
                ['slug' => $type['slug']],
                $type
            );
        }
    }
}