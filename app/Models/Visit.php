<?php

namespace App\Models;

use App\Services\VisitObservations\VisitObservationMetricsExtractor;
use App\VisitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visit extends Model
{
    public const OBSERVATION_METRIC_FIELDS = ['nests_removed', 'captures_falconry', 'captures_trap'];

    protected $fillable = [
        'client_id',
        'visit_import_id',
        'employee_id',
        'date_init',
        'date_end',
        'observation',
        'nests_removed',
        'captures_falconry',
        'captures_trap',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_init' => 'datetime',
            'date_end' => 'datetime',
            'nests_removed' => 'integer',
            'captures_falconry' => 'integer',
            'captures_trap' => 'integer',
            'status' => VisitStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Visit $visit): void {
            if ($visit->exists) {
                if (! $visit->isDirty('observation') || $visit->isDirty(self::OBSERVATION_METRIC_FIELDS)) {
                    return;
                }
            } else {
                $hasManualMetrics = collect(self::OBSERVATION_METRIC_FIELDS)
                    ->contains(fn (string $field): bool => $visit->{$field} !== null);

                if ($hasManualMetrics) {
                    return;
                }
            }

            $metrics = app(VisitObservationMetricsExtractor::class)->extract($visit->observation);

            $visit->nests_removed = $metrics->nestsRemoved;
            $visit->captures_falconry = $metrics->capturesFalconry;
            $visit->captures_trap = $metrics->capturesTrap;
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function visitImport(): BelongsTo
    {
        return $this->belongsTo(VisitImport::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function visitReports(): HasMany
    {
        return $this->hasMany(VisitReport::class);
    }
}
