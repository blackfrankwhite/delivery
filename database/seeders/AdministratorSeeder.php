<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdministratorSeeder extends Seeder
{
    public function run(): void
    {
        $production = app()->environment('production');
        $email = config('dispatch.admin_email') ?: ($production ? null : 'admin@example.com');
        $password = config('dispatch.admin_password') ?: ($production ? null : 'password123');
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $password || ($production && strlen($password) < 12)) {
            throw new \RuntimeException('Set ADMIN_EMAIL and an ADMIN_PASSWORD of at least 12 characters before seeding production.');
        }
        if ($production && in_array($password, ['password123', 'SaburtaloDemo2026!'], true)) {
            throw new \RuntimeException('Use a unique administrator password for production.');
        }
        User::firstOrCreate(['email' => $email], ['name' => 'Administrator', 'password' => Hash::make($password), 'role' => 'superadministrator', 'active' => true]);
    }
}
