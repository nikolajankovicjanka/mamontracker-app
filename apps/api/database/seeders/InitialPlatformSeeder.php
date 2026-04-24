<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'mamont'],
            [
                'name' => 'Mamont',
                'is_active' => true,
                'plan' => 'pro',
                'settings' => [
                    'timezone' => 'Europe/Sarajevo',
                    'locale' => 'sr',
                ],
                'meta' => null,
            ]
        );

        TenantDomain::firstOrCreate(
            ['domain' => 'mamont.localhost'],
            [
                'tenant_id' => $tenant->id,
                'is_primary' => true,
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@mamontrucker.local'],
            [
                'tenant_id' => null,
                'name' => 'Platform Super Admin',
                'password' => Hash::make('password123'),
                'role' => 'platform_super_admin',
                'is_active' => true,
                'last_login_at' => null,
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@mamont.local'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Mamont Admin',
                'password' => Hash::make('password123'),
                'role' => 'tenant_admin',
                'is_active' => true,
                'last_login_at' => null,
            ]
        );

        User::firstOrCreate(
            ['email' => 'user@mamont.local'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Mamont User',
                'password' => Hash::make('password123'),
                'role' => 'tenant_user',
                'is_active' => true,
                'last_login_at' => null,
            ]
        );
    }
}
