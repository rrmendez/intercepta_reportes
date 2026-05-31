<?php

namespace Database\Seeders;

use App\Models\BirdType;
use App\Models\BirdTypeAlias;
use App\Services\BirdTypes\BirdTypeTokenNormalizer;
use Illuminate\Database\Seeder;

class BirdTypeSeeder extends Seeder
{
    /**
     * @var list<array{
     *     slug: string,
     *     name: string,
     *     common_name: string,
     *     common_name_plural: string,
     *     scientific_name: string,
     *     aliases: list<string>,
     * }>
     */
    private const SPECIES = [
        [
            'slug' => 'palomas',
            'name' => 'Palomas',
            'common_name' => 'Paloma doméstica',
            'common_name_plural' => 'Palomas domésticas',
            'scientific_name' => 'Columba livia',
            'aliases' => ['Paloma', 'Paloma doméstica', 'Palomas domésticas'],
        ],
        [
            'slug' => 'cotorras',
            'name' => 'Cotorras',
            'common_name' => 'Cotorra',
            'common_name_plural' => 'Cotorras',
            'scientific_name' => 'Myiopsitta monachus',
            'aliases' => ['Cotorra'],
        ],
        [
            'slug' => 'tordos',
            'name' => 'Tordos',
            'common_name' => 'Tordo',
            'common_name_plural' => 'Tordos',
            'scientific_name' => 'Molothrus bonariensis',
            'aliases' => ['Tordo'],
        ],
    ];

    public function run(): void
    {
        $normalizer = app(BirdTypeTokenNormalizer::class);

        foreach (self::SPECIES as $species) {
            $birdType = BirdType::query()->updateOrCreate(
                ['slug' => $species['slug']],
                [
                    'name' => $species['name'],
                    'common_name' => $species['common_name'],
                    'common_name_plural' => $species['common_name_plural'],
                    'scientific_name' => $species['scientific_name'],
                    'active' => true,
                ],
            );

            $this->syncAliases($birdType, $species['aliases'], $normalizer);
        }
    }

    /**
     * @param  list<string>  $aliases
     */
    private function syncAliases(BirdType $birdType, array $aliases, BirdTypeTokenNormalizer $normalizer): void
    {
        $tokens = [];

        foreach ($aliases as $alias) {
            $token = $normalizer->normalize($alias);

            if ($token === '') {
                continue;
            }

            $tokens[] = $token;

            BirdTypeAlias::query()->updateOrCreate(
                ['token' => $token],
                [
                    'bird_type_id' => $birdType->id,
                    'alias' => $alias,
                ],
            );
        }

        if ($tokens !== []) {
            BirdTypeAlias::query()
                ->where('bird_type_id', $birdType->id)
                ->whereNotIn('token', $tokens)
                ->delete();
        }
    }
}
