<?php

use App\Jobs\ProcessStoredVisitImportJob;
use App\Models\Client;
use App\Models\Location;
use Database\Seeders\BirdTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->seed(BirdTypeSeeder::class);
});

it('runs dry-run preview without queueing jobs', function (): void {
    Queue::fake();
    createClientWithLocation('Cliente Import Page');

    $directory = createImportDirectory('dry_run');
    File::put(
        $directory.'/ClienteImportPage_Constancia_de_Servicio_20260504.csv',
        sampleCompactCsvRow(),
    );

    $this->artisan('visits:import', [
        '--directory' => relativeToBasePath($directory),
        '--dry-run' => true,
        '--provision-client-and-sections' => true,
    ])
        ->expectsOutputToContain('Dry-run completado')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('queues jobs during execute mode with flags', function (): void {
    Queue::fake();
    createClientWithLocation('Cliente Import Page');

    $directory = createImportDirectory('execute_queue');
    File::put(
        $directory.'/ClienteImportPage_Constancia_de_Servicio_20260504.csv',
        sampleCompactCsvRow(),
    );

    $this->artisan('visits:import', [
        '--directory' => relativeToBasePath($directory),
        '--execute' => true,
        '--queue' => true,
        '--replace' => true,
        '--provision-client-and-sections' => true,
    ])->assertSuccessful();

    Queue::assertPushed(ProcessStoredVisitImportJob::class, 1);
    Queue::assertPushed(ProcessStoredVisitImportJob::class, function (ProcessStoredVisitImportJob $job): bool {
        return $job->provisionClientAndSections === true
            && $job->replacePreviousImportSameFilename === true
            && $job->originalFilename === 'ClienteImportPage_Constancia_de_Servicio_20260504.csv';
    });
});

it('deletes matching clients before importing when requested', function (): void {
    Queue::fake();

    $client = createClientWithLocation('Cliente Import Page');

    $directory = createImportDirectory('delete_clients');
    File::put(
        $directory.'/ClienteImportPage_Constancia_de_Servicio_20260504.csv',
        sampleCompactCsvRow(),
    );

    $this->artisan('visits:import', [
        '--directory' => relativeToBasePath($directory),
        '--execute' => true,
        '--delete-clients' => true,
        '--provision-client-and-sections' => true,
    ])->assertSuccessful();

    expect(Client::query()->whereKey($client->id)->exists())->toBeFalse();
    Queue::assertPushed(ProcessStoredVisitImportJob::class, 1);
});

function createClientWithLocation(string $name): Client
{
    $client = Client::query()->create([
        'name' => $name,
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => $name,
        'active' => true,
    ]);

    return $client;
}

function createImportDirectory(string $suffix): string
{
    $directory = base_path('tests/tmp/import_visit_reports_command/'.$suffix.'_'.uniqid('', true));
    File::ensureDirectoryExists($directory);

    return $directory;
}

function relativeToBasePath(string $absolutePath): string
{
    return ltrim(str_replace(base_path(), '', $absolutePath), '/');
}

function sampleCompactCsvRow(): string
{
    return implode("\n", [
        'Fecha,Entrada,Salida,Palomas,Cotorras,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,3,1,Sin novedades,Marta',
    ]);
}
