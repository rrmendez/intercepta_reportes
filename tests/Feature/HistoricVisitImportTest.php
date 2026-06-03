<?php

use App\ClientImportMode;
use App\Jobs\ProcessHistoricVisitImportsJob;
use App\Models\BirdType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Visit;
use App\Models\VisitImport;
use App\Models\VisitReport;
use App\Services\HistoricVisitImport\HistoricVisitImportService;
use App\Services\HistoricVisitImport\HistoricVisitSpreadsheetReader;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
});

it('reads historic data from the third spreadsheet sheet', function () {
    $path = createTempHistoricSpreadsheet([
        [45264, 8, 20],
        [45265, 7, 15],
    ]);

    try {
        $result = app(HistoricVisitSpreadsheetReader::class)->read($path);

        expect($result['section_column_indices'])->toBe([2, 3])
            ->and($result['rows'])->toHaveCount(2)
            ->and($result['rows'][0]['quantities'])->toBe([2 => 8, 3 => 20]);
    } finally {
        @unlink($path);
    }
});

it('maps spreadsheet columns to client sections ordered by creation', function () {
    $client = Client::query()->create([
        'name' => 'Conaprole',
        'active' => true,
        'import_mode' => ClientImportMode::MultiSectorSingleBird,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Planta Norte',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Planta Sur',
        'active' => true,
    ]);

    $directory = createTempHistoricDirectory([
        'Conaprole.xls' => [
            [45264, 8, 20],
        ],
    ]);

    try {
        $summary = app(HistoricVisitImportService::class)->processDirectory($directory, dryRun: true);

        expect($summary['files'][0]['sections'])->toBe(['Planta Norte', 'Planta Sur'])
            ->and($summary['total_visit_reports'])->toBe(2);
    } finally {
        deleteDirectoryRecursive($directory);
    }
});

it('dry run previews historic visits with fake metadata', function () {
    $client = Client::query()->create([
        'name' => 'Conaprole',
        'active' => true,
        'import_mode' => ClientImportMode::MultiSectorSingleBird,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Seccion 1',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Seccion 2',
        'active' => true,
    ]);

    $directory = createTempHistoricDirectory([
        'Conaprole.xls' => [
            [45264, 8, 20],
            [45265, 7, 15],
        ],
    ]);

    try {
        $summary = app(HistoricVisitImportService::class)->processDirectory($directory, dryRun: true);

        expect($summary['dry_run'])->toBeTrue()
            ->and($summary['total_files'])->toBe(1)
            ->and($summary['successful_files'])->toBe(1)
            ->and($summary['failed_files'])->toBe(0)
            ->and($summary['total_valid_rows'])->toBe(2)
            ->and($summary['total_visit_reports'])->toBe(4)
            ->and($summary['files'][0]['client_resolution'])->toBe('existing_client');

        expect(Visit::query()->count())->toBe(0);
    } finally {
        deleteDirectoryRecursive($directory);
    }
});

it('imports historic visits when execute mode is used', function () {
    $client = Client::query()->create([
        'name' => 'APM',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'APM',
        'active' => true,
    ]);

    $directory = createTempHistoricDirectory([
        'APM.xls' => [
            [41219, 45],
            [41221, 40],
        ],
    ]);

    try {
        $summary = app(HistoricVisitImportService::class)->processDirectory($directory, dryRun: false);

        expect($summary['successful_files'])->toBe(1)
            ->and($summary['total_persisted_rows'])->toBe(2)
            ->and($summary['total_visit_reports'])->toBe(2);

        $visit = Visit::query()->with('visitReports')->firstOrFail();

        expect($visit->observation)->toBe(HistoricVisitImportService::HISTORIC_OBSERVATION)
            ->and($visit->visitReports)->toHaveCount(1)
            ->and($visit->visitReports->firstOrFail()->quantity)->toBe(45);

        expect(VisitImport::query()->count())->toBe(1)
            ->and(VisitImport::query()->value('stored_file_path'))->toBe(HistoricVisitImportService::HISTORIC_IMPORT_SOURCE);
    } finally {
        deleteDirectoryRecursive($directory);
    }
});

it('deletes previous historic imports before re-importing', function () {
    $client = Client::query()->create([
        'name' => 'APM',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'APM',
        'active' => true,
    ]);

    $directory = createTempHistoricDirectory([
        'APM.xls' => [
            [41219, 45],
        ],
    ]);

    try {
        app(HistoricVisitImportService::class)->processDirectory($directory, dryRun: false);
        app(HistoricVisitImportService::class)->processDirectory($directory, dryRun: false);

        expect(Visit::query()->count())->toBe(1)
            ->and(VisitImport::query()->count())->toBe(1)
            ->and(VisitReport::query()->count())->toBe(1);
    } finally {
        deleteDirectoryRecursive($directory);
    }
});

it('skips rows when the client already has a visit on that date', function () {
    $client = Client::query()->create([
        'name' => 'APM',
        'active' => true,
    ]);

    $location = Location::query()->create([
        'client_id' => $client->id,
        'name' => 'APM',
        'active' => true,
    ]);

    $existingDate = ExcelDate::excelToDateTimeObject(41219)->format('Y-m-d H:i:s');

    $existingVisit = Visit::query()->create([
        'client_id' => $client->id,
        'employee_id' => Employee::query()->create(['name' => 'Operador', 'active' => true])->id,
        'date_init' => $existingDate,
        'date_end' => $existingDate,
        'status' => 'completed',
        'observation' => 'Visita real',
    ]);

    VisitReport::query()->create([
        'visit_id' => $existingVisit->id,
        'location_id' => $location->id,
        'bird_type_id' => BirdType::query()->firstOrFail()->id,
        'quantity' => 10,
        'observation' => 'Visita real',
    ]);

    $directory = createTempHistoricDirectory([
        'APM.xls' => [
            [41219, 45],
            [41221, 40],
        ],
    ]);

    try {
        $summary = app(HistoricVisitImportService::class)->processDirectory($directory, dryRun: false);

        expect($summary['total_persisted_rows'])->toBe(1)
            ->and($summary['total_skipped_existing_dates'])->toBe(1)
            ->and(Visit::query()->count())->toBe(2);
    } finally {
        deleteDirectoryRecursive($directory);
    }
});

it('fails when the client is not registered', function () {
    $directory = createTempHistoricDirectory([
        'APM.xls' => [
            [41219, 45],
        ],
    ]);

    try {
        $summary = app(HistoricVisitImportService::class)->processDirectory($directory, dryRun: true);

        expect($summary['failed_files'])->toBe(1)
            ->and($summary['files'][0]['error'])->toContain('No existe un cliente registrado');
    } finally {
        deleteDirectoryRecursive($directory);
    }
});

it('does not create new sections during import', function () {
    $client = Client::query()->create([
        'name' => 'APM',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'APM',
        'active' => true,
    ]);

    $directory = createTempHistoricDirectory([
        'APM.xls' => [
            [41219, 45],
        ],
    ]);

    try {
        app(HistoricVisitImportService::class)->processDirectory($directory, dryRun: false);

        expect(Location::query()->where('client_id', $client->id)->count())->toBe(1);
    } finally {
        deleteDirectoryRecursive($directory);
    }
});

it('dispatches the historic import job with dry run by default', function () {
    Queue::fake();

    ProcessHistoricVisitImportsJob::dispatch(dryRun: true, userId: null, directory: base_path('historico'));

    Queue::assertPushed(ProcessHistoricVisitImportsJob::class, function (ProcessHistoricVisitImportsJob $job): bool {
        return $job->dryRun === true
            && $job->directory === base_path('historico');
    });
});

it('maps spreadsheet bird columns for single sector multi bird clients', function () {
    $client = Client::query()->create([
        'name' => 'Alur',
        'active' => true,
        'import_mode' => ClientImportMode::SingleSectorMultiBird,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Alur',
        'active' => true,
    ]);

    $directory = createTempHistoricDirectory([
        'Alur.xls' => [
            ['Fecha', 'Paloma', 'Cotorra'],
            [45264, 8, 20],
            [45265, 7, 15],
        ],
    ]);

    try {
        $summary = app(HistoricVisitImportService::class)->processDirectory($directory, dryRun: true);

        expect($summary['successful_files'])->toBe(1)
            ->and($summary['total_visit_reports'])->toBe(4)
            ->and($summary['files'][0]['column_mappings'])->toHaveCount(2)
            ->and(collect($summary['files'][0]['column_mappings'])->pluck('bird_type_name')->all())
            ->toContain('Palomas', 'Cotorras');
    } finally {
        deleteDirectoryRecursive($directory);
    }
});

it('maps composite bird and section headers for multi sector multi bird clients', function () {
    $client = Client::query()->create([
        'name' => 'Monte Paz',
        'active' => true,
        'import_mode' => ClientImportMode::MultiSectorMultiBird,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Interior',
        'active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'name' => 'Exterior',
        'active' => true,
    ]);

    $directory = createTempHistoricDirectory([
        'Monte Paz.xls' => [
            ['fecha', 'Palomas interior', 'Palomas exterior'],
            [40827, 65, 33],
        ],
    ]);

    try {
        $summary = app(HistoricVisitImportService::class)->processDirectory($directory, dryRun: true);

        expect($summary['successful_files'])->toBe(1)
            ->and($summary['total_visit_reports'])->toBe(2)
            ->and(collect($summary['files'][0]['column_mappings'])->pluck('location_name')->all())
            ->toBe(['Interior', 'Exterior']);
    } finally {
        deleteDirectoryRecursive($directory);
    }
});

it('extracts client names from prefixed historic filenames', function () {
    $service = app(HistoricVisitImportService::class);

    expect($service->extractClientDisplayNameFromFilename('Abril 2026 - ANCAP PORTLAND.xls'))
        ->toBe('Ancap Portland')
        ->and($service->extractClientDisplayNameFromFilename(' Conaprole.xls'))
        ->toBe('Conaprole');
});

/**
 * @param  array<int, array<int, int|float|string>>  $historicRows
 */
function createTempHistoricSpreadsheet(array $historicRows): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->setTitle('Informe');
    $spreadsheet->createSheet()->setTitle('Hoja2');

    $historicSheet = $spreadsheet->createSheet();
    $historicSheet->setTitle('Hoja3');

    foreach ($historicRows as $rowIndex => $row) {
        foreach ($row as $columnIndex => $value) {
            $cellAddress = Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 1);
            $historicSheet->setCellValue($cellAddress, $value);
        }
    }

    $path = sys_get_temp_dir().'/historic-'.uniqid('', true).'.xls';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

/**
 * @param  array<string, array<int, array<int, int|float|string>>>  $files
 */
function createTempHistoricDirectory(array $files): string
{
    $directory = sys_get_temp_dir().'/historic-dir-'.uniqid('', true);
    mkdir($directory);

    foreach ($files as $filename => $rows) {
        copy(createTempHistoricSpreadsheet($rows), $directory.'/'.$filename);
    }

    return $directory;
}

function deleteDirectoryRecursive(string $directory): void
{
    foreach (glob($directory.'/*') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }

    @rmdir($directory);
}
