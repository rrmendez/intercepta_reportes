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

    /**
     * @return array{
     *     locations: int,
     *     templates: int,
     *     sections: int,
     *     reports: int,
     *     report_pdfs: int,
     *     visit_imports: int,
     *     visits: int,
     *     visit_reports: int
     * }
     */
    public function deletionImpactCounts(): array
    {
        return [
            'locations' => $this->locations()->count(),
            'templates' => $this->templates()->count(),
            'sections' => Section::query()
                ->whereIn('template_id', $this->templates()->select('id'))
                ->count(),
            'reports' => $this->reports()->count(),
            'report_pdfs' => $this->reports()->whereNotNull('generated_file_path')->count(),
            'visit_imports' => $this->visitImports()->count(),
            'visits' => $this->visits()->count(),
            'visit_reports' => VisitReport::query()
                ->where(function ($query): void {
                    $query->whereHas('visit', fn ($visitQuery) => $visitQuery->whereBelongsTo($this))
                        ->orWhereHas('location', fn ($locationQuery) => $locationQuery->whereBelongsTo($this));
                })
                ->count(),
        ];
    }
}
