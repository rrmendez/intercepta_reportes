<?php

use App\Filament\Resources\BirdTypes\BirdTypeResource;
use App\Filament\Resources\BirdTypes\Pages\ListBirdTypes;
use App\Models\BirdType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('registers bird types under operaciones with spanish labels', function (): void {
    expect(BirdTypeResource::shouldRegisterNavigation())->toBeTrue()
        ->and(BirdTypeResource::getNavigationGroup())->toBe('Operaciones')
        ->and(BirdTypeResource::getNavigationLabel())->toBe('Tipos de ave')
        ->and(BirdTypeResource::getNavigationSort())->toBe(13)
        ->and(BirdTypeResource::getModelLabel())->toBe('tipo de ave')
        ->and(BirdTypeResource::getPluralModelLabel())->toBe('tipos de ave');
});

it('lists bird types and creates and edits records in modals', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $birdType = BirdType::factory()->create([
        'slug' => 'gaviotas',
        'name' => 'Gaviotas',
        'common_name' => 'Gaviota',
        'common_name_plural' => 'Gaviotas',
        'scientific_name' => 'Larus dominicanus',
    ]);

    Livewire::actingAs($user)
        ->test(ListBirdTypes::class)
        ->assertCanSeeTableRecords([$birdType])
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('common_name')
        ->assertTableColumnExists('scientific_name')
        ->assertTableColumnExists('active')
        ->assertActionExists('create')
        ->callAction(TestAction::make('create'), [
            'name' => 'Halcones',
            'slug' => 'halcones',
            'common_name' => 'Halcon',
            'common_name_plural' => 'Halcones',
            'scientific_name' => 'Falco sp.',
            'active' => true,
        ])
        ->assertNotified();

    expect(BirdType::query()->where('slug', 'halcones')->exists())->toBeTrue();

    Livewire::actingAs($user)
        ->test(ListBirdTypes::class)
        ->callAction(TestAction::make('edit')->table($birdType), [
            'name' => 'Gaviotas marinas',
            'slug' => 'gaviotas-marinas',
            'common_name' => 'Gaviota marina',
            'common_name_plural' => 'Gaviotas marinas',
            'scientific_name' => 'Descripcion actualizada',
            'aliases' => [
                ['alias' => 'Gaviota'],
            ],
            'active' => false,
        ])
        ->assertNotified();

    $birdType->refresh();

    expect($birdType->name)->toBe('Gaviotas marinas')
        ->and($birdType->common_name)->toBe('Gaviota marina')
        ->and($birdType->scientific_name)->toBe('Descripcion actualizada')
        ->and($birdType->active)->toBeFalse()
        ->and($birdType->aliases)->toHaveCount(1)
        ->and($birdType->aliases->first()->alias)->toBe('Gaviota')
        ->and($birdType->aliases->first()->token)->toBe('gaviota');
});
