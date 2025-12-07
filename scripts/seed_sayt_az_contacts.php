<?php
/**
 * Test script to seed Sayt.az project with sample contacts
 *
 * Usage: php scripts/seed_sayt_az_contacts.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\Contact;
use App\Models\ClientAttributeSchema;

// Find Sayt.az client
$client = Client::where('name', 'like', '%sayt%')->first();

if (!$client) {
    echo "❌ Sayt.az project not found!\n";
    exit(1);
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📦 Seeding Sayt.az Project\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Project: {$client->name} (ID: {$client->id})\n";
echo "API Token: {$client->api_token}\n\n";

// Step 1: Register Schema (attributes that contacts can have)
echo "📝 Step 1: Registering attribute schema...\n";

$attributes = [
    [
        'key' => 'first_name',
        'label' => 'Ad',
        'type' => 'string',
        'required' => false,
    ],
    [
        'key' => 'last_name',
        'label' => 'Soyad',
        'type' => 'string',
        'required' => false,
    ],
    [
        'key' => 'email',
        'label' => 'E-poçt',
        'type' => 'string',
        'required' => false,
    ],
    [
        'key' => 'age',
        'label' => 'Yaş',
        'type' => 'integer',
        'required' => false,
    ],
    [
        'key' => 'city',
        'label' => 'Şəhər',
        'type' => 'enum',
        'options' => ['Bakı', 'Gəncə', 'Sumqayıt', 'Mingəçevir', 'Şəki', 'Lənkəran'],
        'required' => false,
    ],
    [
        'key' => 'subscription_type',
        'label' => 'Abunəlik növü',
        'type' => 'enum',
        'options' => ['free', 'premium', 'enterprise'],
        'required' => false,
    ],
    [
        'key' => 'is_verified',
        'label' => 'Təsdiqlənib',
        'type' => 'boolean',
        'required' => false,
    ],
    [
        'key' => 'registration_date',
        'label' => 'Qeydiyyat tarixi',
        'type' => 'date',
        'required' => false,
    ],
    [
        'key' => 'total_orders',
        'label' => 'Ümumi sifarişlər',
        'type' => 'integer',
        'required' => false,
    ],
    [
        'key' => 'balance',
        'label' => 'Balans (AZN)',
        'type' => 'number',
        'required' => false,
    ],
    [
        'key' => 'tags',
        'label' => 'Teqlər',
        'type' => 'array',
        'item_type' => 'string',
        'required' => false,
    ],
];

// Clear existing schema
ClientAttributeSchema::where('client_id', $client->id)->delete();

foreach ($attributes as $attr) {
    ClientAttributeSchema::create([
        'client_id' => $client->id,
        'attribute_key' => $attr['key'],
        'attribute_type' => $attr['type'],
        'label' => $attr['label'],
        'options' => $attr['options'] ?? null,
        'item_type' => $attr['item_type'] ?? null,
        'required' => $attr['required'] ?? false,
    ]);
    echo "  ✓ {$attr['key']} ({$attr['type']})\n";
}

echo "\n📱 Step 2: Syncing sample contacts...\n";

// Step 2: Create sample contacts
$contacts = [
    [
        'phone' => '+994501234567',
        'first_name' => 'Əli',
        'last_name' => 'Məmmədov',
        'email' => 'ali@example.com',
        'age' => 28,
        'city' => 'Bakı',
        'subscription_type' => 'premium',
        'is_verified' => true,
        'registration_date' => '2024-01-15',
        'total_orders' => 12,
        'balance' => 150.50,
        'tags' => ['loyal', 'vip'],
    ],
    [
        'phone' => '+994502345678',
        'first_name' => 'Leyla',
        'last_name' => 'Həsənova',
        'email' => 'leyla@example.com',
        'age' => 35,
        'city' => 'Gəncə',
        'subscription_type' => 'enterprise',
        'is_verified' => true,
        'registration_date' => '2023-06-20',
        'total_orders' => 45,
        'balance' => 520.00,
        'tags' => ['enterprise', 'vip', 'early-adopter'],
    ],
    [
        'phone' => '+994503456789',
        'first_name' => 'Rəşad',
        'last_name' => 'Əliyev',
        'email' => 'rashad@example.com',
        'age' => 22,
        'city' => 'Bakı',
        'subscription_type' => 'free',
        'is_verified' => false,
        'registration_date' => '2024-11-01',
        'total_orders' => 2,
        'balance' => 0,
        'tags' => ['new'],
    ],
    [
        'phone' => '+994504567890',
        'first_name' => 'Günel',
        'last_name' => 'Quliyeva',
        'email' => 'gunel@example.com',
        'age' => 41,
        'city' => 'Sumqayıt',
        'subscription_type' => 'premium',
        'is_verified' => true,
        'registration_date' => '2023-03-10',
        'total_orders' => 28,
        'balance' => 89.99,
        'tags' => ['loyal'],
    ],
    [
        'phone' => '+994505678901',
        'first_name' => 'Elşən',
        'last_name' => 'Nəsirov',
        'email' => 'elshan@example.com',
        'age' => 19,
        'city' => 'Bakı',
        'subscription_type' => 'free',
        'is_verified' => true,
        'registration_date' => '2024-09-15',
        'total_orders' => 5,
        'balance' => 25.00,
        'tags' => ['student'],
    ],
    [
        'phone' => '+994506789012',
        'first_name' => 'Nigar',
        'last_name' => 'İsmayılova',
        'email' => 'nigar@example.com',
        'age' => 55,
        'city' => 'Şəki',
        'subscription_type' => 'premium',
        'is_verified' => true,
        'registration_date' => '2022-12-01',
        'total_orders' => 67,
        'balance' => 340.25,
        'tags' => ['loyal', 'vip', 'ambassador'],
    ],
    [
        'phone' => '+994507890123',
        'first_name' => 'Vüsal',
        'last_name' => 'Hüseynov',
        'email' => 'vusal@example.com',
        'age' => 33,
        'city' => 'Mingəçevir',
        'subscription_type' => 'enterprise',
        'is_verified' => true,
        'registration_date' => '2023-08-22',
        'total_orders' => 89,
        'balance' => 1250.00,
        'tags' => ['enterprise', 'bulk-buyer'],
    ],
    [
        'phone' => '+994508901234',
        'first_name' => 'Aynur',
        'last_name' => 'Babayeva',
        'email' => 'aynur@example.com',
        'age' => 27,
        'city' => 'Lənkəran',
        'subscription_type' => 'free',
        'is_verified' => false,
        'registration_date' => '2024-10-05',
        'total_orders' => 1,
        'balance' => 0,
        'tags' => ['new', 'trial'],
    ],
    [
        'phone' => '+994509012345',
        'first_name' => 'Tural',
        'last_name' => 'Əhmədov',
        'email' => 'tural@example.com',
        'age' => 45,
        'city' => 'Bakı',
        'subscription_type' => 'premium',
        'is_verified' => true,
        'registration_date' => '2021-05-18',
        'total_orders' => 156,
        'balance' => 780.50,
        'tags' => ['loyal', 'vip', 'early-adopter', 'ambassador'],
    ],
    [
        'phone' => '+994550123456',
        'first_name' => 'Səbinə',
        'last_name' => 'Kazımova',
        'email' => 'sabina@example.com',
        'age' => 31,
        'city' => 'Gəncə',
        'subscription_type' => 'premium',
        'is_verified' => true,
        'registration_date' => '2024-02-28',
        'total_orders' => 18,
        'balance' => 210.00,
        'tags' => ['active'],
    ],
];

// Clear existing contacts
Contact::where('client_id', $client->id)->delete();

foreach ($contacts as $contactData) {
    $phone = $contactData['phone'];
    unset($contactData['phone']);

    Contact::create([
        'client_id' => $client->id,
        'phone' => $phone,
        'attributes' => $contactData,
    ]);

    echo "  ✓ {$phone} - {$contactData['first_name']} {$contactData['last_name']}\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Seeding complete!\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n📊 Summary:\n";
echo "  • Attributes: " . count($attributes) . "\n";
echo "  • Contacts: " . count($contacts) . "\n";
echo "\n🔗 Test the segment builder at:\n";
echo "  http://100.89.150.50:3007/settings/sms/projects/{$client->id}/campaigns/create\n\n";
