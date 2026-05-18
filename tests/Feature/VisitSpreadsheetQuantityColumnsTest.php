<?php

declare(strict_types=1);

use App\ClientImportMode;
use App\Models\Client;
use App\Models\Location;
use App\Services\VisitSpreadsheet\VisitSpreadsheetColumns;
use App\Services\VisitSpreadsheet\VisitSpreadsheetQuantityColumns;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
});

it('uses section columns when client has multiple locations even if import_mode is single sector compact', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Multi Seccion',
        'active' => true,
        'import_mode' => ClientImportMode::SingleSectorSingleBird,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Almacen',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Taller',
        'active' => true,
    ]);

    $specs = app(VisitSpreadsheetQuantityColumns::class)->forClient($client->fresh());

    expect($specs)->toHaveCount(2)
        ->and(collect($specs)->pluck('label')->sort()->values()->all())->toBe(['Almacen', 'Taller']);
});

it('capitalizes the first letter of spreadsheet table column labels', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Etiquetas',
        'active' => true,
        'import_mode' => ClientImportMode::MultiSectorSingleBird,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'almacen',
        'active' => true,
    ]);

    $labels = collect(app(VisitSpreadsheetColumns::class)->forClient($client->fresh()))
        ->map(fn ($column): string => (string) $column->getLabel())
        ->all();

    expect($labels)->toContain('Almacen')
        ->and($labels)->not->toContain('almacen');
});

it('keeps single Conteo column for compact mode with one location', function (): void {
    $client = Client::query()->create([
        'name' => 'Cliente Compacto',
        'active' => true,
        'import_mode' => ClientImportMode::SingleSectorSingleBird,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Unica',
        'active' => true,
    ]);

    $specs = app(VisitSpreadsheetQuantityColumns::class)->forClient($client->fresh());

    expect($specs)->toHaveCount(1)
        ->and($specs[0]['label'])->toBe('Conteo');
});

it('includes all active locations for multi-sector columns even when one matches client name', function (): void {
    $client = Client::query()->create([
        'name' => 'Cutcsa2',
        'active' => true,
        'import_mode' => ClientImportMode::MultiSectorSingleBird,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Cutcsa2',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Taller',
        'active' => true,
    ]);

    $specs = app(VisitSpreadsheetQuantityColumns::class)->forClient($client->fresh());

    expect($specs)->toHaveCount(2)
        ->and(collect($specs)->pluck('label')->sort()->values()->all())->toBe(['Cutcsa2', 'Taller']);
});
