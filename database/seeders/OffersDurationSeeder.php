<?php

namespace Database\Seeders;

use App\Models\OffersDuration;
use Illuminate\Database\Seeder;

class OffersDurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $durations = [
            ['title' => '3 ay', 'order' => 1, 'status' => true],
            ['title' => '6 ay', 'order' => 2, 'status' => true],
            ['title' => '9 ay', 'order' => 3, 'status' => true],
            ['title' => '12 ay', 'order' => 4, 'status' => true],
            ['title' => '18 ay', 'order' => 5, 'status' => true],
            ['title' => '24 ay', 'order' => 6, 'status' => true],
            ['title' => '36 ay', 'order' => 7, 'status' => true],
            ['title' => '48 ay', 'order' => 8, 'status' => true],
            ['title' => '60 ay', 'order' => 9, 'status' => true],
            ['title' => '72 ay', 'order' => 10, 'status' => true],
            ['title' => '84 ay', 'order' => 11, 'status' => true],
            ['title' => '96 ay', 'order' => 12, 'status' => true],
            ['title' => '108 ay', 'order' => 13, 'status' => true],
            ['title' => '120 ay', 'order' => 14, 'status' => true],
        ];

        foreach ($durations as $duration) {
            OffersDuration::create($duration);
        }
    }
}