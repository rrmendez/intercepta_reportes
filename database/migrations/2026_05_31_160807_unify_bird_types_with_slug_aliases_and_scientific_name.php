<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * @var array<string, array{slug: string, common_name: string, common_name_plural: string, scientific_name: string, aliases: list<string>}>
     */
    private const CANONICAL_SPECIES = [
        'Palomas' => [
            'slug' => 'palomas',
            'common_name' => 'Paloma doméstica',
            'common_name_plural' => 'Palomas domésticas',
            'scientific_name' => 'Columba livia',
            'aliases' => ['Paloma', 'Paloma doméstica', 'Palomas domésticas'],
        ],
        'Cotorras' => [
            'slug' => 'cotorras',
            'common_name' => 'Cotorra',
            'common_name_plural' => 'Cotorras',
            'scientific_name' => 'Myiopsitta monachus',
            'aliases' => ['Cotorra'],
        ],
        'Tordos' => [
            'slug' => 'tordos',
            'common_name' => 'Tordo',
            'common_name_plural' => 'Tordos',
            'scientific_name' => 'Molothrus bonariensis',
            'aliases' => ['Tordo'],
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const DUPLICATE_TO_SLUG = [
        'Paloma doméstica' => 'palomas',
        'Cotorra' => 'cotorras',
        'Tordo' => 'tordos',
    ];

    public function up(): void
    {
        Schema::table('bird_types', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('id');
            $table->string('common_name')->nullable()->after('name');
            $table->string('common_name_plural')->nullable()->after('common_name');
        });

        if (Schema::hasColumn('bird_types', 'description')) {
            Schema::table('bird_types', function (Blueprint $table): void {
                $table->renameColumn('description', 'scientific_name');
            });
        }

        Schema::create('bird_type_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bird_type_id')->constrained('bird_types')->cascadeOnDelete();
            $table->string('alias');
            $table->string('token')->unique();
            $table->timestamps();
        });

        $this->migrateBirdTypeData();

        Schema::table('bird_types', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->unique()->change();
            $table->string('common_name')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bird_type_aliases');

        Schema::table('bird_types', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'common_name', 'common_name_plural']);
        });

        if (Schema::hasColumn('bird_types', 'scientific_name')) {
            Schema::table('bird_types', function (Blueprint $table): void {
                $table->renameColumn('scientific_name', 'description');
            });
        }
    }

    private function migrateBirdTypeData(): void
    {
        /** @var array<string, int> $slugToId */
        $slugToId = [];

        foreach (DB::table('bird_types')->orderBy('id')->get() as $row) {
            $name = (string) $row->name;
            $canonical = self::CANONICAL_SPECIES[$name] ?? null;
            $duplicateSlug = self::DUPLICATE_TO_SLUG[$name] ?? null;

            if ($duplicateSlug !== null && isset($slugToId[$duplicateSlug])) {
                DB::table('visit_reports')
                    ->where('bird_type_id', $row->id)
                    ->update(['bird_type_id' => $slugToId[$duplicateSlug]]);

                $this->insertAlias($slugToId[$duplicateSlug], $name);

                DB::table('bird_types')->where('id', $row->id)->delete();

                continue;
            }

            if ($canonical !== null) {
                DB::table('bird_types')->where('id', $row->id)->update([
                    'slug' => $canonical['slug'],
                    'common_name' => $canonical['common_name'],
                    'common_name_plural' => $canonical['common_name_plural'],
                    'scientific_name' => $canonical['scientific_name'],
                ]);

                $slugToId[$canonical['slug']] = (int) $row->id;

                foreach ($canonical['aliases'] as $alias) {
                    $this->insertAlias((int) $row->id, $alias);
                }

                continue;
            }

            $slug = Str::slug($name);

            if ($slug === '') {
                $slug = 'bird-'.$row->id;
            }

            DB::table('bird_types')->where('id', $row->id)->update([
                'slug' => $slug,
                'common_name' => $name,
            ]);

            $slugToId[$slug] = (int) $row->id;
        }

        foreach (self::CANONICAL_SPECIES as $importName => $meta) {
            if (isset($slugToId[$meta['slug']])) {
                continue;
            }

            $id = DB::table('bird_types')->insertGetId([
                'slug' => $meta['slug'],
                'name' => $importName,
                'common_name' => $meta['common_name'],
                'common_name_plural' => $meta['common_name_plural'],
                'scientific_name' => $meta['scientific_name'],
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $slugToId[$meta['slug']] = (int) $id;

            foreach ($meta['aliases'] as $alias) {
                $this->insertAlias((int) $id, $alias);
            }
        }
    }

    private function insertAlias(int $birdTypeId, string $alias): void
    {
        $token = $this->normalizeToken($alias);

        if ($token === '') {
            return;
        }

        if (DB::table('bird_type_aliases')->where('token', $token)->exists()) {
            return;
        }

        DB::table('bird_type_aliases')->insert([
            'bird_type_id' => $birdTypeId,
            'alias' => $alias,
            'token' => $token,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizeToken(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '');
    }
};
