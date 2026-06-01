<?php

namespace App\Models;

use App\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Report extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (Report $report): void {
            if (filled($report->generated_file_path)) {
                Storage::disk('local')->delete($report->generated_file_path);
            }
        });
    }

    protected $fillable = [
        'client_id',
        'generated_by_user_id',
        'template_id',
        'month',
        'year',
        'date_from',
        'date_until',
        'generated_file_path',
        'status',
        'generated_at',
        'email_sent_at',
        'data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'date_from' => 'date',
            'date_until' => 'date',
            'generated_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'status' => ReportStatus::class,
            'data' => 'array',
        ];
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function getGeneratedFilenameAttribute(): string
    {
        if (filled($this->generated_file_path)) {
            return basename((string) $this->generated_file_path);
        }

        return 'Report #'.(string) $this->getKey();
    }
}
