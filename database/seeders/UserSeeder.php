<?php

namespace Database\Seeders;

use App\Models\Contractor;
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
        $masterContractorIds = Contractor::query()
            ->pluck('id')
            ->values()
            ->all();

        $adminContractorIds = Contractor::query()
            ->pluck('id')
            ->values()
            ->all();

        $masterUser = User::updateOrCreate(
            ['email' => env('VESHOP_MASTER_EMAIL', 'master@veshop.local')],
            [
                'name' => env('VESHOP_MASTER_NAME', 'Veshop Master'),
                'password' => Hash::make(env('VESHOP_MASTER_PASSWORD', '@veshop_2026')),
                'role' => User::ROLE_MASTER,
                'email_verified_at' => now(),
                'is_active' => true,
                'password_changed_at' => now(),
            ]
        );

        if ($masterContractorIds !== []) {
            $masterUser->contractors()->sync($masterContractorIds);
        }

        $adminUser = User::updateOrCreate(
            ['email' => 'evertonjunior1015@hotmail.com'],
            [
                'name' => 'Everton Martins',
                'password' => Hash::make(env('VESHOP_ADMIN_PASSWORD', '@veshop_2026')),
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
                'is_active' => true,
                'password_changed_at' => now(),
            ]
        );

        if ($adminContractorIds !== []) {
            $adminUser->contractors()->sync($adminContractorIds);
        }
    }
}
