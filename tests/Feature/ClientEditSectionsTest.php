<?php

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('detects the internal section mirrored from the client name', function (): void {
    $client = Client::query()->create([
        'name' => 'Acme',
        'active' => true,
    ]);

    expect(ClientResource::isInternalClientSection('Acme', $client))->toBeTrue()
        ->and(ClientResource::isInternalClientSection(['name' => 'Acme'], $client))->toBeTrue()
        ->and(ClientResource::isInternalClientSection('Deposito', $client))->toBeFalse()
        ->and(ClientResource::isInternalClientSection(['name' => 'Deposito'], $client))->toBeFalse();
});

it('hides repeater delete when the client has a single user-managed section', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Acme',
        'active' => true,
    ]);

    $client->locations()->create([
        'name' => 'Acme',
        'active' => true,
    ]);

    $namedSection = $client->locations()->create([
        'name' => 'Deposito',
        'active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(ListClients::class)
        ->callAction(TestAction::make('edit')->table($client))
        ->assertFormComponentActionHidden('locations', 'delete', [
            'item' => "record-{$namedSection->id}",
        ]);
});

it('never allows deleting the internal section that mirrors the client name', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $client = Client::query()->create([
        'name' => 'Acme',
        'active' => true,
    ]);

    $internalSection = $client->locations()->create([
        'name' => 'Acme',
        'active' => true,
    ]);

    $client->locations()->create([
        'name' => 'Deposito',
        'active' => true,
    ]);

    $client->locations()->create([
        'name' => 'Taller',
        'active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(ListClients::class)
        ->callAction(TestAction::make('edit')->table($client))
        ->assertFormComponentActionHidden('locations', 'delete', [
            'item' => "record-{$internalSection->id}",
        ]);
});
