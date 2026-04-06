<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var User $admin */
        $admin = User::query()->firstOrCreate(
            ['email' => env('ADMIN_USER_EMAIL', 'admin@intercepta.test')],
            [
                'name' => env('ADMIN_USER_NAME', 'Intercepta Admin'),
                'password' => Hash::make(env('ADMIN_USER_PASSWORD', 'password')),
            ],
        );

        $admin->assignRole('Admin');
    }
}
