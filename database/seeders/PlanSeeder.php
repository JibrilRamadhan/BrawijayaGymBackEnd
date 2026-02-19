<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Trial 24 Jam',
                'type' => 'trial',
                'duration_unit' => 'hours',
                'duration_value' => 24,
                'price' => 0,
                'is_active' => true,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Visit Harian',
                'type' => 'harian',
                'duration_unit' => 'days',
                'duration_value' => 1,
                'price' => 35000,
                'is_active' => true,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Member Bulanan',
                'type' => 'member',
                'duration_unit' => 'months',
                'duration_value' => 1,
                'price' => 150000,
                'is_active' => true,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Member Tahunan',
                'type' => 'member',
                'duration_unit' => 'years',
                'duration_value' => 1,
                'price' => 1500000,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::create($plan);
        }
    }
}
