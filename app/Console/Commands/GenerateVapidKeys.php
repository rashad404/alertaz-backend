<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateVapidKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'push:generate-keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate VAPID keys for web push notifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating VAPID keys for push notifications...');

        try {
            // Check if the WebPush library is installed
            if (!class_exists(\Minishlink\WebPush\VAPID::class)) {
                $this->error('WebPush library not installed. Run: composer require minishlink/web-push');
                return Command::FAILURE;
            }

            // Generate VAPID keys
            $keys = \Minishlink\WebPush\VAPID::createVapidKeys();

            $this->info('VAPID keys generated successfully!');
            $this->newLine();

            // Display the keys
            $this->info('Add these keys to your .env file:');
            $this->newLine();

            $this->line('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
            $this->line('VAPID_PRIVATE_KEY=' . $keys['privateKey']);

            $this->newLine();
            $this->info('Frontend configuration:');
            $this->line('Add this public key to your frontend push subscription:');
            $this->comment($keys['publicKey']);

            $this->newLine();
            $this->warn('Important: Keep your private key secure and never expose it in client-side code!');

            // Optionally write to .env file
            if ($this->confirm('Do you want to add these keys to your .env file automatically?')) {
                $envFile = base_path('.env');

                if (file_exists($envFile)) {
                    $envContent = file_get_contents($envFile);

                    // Check if keys already exist
                    if (str_contains($envContent, 'VAPID_PUBLIC_KEY')) {
                        $this->warn('VAPID keys already exist in .env file. Please update them manually.');
                    } else {
                        // Append keys to .env file
                        $keysToAdd = "\n# Web Push VAPID Keys (Generated on " . now()->format('Y-m-d H:i:s') . ")\n";
                        $keysToAdd .= "VAPID_PUBLIC_KEY=" . $keys['publicKey'] . "\n";
                        $keysToAdd .= "VAPID_PRIVATE_KEY=" . $keys['privateKey'] . "\n";

                        file_put_contents($envFile, $envContent . $keysToAdd);

                        $this->info('Keys added to .env file successfully!');
                        $this->warn('Remember to clear config cache: php artisan config:clear');
                    }
                } else {
                    $this->error('.env file not found. Please create it first.');
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to generate VAPID keys: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}