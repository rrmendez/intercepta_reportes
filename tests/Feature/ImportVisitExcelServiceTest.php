<?php

use App\ClientImportMode;
use App\Jobs\ProcessStoredVisitImportJob;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Location;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitImport;
use App\Services\BirdTypes\BirdTypeResolver;
use App\Services\ImportVisitExcelService;
use App\Services\VisitImport\Persistence\VisitImportPersistence;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
});

it('imports compact xlsx files when conteo values are stored as whole number floats', function () {
    $client = Client::query()->create([
        'name' => 'Urusal',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Urusal',
        'active' => true,
    ]);

    $xlsxPath = createTempCompactXlsxWithFloatQuantities();

    try {
        $preview = app(ImportVisitExcelService::class)->preview($xlsxPath, $client->id);

        expect($preview['valid_rows'])->toBe(1)
            ->and($preview['invalid_rows'])->toBe(0)
            ->and($preview['errors'])->toBeEmpty();

        $result = app(ImportVisitExcelService::class)->import($xlsxPath, $client->id);

        expect($result['persisted_rows'])->toBe(1)
            ->and($result['skipped_rows'])->toBe(0);

        $visitReport = Visit::query()
            ->with('visitReports')
            ->firstOrFail()
            ->visitReports
            ->firstOrFail();

        expect($visitReport->quantity)->toBe(1);
    } finally {
        @unlink($xlsxPath);
    }
});

it('imports compact xlsx files with conteo for clients with a single location', function () {
    $client = Client::query()->create([
        'name' => 'Vaino',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Jumbos',
        'active' => true,
    ]);

    $xlsxPath = createTempCompactXlsx();

    try {
        $result = app(ImportVisitExcelService::class)->import($xlsxPath, $client->id);

        expect($result['status'])->toBe('imported')
            ->and($result['total_rows'])->toBe(1)
            ->and($result['persisted_rows'])->toBe(1)
            ->and($result['skipped_rows'])->toBe(0);

        $visit = Visit::query()->with('visitReports.location', 'visitReports.birdType')->firstOrFail();
        $visitReport = $visit->visitReports->firstOrFail();

        expect($visit->client_id)->toBe($client->id)
            ->and($visitReport->location->name)->toBe('Jumbos')
            ->and($visitReport->birdType->name)->toBe('Palomas')
            ->and($visitReport->quantity)->toBe(2);
    } finally {
        @unlink($xlsxPath);
    }
});

it('detects the client from file name when fallback client id is not provided', function () {
    $client = Client::query()->create([
        'name' => 'Asistencial Cantegril',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Conteo',
        'active' => true,
    ]);

    $xlsxPath = createTempCompactXlsx('Asistencial_Cantegril_Constancia_de_Servicio_20260305');

    try {
        $result = app(ImportVisitExcelService::class)->import($xlsxPath);

        expect($result['persisted_rows'])->toBe(1)
            ->and($result['skipped_rows'])->toBe(0);

        $visit = Visit::query()->with('visitReports.location')->firstOrFail();
        $visitReport = $visit->visitReports->firstOrFail();

        expect($visit->client_id)->toBe($client->id)
            ->and($visitReport->location->name)->toBe('Conteo');
    } finally {
        @unlink($xlsxPath);
    }
});

it('resolves client when the file name prefix uses camel case before constancia', function () {
    $client = Client::query()->create([
        'name' => 'Zona America',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Conteo',
        'active' => true,
    ]);

    $xlsxPath = createTempCompactXlsx('ZonaAmerica_Constancia_de_Servicio_20260406');

    try {
        $result = app(ImportVisitExcelService::class)->import($xlsxPath);

        expect($result['persisted_rows'])->toBe(1)
            ->and($result['skipped_rows'])->toBe(0);

        $visit = Visit::query()->with('visitReports.location')->firstOrFail();

        expect($visit->client_id)->toBe($client->id);
    } finally {
        @unlink($xlsxPath);
    }
});

it('queues one job per stored path for bulk visit import', function () {
    Queue::fake();

    ProcessStoredVisitImportJob::dispatch('imports/visit-reports/Zonamerica_Constancia_de_Servicio_20260406.xlsx', 1, 'Zonamerica_Constancia_de_Servicio_20260406.xlsx', false, false);
    ProcessStoredVisitImportJob::dispatch('imports/visit-reports/Cutcsa_Constancia_de_Servicio_20260406.xlsx', 1, 'Cutcsa_Constancia_de_Servicio_20260406.xlsx', true, false);

    Queue::assertPushed(ProcessStoredVisitImportJob::class, 2);
});

it('fails compact multi-location import without client when provision is false', function () {
    $csvPath = storage_path('framework/testing/ProvTest_Corp_Constancia_de_Servicio_20260406-'.uniqid('', true).'.csv');
    file_put_contents($csvPath, implode("\n", [
        'Fecha,Entrada,Salida,Jumbos,Conversiones,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,3,2,Sin novedades,Marta',
    ]));

    try {
        app(ImportVisitExcelService::class)->import($csvPath, null, [
            'provision_client_and_sections' => false,
        ]);

        $this->fail('Expected validation exception.');
    } catch (ValidationException $exception) {
        expect(Client::query()->count())->toBe(0);
    } finally {
        @unlink($csvPath);
    }
});

it('creates client and section locations from compact csv when provision is true', function () {
    $csvPath = storage_path('framework/testing/ProvTest_Corp_Constancia_de_Servicio_20260406-'.uniqid('', true).'.csv');
    file_put_contents($csvPath, implode("\n", [
        'Fecha,Entrada,Salida,Jumbos,Conversiones,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,3,2,Sin novedades,Marta',
    ]));

    try {
        $result = app(ImportVisitExcelService::class)->import($csvPath, null, [
            'provision_client_and_sections' => true,
        ]);

        expect($result['persisted_rows'])->toBe(1)
            ->and($result['skipped_rows'])->toBe(0);

        $client = Client::query()->where('name', 'Prov Test Corp')->firstOrFail();
        $named = $client->namedLocations()->orderBy('name')->pluck('name')->all();

        expect(collect($named)->sort()->values()->all())->toBe(['Conversiones', 'Jumbos']);

        $visit = Visit::query()->where('client_id', $client->id)->with('visitReports.location')->firstOrFail();
        expect($visit->visitReports)->toHaveCount(2);
    } finally {
        @unlink($csvPath);
    }
});

it('previews compact file without client when provision is enabled in context', function () {
    $csvPath = storage_path('framework/testing/PreviewProv_Corp_Constancia_de_Servicio_20260406-'.uniqid('', true).'.csv');
    file_put_contents($csvPath, implode("\n", [
        'Fecha,Entrada,Salida,Jumbos,Conversiones,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,3,2,Sin novedades,Marta',
    ]));

    try {
        expect(fn () => app(ImportVisitExcelService::class)->preview($csvPath, null, []))
            ->toThrow(ValidationException::class);

        expect(Client::query()->count())->toBe(0)
            ->and(Location::query()->count())->toBe(0);

        $preview = app(ImportVisitExcelService::class)->preview($csvPath, null, [
            'provision_client_and_sections' => true,
        ]);

        expect($preview['valid_rows'])->toBe(1)
            ->and($preview['invalid_rows'])->toBe(0)
            ->and(Client::query()->count())->toBe(0)
            ->and(Location::query()->count())->toBe(0)
            ->and(Client::query()->where('name', 'Preview Prov Corp')->exists())->toBeFalse();
    } finally {
        @unlink($csvPath);
    }
});

it('rolls back the whole import when persist fails', function () {
    $mock = Mockery::mock(VisitImportPersistence::class, [app(BirdTypeResolver::class)])->makePartial();
    $mock->shouldReceive('persist')->andThrow(new RuntimeException('simulated'));
    app()->instance(VisitImportPersistence::class, $mock);

    $client = Client::query()->create([
        'name' => 'Rollback Test Client',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Solo',
        'active' => true,
    ]);

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Conteo,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,1,Sin novedades,Marta',
    ]));

    try {
        expect(fn () => app(ImportVisitExcelService::class)->import($csvPath, $client->id))
            ->toThrow(RuntimeException::class);

        expect(Visit::query()->count())->toBe(0)
            ->and(VisitImport::query()->count())->toBe(0);
    } finally {
        app()->forgetInstance(VisitImportPersistence::class);
        @unlink($csvPath);
        Mockery::close();
    }
});

it('rolls back provisioned client and locations when persist fails', function () {
    $mock = Mockery::mock(VisitImportPersistence::class, [app(BirdTypeResolver::class)])->makePartial();
    $mock->shouldReceive('persist')->andThrow(new RuntimeException('simulated'));
    app()->instance(VisitImportPersistence::class, $mock);

    $csvPath = storage_path('framework/testing/RollbackProv_Corp_Constancia_de_Servicio_20260406-'.uniqid('', true).'.csv');
    file_put_contents($csvPath, implode("\n", [
        'Fecha,Entrada,Salida,Jumbos,Conversiones,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,3,2,Sin novedades,Marta',
    ]));

    try {
        expect(fn () => app(ImportVisitExcelService::class)->import($csvPath, null, [
            'provision_client_and_sections' => true,
        ]))->toThrow(RuntimeException::class);

        expect(Client::query()->count())->toBe(0)
            ->and(Location::query()->count())->toBe(0)
            ->and(VisitImport::query()->count())->toBe(0);
    } finally {
        app()->forgetInstance(VisitImportPersistence::class);
        @unlink($csvPath);
        Mockery::close();
    }
});

it('describes new client from filename when provisioning hint is requested', function () {
    $csvPath = storage_path('framework/testing/HintCorp_Constancia_de_Servicio_20260406-'.uniqid('', true).'.csv');
    file_put_contents($csvPath, implode("\n", [
        'Fecha,Entrada,Salida,Jumbos,Conversiones,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,3,2,Sin novedades,Marta',
    ]));

    try {
        $hint = app(ImportVisitExcelService::class)->compactClientProvisioningHintFromPath($csvPath);

        expect($hint['kind'])->toBe('will_create_client')
            ->and($hint['display_name'])->toBe('Hint Corp');
    } finally {
        @unlink($csvPath);
    }
});

it('provisions new client Cutcsa2 with sections when Cutsa and Cutcsa exist and filename is Cutcsa2_Constancia', function () {
    Client::query()->create([
        'name' => 'Cutsa',
        'active' => true,
    ]);

    Client::query()->create([
        'name' => 'Cutcsa',
        'active' => true,
    ]);

    $dir = storage_path('framework/testing/'.uniqid('cutcsa2_import_', true));
    mkdir($dir, 0755, true);
    $csvPath = $dir.'/Cutcsa2_Constancia_de_Servicio_20260406.csv';

    file_put_contents($csvPath, implode("\n", [
        'Fecha,Entrada,Salida,Jumbos,Conversiones,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,5,1,Sin novedades,Marta',
    ]));

    try {
        $hint = app(ImportVisitExcelService::class)->compactClientProvisioningHintFromPath($csvPath);
        expect($hint['kind'])->toBe('will_create_client')
            ->and($hint['display_name'])->toBe('Cutcsa2');

        $result = app(ImportVisitExcelService::class)->import($csvPath, null, [
            'provision_client_and_sections' => true,
        ]);

        expect($result['persisted_rows'])->toBe(1)
            ->and(Client::query()->count())->toBe(3);

        expect(Client::query()->where('name', 'Cutsa')->exists())->toBeTrue()
            ->and(Client::query()->where('name', 'Cutcsa')->exists())->toBeTrue();

        $newClient = Client::query()->where('name', 'Cutcsa2')->firstOrFail();

        $named = $newClient->namedLocations()->orderBy('name')->pluck('name')->all();
        expect(collect($named)->sort()->values()->all())->toBe(['Conversiones', 'Jumbos']);

        $visit = Visit::query()->where('client_id', $newClient->id)->with('visitReports')->firstOrFail();
        expect($visit->visitReports)->toHaveCount(2);
    } finally {
        @unlink($csvPath);
        @rmdir($dir);
    }
});

it('compact import matches existing client only when filename prefix equals client name (Cliente_Nuevo_Constancia)', function () {
    $client = Client::query()->create([
        'name' => 'Cliente Nuevo',
        'active' => true,
    ]);

    foreach (['Jumbos', 'Conversiones'] as $name) {
        Location::query()->create([
            'client_id' => $client->id,
            'name' => $name,
            'active' => true,
        ]);
    }

    $dir = storage_path('framework/testing/'.uniqid('cliente_nuevo_exact_', true));
    mkdir($dir, 0755, true);
    $csvPath = $dir.'/Cliente_Nuevo_Constancia_de_Servicio_20260406.csv';

    file_put_contents($csvPath, implode("\n", [
        'Fecha,Entrada,Salida,Jumbos,Conversiones,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,4,1,Sin novedades,Marta',
    ]));

    try {
        $hint = app(ImportVisitExcelService::class)->compactClientProvisioningHintFromPath($csvPath);
        expect($hint['kind'])->toBe('existing_client')
            ->and($hint['matched_client_name'])->toBe('Cliente Nuevo');

        $result = app(ImportVisitExcelService::class)->import($csvPath, null, [
            'provision_client_and_sections' => false,
        ]);

        expect($result['persisted_rows'])->toBe(1)
            ->and(Client::query()->count())->toBe(1);
    } finally {
        @unlink($csvPath);
        @rmdir($dir);
    }
});

it('compact import treats Cliente_Nuevo_Ejemplo prefix as a different client than Cliente Nuevo', function () {
    Client::query()->create([
        'name' => 'Cliente Nuevo',
        'active' => true,
    ]);

    $dir = storage_path('framework/testing/'.uniqid('cliente_nuevo_ejemplo_', true));
    mkdir($dir, 0755, true);
    $csvPath = $dir.'/Cliente_Nuevo_Ejemplo_Constancia_de_Servicio_20260406.csv';

    file_put_contents($csvPath, implode("\n", [
        'Fecha,Entrada,Salida,Jumbos,Conversiones,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,4,1,Sin novedades,Marta',
    ]));

    try {
        $hint = app(ImportVisitExcelService::class)->compactClientProvisioningHintFromPath($csvPath);
        expect($hint['kind'])->toBe('will_create_client')
            ->and($hint['display_name'])->toBe('Cliente Nuevo Ejemplo');

        $result = app(ImportVisitExcelService::class)->import($csvPath, null, [
            'provision_client_and_sections' => true,
        ]);

        expect($result['persisted_rows'])->toBe(1)
            ->and(Client::query()->count())->toBe(2);

        Client::query()->where('name', 'Cliente Nuevo Ejemplo')->firstOrFail();
    } finally {
        @unlink($csvPath);
        @rmdir($dir);
    }
});

it('removes prior visits from same original filename when replace_previous_import_same_filename is true', function () {
    $client = Client::query()->create([
        'name' => 'Replace Co',
        'active' => true,
    ]);

    foreach (['Jumbos', 'Conversiones'] as $name) {
        Location::query()->create([
            'client_id' => $client->id,
            'name' => $name,
            'active' => true,
        ]);
    }

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Jumbos,Conversiones,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,1,2,Sin novedades,Marta',
    ]));

    $baseContext = [
        'original_filename' => 'stable-dup.csv',
    ];

    try {
        $first = app(ImportVisitExcelService::class)->import($csvPath, $client->id, $baseContext);
        expect($first['persisted_rows'])->toBe(1);

        $firstImport = VisitImport::query()->where('original_filename', 'stable-dup.csv')->orderBy('id')->firstOrFail();
        expect(Visit::query()->where('visit_import_id', $firstImport->id)->count())->toBeGreaterThan(0);

        $second = app(ImportVisitExcelService::class)->import($csvPath, $client->id, array_merge($baseContext, [
            'replace_previous_import_same_filename' => true,
        ]));

        expect($second['persisted_rows'])->toBe(1);
        expect(strtolower(implode(' ', $second['warnings'] ?? [])))->toContain('se eliminaron');

        expect(Visit::query()->where('visit_import_id', $firstImport->id)->count())->toBe(0);

        $secondImport = VisitImport::query()->where('original_filename', 'stable-dup.csv')->orderByDesc('id')->firstOrFail();
        expect(Visit::query()->where('visit_import_id', $secondImport->id)->count())->toBeGreaterThan(0);
    } finally {
        @unlink($csvPath);
    }
});

it('imports compact csv files with multiple location columns between salida and observaciones', function () {
    $client = Client::query()->create([
        'name' => 'Cliente Multi',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Jumbos',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Conversiones',
        'active' => true,
    ]);

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Jumbos,Conversiones,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,3,2,Sin novedades,Marta',
    ]));

    try {
        $result = app(ImportVisitExcelService::class)->import($csvPath, $client->id);

        expect($result['total_rows'])->toBe(1)
            ->and($result['persisted_rows'])->toBe(1)
            ->and($result['skipped_rows'])->toBe(0);

        $visitReports = Visit::query()
            ->with('visitReports.location', 'visitReports.birdType')
            ->firstOrFail()
            ->visitReports
            ->sortBy(fn ($report): string => $report->location->name)
            ->values();

        expect($visitReports)->toHaveCount(2)
            ->and($visitReports[0]->location->name)->toBe('Conversiones')
            ->and($visitReports[0]->quantity)->toBe(2)
            ->and($visitReports[0]->birdType->name)->toBe('Palomas')
            ->and($visitReports[1]->location->name)->toBe('Jumbos')
            ->and($visitReports[1]->quantity)->toBe(3)
            ->and($visitReports[1]->birdType->name)->toBe('Palomas');
    } finally {
        @unlink($csvPath);
    }
});

it('imports every compact multi-location row without overwriting previous rows', function () {
    $client = Client::query()->create([
        'name' => 'Cliente Multi Rows',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Jumbos',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Conversiones',
        'active' => true,
    ]);

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Jumbos,Conversiones,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,3,2,Sin novedades,Marta',
        '2026-04-11,10:00,11:15,1,4,Recorrido completo,Marta',
    ]));

    try {
        $result = app(ImportVisitExcelService::class)->import($csvPath, $client->id);

        expect($result['total_rows'])->toBe(2)
            ->and($result['persisted_rows'])->toBe(2)
            ->and($result['skipped_rows'])->toBe(0);

        $visitReports = Visit::query()
            ->with('visitReports.location')
            ->get()
            ->flatMap->visitReports;

        expect($visitReports)->toHaveCount(4);
    } finally {
        @unlink($csvPath);
    }
});

it('previews compact multi-location files using source visit rows count', function () {
    $client = Client::query()->create([
        'name' => 'Cliente Preview Multi Rows',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Jumbos',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Conversiones',
        'active' => true,
    ]);

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Jumbos,Conversiones,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,3,2,Sin novedades,Marta',
        '2026-04-11,10:00,11:15,1,4,Recorrido completo,Marta',
    ]));

    try {
        $preview = app(ImportVisitExcelService::class)->preview($csvPath, $client->id);

        expect($preview['total_rows'])->toBe(2)
            ->and($preview['valid_rows'])->toBe(2)
            ->and($preview['invalid_rows'])->toBe(0);
    } finally {
        @unlink($csvPath);
    }
});

it('matches compact multi-location columns that include spaces and numbers', function () {
    $client = Client::query()->create([
        'name' => 'Cliente Planta 4',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Jumbos',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Planta 4',
        'active' => true,
    ]);

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Jumbos,Planta 4,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,1,3,Sin novedades,Marta',
    ]));

    try {
        $result = app(ImportVisitExcelService::class)->import($csvPath, $client->id);

        expect($result['total_rows'])->toBe(1)
            ->and($result['persisted_rows'])->toBe(1)
            ->and($result['skipped_rows'])->toBe(0);

        $visitReports = Visit::query()
            ->with('visitReports.location')
            ->firstOrFail()
            ->visitReports
            ->sortBy(fn ($report): string => $report->location->name)
            ->values();

        expect($visitReports)->toHaveCount(2)
            ->and($visitReports[0]->location->name)->toBe('Jumbos')
            ->and($visitReports[0]->quantity)->toBe(1)
            ->and($visitReports[1]->location->name)->toBe('Planta 4')
            ->and($visitReports[1]->quantity)->toBe(3);
    } finally {
        @unlink($csvPath);
    }
});

it('fails compact multi sector import when a quantity column does not match any section', function () {
    $client = Client::query()->create([
        'name' => 'Cliente sin match',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Jumbos',
        'active' => true,
    ]);

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Jumbos,Almacenamiento,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,1,4,Sin novedades,Marta',
    ]));

    try {
        expect(fn () => app(ImportVisitExcelService::class)->import($csvPath, $client->id))
            ->toThrow(ValidationException::class);

        expect(fn () => app(ImportVisitExcelService::class)->preview($csvPath, $client->id))
            ->toThrow(ValidationException::class);
    } finally {
        @unlink($csvPath);
    }
});

it('fails compact conteo import when selected client has multiple active locations', function () {
    $client = Client::query()->create([
        'name' => 'Cliente multiple locations',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Jumbos',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Conversiones',
        'active' => true,
    ]);

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Conteo,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,4,Sin novedades,Marta',
    ]));

    try {
        app(ImportVisitExcelService::class)->import($csvPath, $client->id);

        $this->fail('Expected validation exception.');
    } catch (ValidationException $exception) {
        $firstError = collect($exception->errors())->flatten()->first();

        expect($firstError)->toContain('exactamente una ubicacion activa');
    } finally {
        @unlink($csvPath);
    }
});

function createTempCompactXlsxWithFloatQuantities(string $fileNamePrefix = 'visit-import-float-qty'): string
{
    $xlsxPath = storage_path('framework/testing/'.$fileNamePrefix.'-'.uniqid('', true).'.xlsx');

    $worksheetXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1">
      <c r="A1" t="s"><v>0</v></c>
      <c r="B1" t="s"><v>1</v></c>
      <c r="C1" t="s"><v>2</v></c>
      <c r="D1" t="s"><v>3</v></c>
      <c r="E1" t="s"><v>4</v></c>
      <c r="F1" t="s"><v>5</v></c>
    </row>
    <row r="2">
      <c r="A2" t="s"><v>6</v></c>
      <c r="B2" t="s"><v>7</v></c>
      <c r="C2" t="s"><v>8</v></c>
      <c r="D2"><v>1.0</v></c>
      <c r="E2" t="s"><v>9</v></c>
      <c r="F2" t="s"><v>10</v></c>
    </row>
  </sheetData>
</worksheet>
XML;

    $sharedStringsXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="11" uniqueCount="11">
  <si><t>Fecha</t></si>
  <si><t>Entrada</t></si>
  <si><t>Salida</t></si>
  <si><t>Conteo</t></si>
  <si><t>Observaciones</t></si>
  <si><t>Nombre de usuario</t></si>
  <si><t>8/4/2026</t></si>
  <si><t>15:00</t></si>
  <si><t>16:30</t></si>
  <si><t>Se trabaja correctamente</t></si>
  <si><t>Rodrigo</t></si>
</sst>
XML;

    $zip = new ZipArchive;
    $zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
    $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
    $zip->close();

    return $xlsxPath;
}

function createTempCompactXlsx(string $fileNamePrefix = 'visit-import'): string
{
    $xlsxPath = storage_path('framework/testing/'.$fileNamePrefix.'-'.uniqid('', true).'.xlsx');

    $worksheetXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1">
      <c r="A1" t="s"><v>0</v></c>
      <c r="B1" t="s"><v>1</v></c>
      <c r="C1" t="s"><v>2</v></c>
      <c r="D1" t="s"><v>3</v></c>
      <c r="E1" t="s"><v>4</v></c>
      <c r="F1" t="s"><v>5</v></c>
    </row>
    <row r="2">
      <c r="A2"><v>46056</v></c>
      <c r="B2"><v>0.6666666667</v></c>
      <c r="C2"><v>0.7083333333</v></c>
      <c r="D2"><v>2</v></c>
      <c r="E2" t="s"><v>6</v></c>
      <c r="F2" t="s"><v>7</v></c>
    </row>
  </sheetData>
</worksheet>
XML;

    $sharedStringsXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="8" uniqueCount="8">
  <si><t>Fecha</t></si>
  <si><t>Entrada</t></si>
  <si><t>Salida</t></si>
  <si><t>Conteo</t></si>
  <si><t>Observaciones</t></si>
  <si><t>Nombre de usuario</t></si>
  <si><t>Se capturan 2 palomas</t></si>
  <si><t>Miguel</t></si>
</sst>
XML;

    $zip = new ZipArchive;
    $zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
    $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
    $zip->close();

    return $xlsxPath;
}

function createTempCsv(string $content): string
{
    $csvPath = storage_path('framework/testing/visit-import-'.uniqid('', true).'.csv');
    file_put_contents($csvPath, $content);

    return $csvPath;
}

it('imports compact csv with bird columns for single sector multi bird mode', function () {
    $client = Client::query()->create([
        'name' => 'Cliente Aves',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Unica',
        'active' => true,
    ]);

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Palomas,Cotorras,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,3,1,Sin novedades,Marta',
    ]));

    try {
        $result = app(ImportVisitExcelService::class)->import($csvPath, $client->id);

        expect($result['persisted_rows'])->toBe(1)
            ->and($result['skipped_rows'])->toBe(0);

        $visitReports = Visit::query()
            ->with('visitReports.birdType', 'visitReports.location')
            ->firstOrFail()
            ->visitReports
            ->sortBy(fn ($report): string => $report->birdType->name)
            ->values();

        expect($visitReports)->toHaveCount(2)
            ->and($visitReports[0]->birdType->name)->toBe('Cotorras')
            ->and($visitReports[0]->quantity)->toBe(1)
            ->and($visitReports[1]->birdType->name)->toBe('Palomas')
            ->and($visitReports[1]->quantity)->toBe(3)
            ->and($visitReports[0]->location->name)->toBe('Unica');
    } finally {
        @unlink($csvPath);
    }
});

it('warns and skips unknown bird type columns in compact single sector multi bird', function () {
    $client = Client::query()->create([
        'name' => 'Cliente Aves Mix',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Unica',
        'active' => true,
    ]);

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Palomas,DragonesInexistentes,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,3,9,Sin novedades,Marta',
    ]));

    try {
        $result = app(ImportVisitExcelService::class)->import($csvPath, $client->id);

        $warningsText = strtolower(collect($result['warnings'] ?? [])->implode(' '));
        expect($warningsText)->toContain('dragones_inexistentes')
            ->and($warningsText)->toContain('omitira')
            ->and($result['persisted_rows'])->toBe(1);

        $visitReports = Visit::query()
            ->with('visitReports.birdType')
            ->firstOrFail()
            ->visitReports;

        expect($visitReports)->toHaveCount(1)
            ->and($visitReports->first()->birdType->name)->toBe('Palomas');
    } finally {
        @unlink($csvPath);
    }
});

it('returns warnings when multi sector file omits some section columns', function () {
    $client = Client::query()->create([
        'name' => 'Cliente Tres Secciones',
        'active' => true,
    ]);

    foreach (['Almacen', 'Jumbos', 'Conversiones'] as $name) {
        Location::query()->create([
            'client_id' => $client->id,
            'name' => $name,
            'active' => true,
        ]);
    }

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Jumbos,Conversiones,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,3,2,Sin novedades,Marta',
    ]));

    try {
        $preview = app(ImportVisitExcelService::class)->preview($csvPath, $client->id);

        expect($preview['valid_rows'])->toBe(1)
            ->and($preview['warnings'])->not->toBeEmpty();

        $result = app(ImportVisitExcelService::class)->import($csvPath, $client->id);

        expect($result['persisted_rows'])->toBe(1)
            ->and($result['warnings'])->not->toBeEmpty();
    } finally {
        @unlink($csvPath);
    }
});

it('rejects multi sector compact file when a quantity column does not match any client section', function () {
    $client = Client::query()->create([
        'name' => 'Cliente Cutcsa Like',
        'active' => true,
    ]);

    foreach (['Exterior', 'Taller'] as $name) {
        Location::query()->create([
            'client_id' => $client->id,
            'name' => $name,
            'active' => true,
        ]);
    }

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Exterior,TallerIncorrecto,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,1,2,Sin novedades,Marta',
    ]));

    try {
        expect(fn () => app(ImportVisitExcelService::class)->preview($csvPath, $client->id))
            ->toThrow(ValidationException::class);
    } finally {
        @unlink($csvPath);
    }
});

it('rejects multisector compact when a section column is a typo like TALLER1 instead of Taller', function () {
    $client = Client::query()->create([
        'name' => 'Cliente Cutcsa Like Uppercase',
        'active' => true,
    ]);

    foreach (['Exterior', 'Taller'] as $name) {
        Location::query()->create([
            'client_id' => $client->id,
            'name' => $name,
            'active' => true,
        ]);
    }

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,EXTERIOR,TALLER1,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,1,2,Sin novedades,Marta',
    ]));

    try {
        expect(fn () => app(ImportVisitExcelService::class)->preview($csvPath, $client->id))
            ->toThrow(ValidationException::class);
    } finally {
        @unlink($csvPath);
    }
});

it('imports composite bird and section column headers', function () {
    $client = Client::query()->create([
        'name' => 'Cliente Compuesto',
        'active' => true,
    ]);

    foreach (['Exterior', 'Taller'] as $name) {
        Location::query()->create([
            'client_id' => $client->id,
            'name' => $name,
            'active' => true,
        ]);
    }

    BirdType::factory()->create([
        'slug' => 'gaviotas',
        'name' => 'Gaviotas',
        'common_name' => 'Gaviota',
        'common_name_plural' => 'Gaviotas',
        'scientific_name' => 'Larus dominicanus',
    ]);

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Palomas,Cotorras,Exterior,Taller,Palomas Exterior,Gaviotas Exterior,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,99,88,4,,2,1,Sin novedades,Marta',
    ]));

    try {
        $result = app(ImportVisitExcelService::class)->import($csvPath, $client->id);

        expect($result['persisted_rows'])->toBe(1);

        $reports = Visit::query()
            ->with('visitReports.birdType', 'visitReports.location')
            ->firstOrFail()
            ->visitReports;

        expect($reports)->toHaveCount(3);

        $palomasExteriorSection = $reports->first(
            fn ($report): bool => $report->birdType->name === 'Palomas'
                && $report->location->name === 'Exterior'
                && $report->quantity === 4,
        );
        $palomasExteriorComposite = $reports->first(
            fn ($report): bool => $report->birdType->name === 'Palomas'
                && $report->location->name === 'Exterior'
                && $report->quantity === 2,
        );
        $gaviotasExterior = $reports->first(
            fn ($report): bool => $report->birdType->name === 'Gaviotas' && $report->location->name === 'Exterior',
        );

        expect($palomasExteriorSection)->not->toBeNull()
            ->and($palomasExteriorComposite)->not->toBeNull()
            ->and($gaviotasExterior)->not->toBeNull()
            ->and($gaviotasExterior->quantity)->toBe(1);
    } finally {
        @unlink($csvPath);
    }
});

it('imports hybrid multi sector file with ignored bird columns palomas in sections and composite pairs', function () {
    $client = Client::query()->create([
        'name' => 'Cliente Hibrido',
        'active' => true,
    ]);

    foreach ([
        'Entradas',
        'Silos',
        'Techo',
        'Trampa',
        'Galpon',
        'Exterior',
        'Tanque de agua',
    ] as $name) {
        Location::query()->create([
            'client_id' => $client->id,
            'name' => $name,
            'active' => true,
        ]);
    }

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Palomas,Cotorras,Tordos,Entradas,Silos,Techo,Trampa,Galpon,Exterior,Cotorras Tanque de agua,Cotorras Exterior,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,99,88,77,1,2,,,3,4,5,6,Sin novedades,Marta',
    ]));

    try {
        $result = app(ImportVisitExcelService::class)->import($csvPath, $client->id);

        expect($result['persisted_rows'])->toBe(1);

        $reports = Visit::query()
            ->with('visitReports.birdType', 'visitReports.location')
            ->firstOrFail()
            ->visitReports;

        expect($reports)->toHaveCount(6);

        $palomasEntradas = $reports->first(
            fn ($report): bool => $report->birdType->name === 'Palomas' && $report->location->name === 'Entradas',
        );
        $palomasExterior = $reports->first(
            fn ($report): bool => $report->birdType->name === 'Palomas' && $report->location->name === 'Exterior',
        );
        $cotorrasTanque = $reports->first(
            fn ($report): bool => $report->birdType->name === 'Cotorras' && $report->location->name === 'Tanque de agua',
        );
        $cotorrasExterior = $reports->first(
            fn ($report): bool => $report->birdType->name === 'Cotorras' && $report->location->name === 'Exterior',
        );

        expect($palomasEntradas)->not->toBeNull()
            ->and($palomasEntradas->quantity)->toBe(1)
            ->and($palomasExterior)->not->toBeNull()
            ->and($palomasExterior->quantity)->toBe(4)
            ->and($cotorrasTanque)->not->toBeNull()
            ->and($cotorrasTanque->quantity)->toBe(5)
            ->and($cotorrasExterior)->not->toBeNull()
            ->and($cotorrasExterior->quantity)->toBe(6);

        expect($reports->contains(fn ($report): bool => $report->birdType->name === 'Tordos'))->toBeFalse();
    } finally {
        @unlink($csvPath);
    }
});

it('provisions only real section names for hybrid multi sector multi bird files', function () {
    $csvPath = storage_path('framework/testing/ProvHibrido_Corp_Constancia_de_Servicio_20260406-'.uniqid('', true).'.csv');
    file_put_contents($csvPath, implode("\n", [
        'Fecha,Entrada,Salida,Palomas,Cotorras,Tordos,Entradas,Silos,Techo,Trampa,Galpon,Exterior,Cotorras Tanque de agua,Cotorras Exterior,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,99,88,77,1,2,,,3,4,5,6,Sin novedades,Marta',
    ]));

    try {
        $result = app(ImportVisitExcelService::class)->import($csvPath, null, [
            'provision_client_and_sections' => true,
        ]);

        expect($result['persisted_rows'])->toBe(1);

        $client = Client::query()->where('name', 'Prov Hibrido Corp')->firstOrFail();

        expect($client->import_mode)->toBe(ClientImportMode::MultiSectorMultiBird);

        $locationNames = $client->locations()->orderBy('name')->pluck('name')->all();

        expect($locationNames)->toBe([
            'Entradas',
            'Exterior',
            'Galpon',
            'Silos',
            'Tanque de agua',
            'Techo',
            'Trampa',
        ])
            ->and($locationNames)->not->toContain('palomas', 'cotorras', 'tordos', 'cotorras_exterior', 'cotorras_tanque_de_agua');
    } finally {
        @unlink($csvPath);
    }
});

it('resolves composite columns using longest location suffix first when parsing bird plus section headers', function () {
    BirdType::factory()->create([
        'slug' => 'x',
        'name' => 'X',
        'common_name' => 'X',
    ]);

    BirdType::factory()->create([
        'slug' => 'xb',
        'name' => 'XB',
        'common_name' => 'XB',
    ]);

    $client = Client::query()->create([
        'name' => 'Cliente Composite Orden',
        'active' => true,
    ]);

    foreach (['A', 'BA'] as $name) {
        Location::query()->create([
            'client_id' => $client->id,
            'name' => $name,
            'active' => true,
        ]);
    }

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Palomas,Cotorras,A,BA,XBA,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,,,,,5,Sin novedades,Marta',
    ]));

    try {
        $result = app(ImportVisitExcelService::class)->import($csvPath, $client->id);

        expect($result['persisted_rows'])->toBe(1);

        $visitReport = Visit::query()
            ->with('visitReports.birdType', 'visitReports.location')
            ->firstOrFail()
            ->visitReports
            ->first(fn ($report): bool => $report->birdType->name === 'X' && $report->location->name === 'BA');

        expect($visitReport)->not->toBeNull()
            ->and($visitReport->quantity)->toBe(5);
    } finally {
        @unlink($csvPath);
    }
});

it('persists visit import log with user and file metadata', function () {
    $user = User::factory()->create();

    $client = Client::query()->create([
        'name' => 'Cliente Log',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Solo',
        'active' => true,
    ]);

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Conteo,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,1,Sin novedades,Marta',
    ]));

    try {
        app(ImportVisitExcelService::class)->import($csvPath, $client->id, [
            'user_id' => $user->id,
            'original_filename' => 'registro-visitas.csv',
            'stored_file_path' => 'imports/visit-reports/registro-visitas.csv',
        ]);

        $log = VisitImport::query()->firstOrFail();

        expect($log->client_id)->toBe($client->id)
            ->and($log->user_id)->toBe($user->id)
            ->and($log->original_filename)->toBe('registro-visitas.csv')
            ->and($log->stored_file_path)->toBe('imports/visit-reports/registro-visitas.csv')
            ->and($log->persisted_rows)->toBe(1)
            ->and($log->import_status)->toBe('success')
            ->and(Visit::query()->where('visit_import_id', $log->id)->exists())->toBeTrue();
    } finally {
        @unlink($csvPath);
    }
});

it('surfaces preview errors for invalid integer quantities', function () {
    $client = Client::query()->create([
        'name' => 'Cliente Invalido',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Jumbos',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Conversiones',
        'active' => true,
    ]);

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Jumbos,Conversiones,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,x,2,Sin novedades,Marta',
    ]));

    try {
        $preview = app(ImportVisitExcelService::class)->preview($csvPath, $client->id);

        expect($preview['invalid_rows'])->toBe(1)
            ->and($preview['errors'])->not->toBeEmpty();
    } finally {
        @unlink($csvPath);
    }
});

it('imports production hybrid header with galpon and canonical bird type ids', function () {
    $client = Client::query()->create([
        'name' => 'Cliente Produccion',
        'active' => true,
    ]);

    foreach ([
        'Entradas',
        'Silos',
        'Techo',
        'Trampa',
        'Galpón',
        'Exterior',
        'Tanque de agua',
    ] as $name) {
        Location::query()->create([
            'client_id' => $client->id,
            'name' => $name,
            'active' => true,
        ]);
    }

    $palomas = BirdType::query()->where('slug', 'palomas')->firstOrFail();
    $cotorras = BirdType::query()->where('slug', 'cotorras')->firstOrFail();

    $csvPath = createTempCsv(implode("\n", [
        'Fecha,Entrada,Salida,Palomas,Cotorras,Tordos,Entradas,Silos,Techo,Trampa,Galpón,Exterior,Cotorras Tanque de agua,Cotorras Exterior,Observaciones,Nombre de usuario',
        '2026-04-10,08:00,09:30,99,88,77,1,2,,,3,4,5,6,Sin novedades,Marta',
    ]));

    try {
        $birdTypeCountBefore = BirdType::query()->count();

        $result = app(ImportVisitExcelService::class)->import($csvPath, $client->id);

        expect($result['persisted_rows'])->toBe(1)
            ->and(BirdType::query()->count())->toBe($birdTypeCountBefore);

        $reports = Visit::query()
            ->with('visitReports.birdType', 'visitReports.location')
            ->firstOrFail()
            ->visitReports;

        expect($reports->first(
            fn ($report): bool => $report->birdType->is($palomas) && $report->location->name === 'Entradas',
        ))->not->toBeNull()
            ->and($reports->first(
                fn ($report): bool => $report->birdType->is($cotorras) && $report->location->name === 'Tanque de agua',
            ))->not->toBeNull();
    } finally {
        @unlink($csvPath);
    }
});

it('resolves canonical bird type names from aliases in expanded rows', function () {
    $client = Client::query()->create([
        'name' => 'Cliente Alias',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Predio',
        'active' => true,
    ]);

    $palomas = BirdType::query()->where('slug', 'palomas')->firstOrFail();

    $csvPath = createTempCsv(implode("\n", [
        'client_name,employee_name,employee_email,date_init,date_end,status,location_name,bird_type_name,quantity,observation,visit_observation',
        'Cliente Alias,Marta,,2026-04-10 08:00,2026-04-10 09:30,completed,Predio,Paloma doméstica,3,Sin novedades,Sin novedades',
    ]));

    try {
        $result = app(ImportVisitExcelService::class)->import($csvPath, $client->id);

        expect($result['persisted_rows'])->toBe(1);

        $visitReport = Visit::query()->with('visitReports.birdType')->firstOrFail()->visitReports->first();

        expect($visitReport->bird_type_id)->toBe($palomas->id);
    } finally {
        @unlink($csvPath);
    }
});

it('marks rows invalid when canonical import references unknown bird type', function () {
    $client = Client::query()->create([
        'name' => 'Cliente Desconocido',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Predio',
        'active' => true,
    ]);

    $csvPath = createTempCsv(implode("\n", [
        'client_name,employee_name,employee_email,date_init,date_end,status,location_name,bird_type_name,quantity,observation,visit_observation',
        'Cliente Desconocido,Marta,,2026-04-10 08:00,2026-04-10 09:30,completed,Predio,DragonesInexistentes,3,Sin novedades,Sin novedades',
    ]));

    try {
        $birdTypeCountBefore = BirdType::query()->count();

        $preview = app(ImportVisitExcelService::class)->preview($csvPath, $client->id);

        expect($preview['invalid_rows'])->toBe(1)
            ->and(BirdType::query()->count())->toBe($birdTypeCountBefore);
    } finally {
        @unlink($csvPath);
    }
});
