<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected static function booted(): void
    {
        static::deleted(function (Location $location): void {
            $client = $location->client()->first();

            if ($client === null) {
                return;
            }

            if ($client->locations()->count() === 0) {
                $client->locations()->create([
                    'name' => $client->name,
                    'active' => true,
                ]);
            }
        });
    }

    protected $fillable = [
        'client_id',
        'name',
        'description',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'bool',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Excluye la ubicación interna cuyo nombre es el mismo que el del cliente.
     */
    public function scopeExcludingInternalDefault(Builder $query): void
    {
        $query->whereRaw('locations.name != (SELECT c.name FROM clients c WHERE c.id = locations.client_id)');
    }

    public function visitReports(): HasMany
    {
        return $this->hasMany(VisitReport::class);
    }
}
