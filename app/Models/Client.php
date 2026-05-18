<?php

namespace App\Models;

use App\ClientImportMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (Client $client): void {
            VisitReport::query()
                ->whereHas('visit', fn ($query) => $query->whereBelongsTo($client))
                ->orWhereHas('location', fn ($query) => $query->whereBelongsTo($client))
                ->delete();

            $client->visitImports()->delete();
        });

        static::updating(function (Client $client): void {
            if (! $client->isDirty('name')) {
                return;
            }

            $previousName = $client->getOriginal('name');

            if (! is_string($previousName) || $previousName === '') {
                return;
            }

            $client->locations()->where('name', $previousName)->update(['name' => $client->name]);
        });
    }

    protected $fillable = [
        'name',
        'email',
        'address',
        'active',
        'notes',
        'import_mode',
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

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Secciones visibles en administración (excluye la sección interna cuyo nombre coincide con la empresa).
     */
    public function namedLocations(): HasMany
    {
        return $this->hasMany(Location::class)
            ->whereRaw('locations.name != (SELECT c.name FROM clients c WHERE c.id = locations.client_id)');
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

    public function visitImports(): HasMany
    {
        return $this->hasMany(VisitImport::class);
    }
}
