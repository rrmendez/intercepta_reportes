<?php

use App\Filament\Resources\VisitImports\Pages\ViewVisitImport;
use App\Models\Client;
use App\Models\User;
use App\Models\VisitImport;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('shows visit import details with stored errors', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Urusal',
        'active' => true,
    ]);

    $visitImport = VisitImport::query()->create([
        'client_id' => $client->id,
        'user_id' => $user->id,
        'original_filename' => 'Urusal_Constancia_de_Servicio_20260406.xlsx',
        'stored_file_path' => 'imports/visit-reports/Urusal_Constancia_de_Servicio_20260406.xlsx',
        'summary_message' => '',
        'total_rows' => 14,
        'persisted_rows' => 0,
        'skipped_rows' => 14,
        'invalid_rows' => 14,
        'import_status' => 'failed',
        'errors' => [
            'Fila 2: El campo cantidad debe ser un numero entero.',
            'Fila 3: El campo cantidad debe ser un numero entero.',
        ],
        'warnings' => [],
    ]);

    Livewire::actingAs($user)
        ->test(ViewVisitImport::class, ['record' => $visitImport->id])
        ->assertSuccessful()
        ->assertSee('Urusal_Constancia_de_Servicio_20260406.xlsx')
        ->assertSee('Fila 2: El campo cantidad debe ser un numero entero.', false);
});
