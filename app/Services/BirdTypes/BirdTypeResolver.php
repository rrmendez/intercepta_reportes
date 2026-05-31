<?php

declare(strict_types=1);

namespace App\Services\BirdTypes;

use App\Models\BirdType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BirdTypeResolver
{
    private const string DEFAULT_SLUG = 'palomas';

    public function __construct(
        private readonly BirdTypeTokenNormalizer $tokenNormalizer,
    ) {}

    public function resolve(string $input): ?BirdType
    {
        $token = $this->tokenNormalizer->normalize($input);

        if ($token === '') {
            return null;
        }

        return $this->tokenIndex()[$token] ?? null;
    }

    public function resolveOrFail(string $input): BirdType
    {
        $birdType = $this->resolve($input);

        if ($birdType instanceof BirdType) {
            return $birdType;
        }

        $suggestions = $this->activeBirdTypes()
            ->map(fn (BirdType $type): string => $type->name)
            ->implode(', ');

        throw ValidationException::withMessages([
            'bird_type_name' => [
                'Tipo de ave desconocido: "'.$input.'". Tipos registrados: '.$suggestions.'.',
            ],
        ]);
    }

    /**
     * @return array<string, BirdType>
     */
    public function importLabelMap(): array
    {
        return $this->tokenIndex();
    }

    /**
     * @return array{0: BirdType, 1: string}|null
     */
    public function matchCompositePrefix(string $header): ?array
    {
        $trimmed = trim($header);

        if ($trimmed === '') {
            return null;
        }

        $labels = [];

        foreach ($this->activeBirdTypes() as $birdType) {
            foreach ($this->labelsForBirdType($birdType) as $label) {
                $labels[] = [$label, $birdType];
            }
        }

        usort(
            $labels,
            static fn (array $a, array $b): int => strlen($b[0]) <=> strlen($a[0]) ?: strcmp($b[0], $a[0]),
        );

        foreach ($labels as [$label, $birdType]) {
            if (strcasecmp($trimmed, $label) === 0) {
                return null;
            }

            $pattern = '/^'.preg_quote($label, '/').'\s+(.+)$/iu';

            if (preg_match($pattern, $trimmed, $matches) === 1 && trim($matches[1]) !== '') {
                return [$birdType, trim($matches[1])];
            }

            $birdSnake = Str::snake($label);

            if (str_starts_with($header, $birdSnake.'_') && strlen($header) > strlen($birdSnake) + 1) {
                return [$birdType, substr($header, strlen($birdSnake) + 1)];
            }
        }

        return null;
    }

    public function default(): BirdType
    {
        $default = $this->activeBirdTypes()->first(
            fn (BirdType $birdType): bool => $birdType->slug === self::DEFAULT_SLUG,
        );

        if ($default instanceof BirdType) {
            return $default;
        }

        $fallback = $this->activeBirdTypes()->first();

        if ($fallback instanceof BirdType) {
            return $fallback;
        }

        throw ValidationException::withMessages([
            'bird_type_name' => ['No hay tipos de ave activos configurados.'],
        ]);
    }

    /**
     * @return Collection<int, BirdType>
     */
    private function activeBirdTypes(): Collection
    {
        return BirdType::query()
            ->where('active', true)
            ->with('aliases')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, BirdType>
     */
    private function tokenIndex(): array
    {
        $map = [];

        foreach ($this->activeBirdTypes() as $birdType) {
            foreach ($this->labelsForBirdType($birdType) as $label) {
                $token = $this->tokenNormalizer->normalize($label);

                if ($token !== '') {
                    $map[$token] = $birdType;
                }
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function labelsForBirdType(BirdType $birdType): array
    {
        $labels = array_filter([
            $birdType->slug,
            $birdType->name,
            $birdType->common_name,
            $birdType->common_name_plural,
        ]);

        foreach ($birdType->aliases as $alias) {
            $labels[] = $alias->alias;
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $label): string => trim((string) $label),
            $labels,
        ))));
    }
}
