<?php

namespace App\Models;

use App\Services\BirdTypes\BirdTypeTokenNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BirdTypeAlias extends Model
{
    protected $fillable = [
        'bird_type_id',
        'alias',
        'token',
    ];

    public function birdType(): BelongsTo
    {
        return $this->belongsTo(BirdType::class);
    }

    protected static function booted(): void
    {
        static::saving(function (BirdTypeAlias $alias): void {
            if ($alias->token === '' && filled($alias->alias)) {
                $alias->token = app(BirdTypeTokenNormalizer::class)
                    ->normalize($alias->alias);
            }
        });
    }
}
