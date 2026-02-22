<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
        ]);

        // Create Admin Role
        $adminRole = Role::firstOrCreate([
            'name' => 'admin'
        ], [
            'uuid' => (string) Str::uuid()
        ]);

        // Create Admin User
        $adminUser = User::firstOrCreate([
            'email' => 'admin@gym.com'
        ], [
            'uuid' => (string) Str::uuid(),
            'name' => 'Administrator',
            'username' => 'admin',
            'phone' => '0000',
            'password' => Hash::make('password123'),
            'is_guest' => false,
        ]);

        // Attach Role
        if (!$adminUser->roles()->where('name', 'admin')->exists()) {
            $adminUser->roles()->attach($adminRole->id);
        }
    }
}
