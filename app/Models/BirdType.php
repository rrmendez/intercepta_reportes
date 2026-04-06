<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BirdType extends Model
{
    protected $fillable = [
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

    public function visitReports(): HasMany
    {
        return $this->hasMany(VisitReport::class);
    }
}
