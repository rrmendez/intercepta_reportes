<?php

namespace App\Models;

use App\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'client_id',
        'template_id',
        'month',
        'year',
        'generated_file_path',
        'status',
        'generated_at',
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
            'generated_at' => 'datetime',
            'status' => ReportStatus::class,
            'data' => 'array',
        ];
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
