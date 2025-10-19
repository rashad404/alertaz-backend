<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@kredit.az',
            'password' => Hash::make('admin123'),
            'email_verified_at' => now(),
            'is_admin' => true,
            'role' => 'admin',
        ]);

        // Create Admin
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'is_admin' => true,
            'role' => 'admin',
        ]);

        // Create Editor
        User::create([
            'name' => 'Editor User',
            'email' => 'editor@kredit.az',
            'password' => Hash::make('editor123'),
            'email_verified_at' => now(),
            'is_admin' => true,
            'role' => 'editor',
        ]);

        // Create Correspondent
        User::create([
            'name' => 'Correspondent User',
            'email' => 'correspondent@kredit.az',
            'password' => Hash::make('correspondent123'),
            'email_verified_at' => now(),
            'is_admin' => true,
            'role' => 'correspondent',
        ]);

        // Create Regular User
        User::create([
            'name' => 'Regular User',
            'email' => 'user@kredit.az',
            'password' => Hash::make('user123'),
            'email_verified_at' => now(),
            'is_admin' => false,
            'role' => 'user',
        ]);

        $this->command->info('Admin users seeded successfully!');
        $this->command->info('Super Admin: admin@kredit.az / admin123');
        $this->command->info('Admin: admin@example.com / password123');
        $this->command->info('Editor: editor@kredit.az / editor123');
        $this->command->info('Correspondent: correspondent@kredit.az / correspondent123');
        $this->command->info('Regular User: user@kredit.az / user123');
    }
}