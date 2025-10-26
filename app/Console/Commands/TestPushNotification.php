<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\Notifications\PushChannel;

class TestPushNotification extends Command
{
    protected $signature = 'notification:test-push {user_id? : The user ID to send notification to}';
    protected $description = 'Send a test push notification to a user';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        if (!$userId) {
            // Get all users with push subscriptions
            $users = User::whereHas('pushSubscriptions')->get();
            
            if ($users->isEmpty()) {
                $this->error('No users with push subscriptions found!');
                return 1;
            }
            
            $this->info('Users with push subscriptions:');
            foreach ($users as $user) {
                $count = $user->pushSubscriptions()->count();
                $this->line("  [{$user->id}] {$user->name} ({$user->email}) - {$count} subscription(s)");
            }
            
            $userId = $this->ask('Enter user ID to send test notification');
        }
        
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return 1;
        }
        
        $subscriptionCount = $user->pushSubscriptions()->count();
        
        if ($subscriptionCount === 0) {
            $this->error("User {$user->email} has no push subscriptions!");
            $this->info("Please subscribe to push notifications in the browser first.");
            return 1;
        }
        
        $this->info("Sending test notification to {$user->name} ({$user->email})...");
        $this->info("User has {$subscriptionCount} active subscription(s)");
        
        $pushChannel = app(PushChannel::class);
        
        $result = $pushChannel->send(
            $user,
            'Test Notification 🔔',
            'This is a test push notification from Alert.az! If you can see this, push notifications are working perfectly.',
            [
                'url' => '/alerts',
                'action' => 'view_alerts',
                'timestamp' => now()->toIso8601String(),
            ]
        );
        
        if ($result['success']) {
            $this->info('✅ Push notification sent successfully!');
            if (isset($result['mocked']) && $result['mocked']) {
                $this->warn('⚠️  MOCK MODE: Notification was logged to database but not actually sent.');
            }
        } else {
            $this->error('❌ Failed to send push notification!');
            if (isset($result['error'])) {
                $this->error("Error: {$result['error']}");
            }
        }
        
        return 0;
    }
}
