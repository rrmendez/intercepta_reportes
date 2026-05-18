<?php

declare(strict_types=1);

use App\Filament\Resources\Reports\Pages\ComposeReport;
use App\Mail\ReportPdfEmail;
use App\Models\Client;
use App\Models\Report;
use App\Models\Template;
use App\Models\User;
use App\ReportStatus;
use Database\Seeders\BirdTypeSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->seed(BirdTypeSeeder::class);
});

it('persists a single report when saving draft twice for the same client and range', function (): void {
    Storage::fake('local');

    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Cliente Compose',
        'active' => true,
    ]);

    Template::query()->create([
        'client_id' => $client->id,
        'name' => 'Plantilla',
        'pdf_template' => <<<'BLADE'
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"></head><body><p>{{ $client->name }}</p>
@include('pdf.partials.report-pdf-fixed-footer', [
    'client' => $client,
    'report' => $report,
    'period_label' => $period_label,
])
</body></html>
BLADE,
        'active' => true,
    ]);

    $component = Livewire::actingAs($user)
        ->test(ComposeReport::class, [
            'client_id' => $client->id,
            'date_from' => '2026-05-01',
            'date_until' => '2026-05-31',
        ]);

    $component->callAction(TestAction::make('saveDraft'));

    $first = Report::query()->whereBelongsTo($client)->sole();
    expect($first->status)->toBe(ReportStatus::Draft)
        ->and(Report::query()->whereBelongsTo($client)->count())->toBe(1);

    $component->callAction(TestAction::make('saveDraft'));

    $second = Report::query()->whereBelongsTo($client)->sole();
    expect($second->id)->toBe($first->id)
        ->and($second->status)->toBe(ReportStatus::Draft);
});

it('queues the pdf mail when sending and marks the report as sent', function (): void {
    Mail::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Cliente Email',
        'email' => 'cliente@example.test',
        'active' => true,
    ]);

    Template::query()->create([
        'client_id' => $client->id,
        'name' => 'Plantilla',
        'pdf_template' => <<<'BLADE'
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"></head><body><p>{{ $client->name }}</p>
@include('pdf.partials.report-pdf-fixed-footer', [
    'client' => $client,
    'report' => $report,
    'period_label' => $period_label,
])
</body></html>
BLADE,
        'active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(ComposeReport::class, [
            'client_id' => $client->id,
            'date_from' => '2026-05-01',
            'date_until' => '2026-05-31',
        ])
        ->callAction(TestAction::make('sendEmail'));

    Mail::assertQueued(ReportPdfEmail::class, function (ReportPdfEmail $mail) use ($client): bool {
        return $mail->client->is($client);
    });

    $report = Report::query()->whereBelongsTo($client)->sole();
    expect($report->status)->toBe(ReportStatus::Sent)
        ->and($report->generated_file_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists((string) $report->generated_file_path))->toBeTrue()
        ->and($report->email_sent_at)->not->toBeNull();
});

it('syncs the form range from the compose visits table event', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Cliente Rango',
        'active' => true,
    ]);

    Template::query()->create([
        'client_id' => $client->id,
        'name' => 'Plantilla',
        'pdf_template' => <<<'BLADE'
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"></head><body><p>{{ $client->name }}</p>
@include('pdf.partials.report-pdf-fixed-footer', [
    'client' => $client,
    'report' => $report,
    'period_label' => $period_label,
])
</body></html>
BLADE,
        'active' => true,
    ]);

    $component = Livewire::actingAs($user)
        ->test(ComposeReport::class, [
            'client_id' => $client->id,
            'date_from' => '2026-05-01',
            'date_until' => '2026-05-31',
        ]);

    $component->dispatch('compose-report-range-changed', dateFrom: '2026-05-10', dateUntil: '2026-05-20');

    expect($component->get('data.date_from'))->toBe('2026-05-10')
        ->and($component->get('data.date_until'))->toBe('2026-05-20');
});

it('stores spreadsheet filters from the visits preview table event', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Cliente Filtros',
        'active' => true,
    ]);

    Template::query()->create([
        'client_id' => $client->id,
        'name' => 'Plantilla',
        'pdf_template' => <<<'BLADE'
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"></head><body><p>{{ $client->name }}</p>
@include('pdf.partials.report-pdf-fixed-footer', [
    'client' => $client,
    'report' => $report,
    'period_label' => $period_label,
])
</body></html>
BLADE,
        'active' => true,
    ]);

    $component = Livewire::actingAs($user)
        ->test(ComposeReport::class, [
            'client_id' => $client->id,
            'date_from' => '2026-05-01',
            'date_until' => '2026-05-31',
        ]);

    $filters = [
        'spreadsheet' => [
            'mode' => 'custom_range',
            'date_from' => '2026-05-10',
            'date_until' => '2026-05-12',
        ],
    ];

    $component->dispatch('compose-report-spreadsheet-filters-changed', filters: $filters);

    expect($component->get('previewSpreadsheetFilters'))->toBe($filters)
        ->and($component->get('reportPreviewRevision'))->toBe(1);
});

it('refreshes the report preview when visit table data changes', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Cliente Refresh',
        'active' => true,
    ]);

    Template::query()->create([
        'client_id' => $client->id,
        'name' => 'Plantilla',
        'pdf_template' => <<<'BLADE'
<!DOCTYPE html>
<html lang="es"><body>
<p>{{ $client->name }}</p>
@include('pdf.partials.report-objective-methodology-page')
</body></html>
BLADE,
        'active' => true,
    ]);

    $component = Livewire::actingAs($user)
        ->test(ComposeReport::class, [
            'client_id' => $client->id,
            'date_from' => '2026-05-01',
            'date_until' => '2026-05-31',
        ]);

    expect($component->get('reportPreviewRevision'))->toBe(0);

    $component->dispatch('report-period-visits-changed');

    expect($component->get('reportPreviewRevision'))->toBe(1);
});

it('expands editable report content pages in the blade code editor', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Cliente Paginas',
        'active' => true,
    ]);

    Template::query()->create([
        'client_id' => $client->id,
        'name' => 'Plantilla',
        'pdf_template' => <<<'BLADE'
<!DOCTYPE html>
<html lang="es"><body>
@include('pdf.partials.report-cover-page')
@include('pdf.partials.report-initial-situation-page')
@include('pdf.partials.report-objective-methodology-page')
@include('pdf.partials.report-pdf-fixed-footer', [
    'client' => $client,
    'report' => $report,
    'period_label' => $period_label,
])
</body></html>
BLADE,
        'active' => true,
    ]);

    $component = Livewire::actingAs($user)
        ->test(ComposeReport::class, [
            'client_id' => $client->id,
            'date_from' => '2026-05-01',
            'date_until' => '2026-05-31',
        ]);

    $source = (string) $component->get('data.pdf_template');

    expect($source)->toContain('Situación inicial del predio')
        ->and($source)->toContain('Objetivo y metodología')
        ->and($source)->toContain('Registro del control de fauna')
        ->and($source)->toContain('report-initial-situation-page__table')
        ->and($source)->toContain('report-objective-methodology-page__table')
        ->and($source)->not->toContain("@include('pdf.partials.report-initial-situation-page')")
        ->and($source)->not->toContain("@include('pdf.partials.report-objective-methodology-page')");
});

it('keeps the compose html preview on a white pdf-like background', function (): void {
    $source = (string) file_get_contents(app_path('Filament/Resources/Reports/Pages/ComposeReport.php'));

    expect($source)->toContain('bg-white p-4 text-gray-950')
        ->and($source)->not->toContain('dark:bg-gray-900');
});

it('opens the pdf preview in a new tab and serves the cached pdf from the preview route', function (): void {
    Storage::fake('local');

    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Cliente Vista PDF',
        'active' => true,
    ]);

    Template::query()->create([
        'client_id' => $client->id,
        'name' => 'Plantilla',
        'pdf_template' => <<<'BLADE'
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"></head><body><p>{{ $client->name }}</p>
@include('pdf.partials.report-pdf-fixed-footer', [
    'client' => $client,
    'report' => $report,
    'period_label' => $period_label,
])
</body></html>
BLADE,
        'active' => true,
    ]);

    $component = Livewire::actingAs($user)
        ->test(ComposeReport::class, [
            'client_id' => $client->id,
            'date_from' => '2026-05-01',
            'date_until' => '2026-05-31',
        ]);

    $previewUrl = $component->invade()->composePdfPreviewUrl();
    expect($previewUrl)->toContain('compose-preview');

    $pdfResponse = $this->actingAs($user)->get($previewUrl);

    expect($pdfResponse->headers->get('Content-Type'))->toBe('application/pdf')
        ->and(strlen((string) $pdfResponse->getContent()))->toBeGreaterThan(100)
        ->and(str_starts_with((string) $pdfResponse->getContent(), '%PDF'))->toBeTrue();

    $component->callAction(TestAction::make('visualizePdf'));

    expect($component->effects['xjs'] ?? [])->not->toBeEmpty();
});
