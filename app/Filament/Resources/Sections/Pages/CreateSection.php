<?php

namespace App\Filament\Resources\Sections\Pages;

use App\Filament\Resources\Sections\SectionResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateSection extends CreateRecord
{
    protected static string $resource = SectionResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;
}
