<?php

use App\Jobs\ProcessStoredVisitImportJob;
use App\Models\Client;
use App\Models\Location;
use App\Models\User;
use App\Services\ImportVisitExcelService;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
});

it('sends a database notification when the visit import job completes successfully', function (): void {
    $user = User::factory()->create();

    $client = Client::query()->create([
        'name' => 'Cliente Job Notif',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Unica',
        'active' => true,
    ]);

    $relativePath = 'imports/visit-reports/job-notif-'.uniqid('', true).'.csv';
    Storage::disk('local')->put($relativePath, implode("\n", [
        'Fecha,Entrada,Salida,Conteo,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,1,Sin novedades,Marta',
    ]));

    $job = new ProcessStoredVisitImportJob(
        $relativePath,
        $user->id,
        basename($relativePath),
        false,
        false,
        $client->id,
    );

    $job->handle(app(ImportVisitExcelService::class));

    $row = DB::table('notifications')
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $user->id)
        ->first();

    expect($row)->not->toBeNull();

    $data = json_decode((string) $row->data, true);
    expect($data)->toBeArray()
        ->and($data['title'] ?? '')->toBe('Importacion de visitas lista')
        ->and($data['status'] ?? '')->toBe('success');

    Storage::disk('local')->delete($relativePath);
});

it('sends a database notification when the visit import job fails definitively', function (): void {
    $user = User::factory()->create();

    $job = new ProcessStoredVisitImportJob(
        'imports/visit-reports/no-existe-'.uniqid('', true).'.csv',
        $user->id,
        'no-existe.csv',
        false,
        false,
        null,
    );

    $job->failed(new RuntimeException('Archivo no encontrado'));

    $row = DB::table('notifications')
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $user->id)
        ->first();

    expect($row)->not->toBeNull();

    $data = json_decode((string) $row->data, true);
    expect($data)->toBeArray()
        ->and($data['title'] ?? '')->toBe('Importacion de visitas fallida')
        ->and($data['status'] ?? '')->toBe('danger');
});
