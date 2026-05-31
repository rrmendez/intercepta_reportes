<?php

declare(strict_types=1);

use App\Models\BirdType;
use App\Services\BirdTypes\BirdTypeResolver;
use Database\Seeders\BirdTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BirdTypeSeeder::class);
    $this->resolver = app(BirdTypeResolver::class);
});

it('resolves import labels and aliases to the same canonical bird type', function (): void {
    $palomas = BirdType::query()->where('slug', 'palomas')->firstOrFail();

    expect($this->resolver->resolve('Palomas')?->is($palomas))->toBeTrue()
        ->and($this->resolver->resolve('Paloma doméstica')?->is($palomas))->toBeTrue()
        ->and($this->resolver->resolve('Paloma')?->is($palomas))->toBeTrue()
        ->and($this->resolver->resolve('Cotorra')?->slug)->toBe('cotorras')
        ->and($this->resolver->resolve('Tordo')?->slug)->toBe('tordos');
});

it('matches composite column prefixes using import labels', function (): void {
    $match = $this->resolver->matchCompositePrefix('Cotorras Tanque de agua');

    expect($match)->not->toBeNull()
        ->and($match[0]->slug)->toBe('cotorras')
        ->and($match[1])->toBe('Tanque de agua');
});

it('does not treat an exact bird label header as a composite prefix', function (): void {
    expect($this->resolver->matchCompositePrefix('Paloma doméstica'))->toBeNull()
        ->and($this->resolver->matchCompositePrefix('Cotorras'))->toBeNull()
        ->and($this->resolver->matchCompositePrefix('cotorras'))->toBeNull();
});

it('throws when bird type is unknown', function (): void {
    expect(fn () => $this->resolver->resolveOrFail('DragonesInexistentes'))
        ->toThrow(ValidationException::class);
});

it('does not create bird types when resolving', function (): void {
    $this->resolver->resolve('Paloma doméstica');

    expect(BirdType::query()->count())->toBe(3);
});

it('returns default palomas bird type', function (): void {
    expect($this->resolver->default()->slug)->toBe('palomas');
});
