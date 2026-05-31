<?php

namespace App\Models;

use Database\Factories\BirdTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BirdType extends Model
{
    /** @use HasFactory<BirdTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'common_name',
        'common_name_plural',
        'scientific_name',
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

    public function visitReports(): HasMany
    {
        return $this->hasMany(VisitReport::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(BirdTypeAlias::class);
    }

    public function labelForImport(): string
    {
        return trim($this->name);
    }

    public function labelForPdf(int $quantity): string
    {
        if ($quantity === 1) {
            return trim($this->common_name);
        }

        $plural = trim((string) ($this->common_name_plural ?? ''));

        return $plural !== '' ? $plural : trim($this->common_name);
    }

    public function labelWithScientific(): string
    {
        $commonName = trim($this->common_name);
        $scientificName = trim((string) ($this->scientific_name ?? ''));

        if ($scientificName !== '') {
            return "{$commonName} ({$scientificName})";
        }

        return $commonName;
    }
}
