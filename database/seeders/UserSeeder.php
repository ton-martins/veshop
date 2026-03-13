<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('VESHOP_MASTER_EMAIL', 'master@veshop.local')],
            [
                'name' => 'Everton Martins',
                'password' => Hash::make(env('VESHOP_MASTER_PASSWORD', '@veshop_2026')),
                'role' => User::ROLE_MASTER,
                'email_verified_at' => now(),
            ]
        );
    }
}
