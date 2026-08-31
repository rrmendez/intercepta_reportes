<?php

namespace App\Models;

use App\VisitCountStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitReport extends Model
{
    protected $fillable = [
        'visit_id',
        'location_id',
        'bird_type_id',
        'quantity',
        'count_status',
        'observation',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'count_status' => VisitCountStatus::class,
        ];
    }

    /**
     * Mantiene la invariante `not_performed` <=> `quantity` nula, sin importar
     * por que camino se escriba el conteo (importacion, planilla o formulario).
     * Escribir una cantidad nueva reabre el conteo; marcarlo como no realizado
     * sin tocar la cantidad la anula.
     */
    protected static function booted(): void
    {
        static::saving(function (VisitReport $report): void {
            if ($report->isDirty('quantity') && $report->quantity !== null) {
                $report->count_status = VisitCountStatus::Counted;

                return;
            }

            if ($report->count_status === VisitCountStatus::NotPerformed) {
                $report->quantity = null;

                return;
            }

            if ($report->quantity === null) {
                $report->count_status = VisitCountStatus::NotPerformed;
            }
        });
    }

    /**
     * Conteos con un valor real registrado, incluyendo ceros.
     *
     * @param  Builder<VisitReport>  $query
     */
    public function scopeCounted(Builder $query): void
    {
        $query->where('count_status', VisitCountStatus::Counted);
    }

    /**
     * Conteos que no pudieron realizarse (local cerrado, sin acceso).
     *
     * @param  Builder<VisitReport>  $query
     */
    public function scopeNotPerformed(Builder $query): void
    {
        $query->where('count_status', VisitCountStatus::NotPerformed);
    }

    public function wasCounted(): bool
    {
        return $this->count_status !== VisitCountStatus::NotPerformed;
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
