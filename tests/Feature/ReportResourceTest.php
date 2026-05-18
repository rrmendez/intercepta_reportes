<?php

use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Client;
use App\Models\Report;
use App\Models\User;
use App\ReportStatus;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('lists reports with compose link, author column, email sent column, and filters', function (): void {
    Storage::fake('local');

    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Cliente Reporte',
        'active' => true,
    ]);

    Storage::disk('local')->put('reports/cliente-reporte.pdf', 'PDF');

    $report = Report::query()->create([
        'client_id' => $client->id,
        'generated_by_user_id' => $user->id,
        'month' => 5,
        'year' => 2026,
        'date_from' => '2026-05-01',
        'date_until' => '2026-05-31',
        'generated_file_path' => 'reports/cliente-reporte.pdf',
        'status' => ReportStatus::Generated,
        'generated_at' => '2026-05-12 10:00:00',
        'email_sent_at' => '2026-05-12 11:00:00',
        'created_at' => '2026-05-12 09:30:00',
        'updated_at' => '2026-05-12 10:00:00',
    ]);

    Livewire::actingAs($user)
        ->test(ListReports::class)
        ->assertCanSeeTableRecords([$report])
        ->assertTableColumnExists('client.name')
        ->assertTableColumnExists('generatedBy.name')
        ->assertTableColumnExists('created_at')
        ->assertTableColumnExists('date_from')
        ->assertTableColumnExists('date_until')
        ->assertTableColumnExists('generated_at')
        ->assertTableColumnExists('email_sent_at')
        ->assertTableColumnExists('pdf_download')
        ->assertTableFilterExists('created_between')
        ->assertTableFilterExists('period_date_from')
        ->assertTableActionExists('compose')
        ->assertTableActionExists('downloadPdf')
        ->assertTableActionVisible('downloadPdf', $report);

    expect(ReportResource::canCreate())->toBeTrue()
        ->and(ReportResource::getNavigationGroup())->toBe('General')
        ->and(ReportResource::canGloballySearch())->toBeFalse();
});

it('serves the report pdf download route for authorized users', function (): void {
    Storage::fake('local');

    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Cliente PDF',
        'active' => true,
    ]);

    Storage::disk('local')->put('reports/test.pdf', '%PDF-1.4 fake');

    $report = Report::query()->create([
        'client_id' => $client->id,
        'generated_by_user_id' => $user->id,
        'month' => 5,
        'year' => 2026,
        'date_from' => '2026-05-01',
        'date_until' => '2026-05-31',
        'generated_file_path' => 'reports/test.pdf',
        'status' => ReportStatus::Generated,
    ]);

    $url = Filament::getPanel('admin')->route('reports.download-pdf', ['report' => $report]);

    $this->actingAs($user)
        ->get($url)
        ->assertOk();
});
