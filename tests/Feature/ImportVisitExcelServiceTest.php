<?php

use App\Models\Client;
use App\Models\Location;
use App\Models\Visit;
use App\Services\ImportVisitExcelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

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

it('fails compact import when location columns do not match client locations', function () {
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
        app(ImportVisitExcelService::class)->import($csvPath, $client->id);

        $this->fail('Expected validation exception.');
    } catch (ValidationException $exception) {
        $firstError = collect($exception->errors())->flatten()->first();

        expect($firstError)->toContain('must match client locations');
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

        expect($firstError)->toContain('requires exactly one active location');
    } finally {
        @unlink($csvPath);
    }
});

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
