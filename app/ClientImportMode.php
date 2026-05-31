<?php

namespace App;

enum ClientImportMode: string
{
    case SingleSectorSingleBird = 'single_sector_single_bird';
    case SingleSectorMultiBird = 'single_sector_multi_bird';
    case MultiSectorSingleBird = 'multi_sector_single_bird';
    case MultiSectorMultiBird = 'multi_sector_multi_bird';

    public function label(): string
    {
        return match ($this) {
            self::SingleSectorSingleBird => 'Single sector / single bird',
            self::SingleSectorMultiBird => 'Single sector / multiple birds',
            self::MultiSectorSingleBird => 'Multiple sectors / single bird',
            self::MultiSectorMultiBird => 'Multiple sectors / multiple birds',
        };
    }

    public function filamentLabel(): string
    {
        return match ($this) {
            self::SingleSectorSingleBird => 'Sector único, ave única',
            self::SingleSectorMultiBird => 'Sector único, múltiples aves',
            self::MultiSectorSingleBird => 'Múltiples sectores, ave única',
            self::MultiSectorMultiBird => 'Múltiples sectores, múltiples aves',
        };
    }

    public function usesSingleSector(): bool
    {
        return in_array($this, [
            self::SingleSectorSingleBird,
            self::SingleSectorMultiBird,
        ], true);
    }

    public function usesSingleBird(): bool
    {
        return in_array($this, [
            self::SingleSectorSingleBird,
            self::MultiSectorSingleBird,
        ], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $mode): array => [$mode->value => $mode->label()])
            ->all();
    }

    public static function fromNullable(mixed $value): ?self
    {
        if (! is_string($value)) {
            return null;
        }

        return self::tryFrom($value);
    }
}
