<?php

namespace App\Models;

use App\ClientImportMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name',
        'email',
        'address',
        'active',
        'notes',
        'import_mode',
        'default_location_name',
        'default_bird_type_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'bool',
            'import_mode' => ClientImportMode::class,
        ];
    }

    public function defaultBirdType(): BelongsTo
    {
        return $this->belongsTo(BirdType::class, 'default_bird_type_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
