<?php

use App\Filament\Pages\ImportVisitReports;
use App\Jobs\ProcessStoredVisitImportJob;
use App\Models\Client;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\BirdTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->seed(BirdTypeSeeder::class);
});

it('marks import result as processed after processImport runs with valid preview', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Cliente Import Page',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Unica',
        'active' => true,
    ]);

    $dir = 'imports/visit-reports/'.uniqid('run_', true);
    Storage::disk('local')->makeDirectory($dir);
    $relativePath = $dir.'/ClienteImportPage.csv';
    Storage::disk('local')->put(
        $relativePath,
        implode("\n", [
            'Fecha,Entrada,Salida,Palomas,Cotorras,Observaciones,Nombre de usuario',
            '2026-04-10,08:00,09:30,3,1,Sin novedades,Marta',
        ]),
    );

    Queue::fake();

    $preview = [
        [
            'file_name' => 'ClienteImportPage.csv',
            'file_path' => $relativePath,
            'can_import' => true,
            'total_rows' => 1,
            'valid_rows' => 1,
            'invalid_rows' => 0,
            'errors' => [],
            'warnings' => [],
        ],
    ];

    $component = Livewire::actingAs($user)
        ->test(ImportVisitReports::class)
        ->set('data.preview_payload', json_encode($preview))
        ->call('processImport');

    $payload = json_decode((string) $component->get('data.import_result_payload'), true);

    expect($payload)->toBeArray()
        ->and($payload['processed'] ?? false)->toBeTrue()
        ->and($payload['success'] ?? false)->toBeTrue()
        ->and($payload['queued'] ?? false)->toBeTrue();

    Queue::assertPushed(ProcessStoredVisitImportJob::class, 1);
    Queue::assertPushed(ProcessStoredVisitImportJob::class, function (ProcessStoredVisitImportJob $job) use ($relativePath, $user): bool {
        return $job->relativePathOnLocalDisk === $relativePath
            && $job->originalFilename === 'ClienteImportPage.csv'
            && $job->userId === $user->id
            && $job->provisionClientAndSections === false
            && $job->replacePreviousImportSameFilename === false
            && $job->fallbackClientId === null;
    });

    expect($user->fresh()->notifications()->where('data->format', 'filament')->count())->toBeGreaterThanOrEqual(1);

    Storage::disk('local')->deleteDirectory($dir);
});

it('does not change import result payload when processImport runs with no importable files', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $preview = [
        [
            'file_name' => 'solo_errores.csv',
            'file_path' => 'imports/visit-reports/solo_errores.csv',
            'can_import' => false,
            'total_rows' => 1,
            'valid_rows' => 0,
            'invalid_rows' => 1,
            'errors' => ['Estructura invalida'],
            'warnings' => [],
        ],
    ];

    Livewire::actingAs($user)
        ->test(ImportVisitReports::class)
        ->set('data.preview_payload', json_encode($preview))
        ->call('processImport')
        ->assertSet('data.import_result_payload', ImportVisitReports::IMPORT_RESULT_PENDING_PAYLOAD);
});
