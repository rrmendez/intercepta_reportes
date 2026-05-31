<?php

namespace App\Filament\Resources\BirdTypes\Pages;

use App\Filament\Resources\BirdTypes\BirdTypeResource;
use App\Services\BirdTypes\BirdTypeTokenNormalizer;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListBirdTypes extends ListRecords
{
    protected static string $resource = BirdTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading('Crear tipo de ave')
                ->modalSubmitActionLabel('Crear')
                ->createAnother(false)
                ->mutateFormDataUsing(fn (array $data): array => $this->ensureSlug($data)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function ensureSlug(array $data): array
    {
        if (filled($data['slug'] ?? null)) {
            return $data;
        }

        $source = (string) ($data['name'] ?? $data['common_name'] ?? '');

        $data['slug'] = Str::slug($source) !== ''
            ? Str::slug($source)
            : app(BirdTypeTokenNormalizer::class)->normalize($source);

        return $data;
    }
}
