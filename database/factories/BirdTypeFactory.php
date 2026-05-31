<?php

namespace Database\Factories;

use App\Models\BirdType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BirdType>
 */
class BirdTypeFactory extends Factory
{
    protected $model = BirdType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => str($name)->slug()->toString(),
            'name' => ucfirst($name),
            'common_name' => ucfirst($name),
            'common_name_plural' => ucfirst($name).'s',
            'scientific_name' => fake()->optional()->words(2, true),
            'active' => true,
        ];
    }

    public function palomas(): static
    {
        return $this->state(fn (): array => [
            'slug' => 'palomas',
            'name' => 'Palomas',
            'common_name' => 'Paloma doméstica',
            'common_name_plural' => 'Palomas domésticas',
            'scientific_name' => 'Columba livia',
        ]);
    }

    public function cotorras(): static
    {
        return $this->state(fn (): array => [
            'slug' => 'cotorras',
            'name' => 'Cotorras',
            'common_name' => 'Cotorra',
            'common_name_plural' => 'Cotorras',
            'scientific_name' => 'Myiopsitta monachus',
        ]);
    }

    public function tordos(): static
    {
        return $this->state(fn (): array => [
            'slug' => 'tordos',
            'name' => 'Tordos',
            'common_name' => 'Tordo',
            'common_name_plural' => 'Tordos',
            'scientific_name' => 'Molothrus bonariensis',
        ]);
    }
}
