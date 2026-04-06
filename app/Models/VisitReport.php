<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitReport extends Model
{
    protected $fillable = [
        'visit_id',
        'location_id',
        'bird_type_id',
        'quantity',
        'observation',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function birdType(): BelongsTo
    {
        return $this->belongsTo(BirdType::class);
    }
}
