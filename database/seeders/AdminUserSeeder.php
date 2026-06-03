<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * @return list<array{name: string, email: string}>
     */
    private function admins(): array
    {
        return [
            [
                'name' => env('ADMIN_USER_NAME', 'Intercepta Admin'),
                'email' => env('ADMIN_USER_EMAIL', 'dlespinosa365@gmail.com'),
            ],
            [
                'name' => 'Manuel Maier',
                'email' => 'mmaier@interceptauruguay.com.uy',
            ],
            [
                'name' => 'Florencia Parolin',
                'email' => 'contaduria@interceptauruguay.com.uy',
            ],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make(env('ADMIN_USER_PASSWORD', 'password'));

        foreach ($this->admins() as $adminData) {
            /** @var User $admin */
            $admin = User::query()->firstOrCreate(
                ['email' => $adminData['email']],
                [
                    'name' => $adminData['name'],
                    'password' => $password,
                ],
            );

            $admin->assignRole('Admin');
        }
    }
}
