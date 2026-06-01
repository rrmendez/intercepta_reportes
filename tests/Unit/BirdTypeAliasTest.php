<?php

use App\Models\BirdType;
use App\Models\BirdTypeAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('auto generates token from alias when token is null', function (): void {
    $birdType = BirdType::factory()->create();

    $alias = BirdTypeAlias::query()->create([
        'bird_type_id' => $birdType->id,
        'alias' => 'Gorrion',
    ]);

    expect($alias->token)->toBe('gorrion');
});

it('preserves an explicitly provided token', function (): void {
    $birdType = BirdType::factory()->create();

    $alias = BirdTypeAlias::query()->create([
        'bird_type_id' => $birdType->id,
        'alias' => 'Gorrion',
        'token' => 'custom-token',
    ]);

    expect($alias->token)->toBe('custom-token');
});
