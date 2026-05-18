<?php

namespace Database\Seeders;

use App\Models\BirdType;
use Illuminate\Database\Seeder;

class BirdTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Palomas', 'Cotorras', 'Tordos'] as $name) {
            BirdType::query()->firstOrCreate(
                ['name' => $name],
                [
                    'description' => null,
                    'active' => true,
                ],
            );
        }
    }
}
