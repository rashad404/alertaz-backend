<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestClientSeeder extends Seeder
{
    public function run(): void
    {
        // Find or create a test user
        $user = User::firstOrCreate(
            ['email' => 'sayt.az@test.com'],
            [
                'name' => 'Sayt.az Test User',
                'password' => bcrypt('password'),
                'phone' => '994501234567',
                'balance' => 1000.00,
            ]
        );

        // Create test client (Sayt.az)
        $client = Client::firstOrCreate(
            ['name' => 'Sayt.az'],
            [
                'api_token' => Client::generateApiToken(),
                'user_id' => $user->id,
                'status' => 'active',
                'settings' => [
                    'description' => 'Hosting & Domain provider',
                ],
            ]
        );

        echo "\n";
        echo "✅ Test Client Created!\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Client Name:  {$client->name}\n";
        echo "Client ID:    {$client->id}\n";
        echo "User Email:   {$user->email}\n";
        echo "API Token:    {$client->api_token}\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "\nUse this token in API requests:\n";
        echo "Authorization: Bearer {$client->api_token}\n\n";
    }
}
