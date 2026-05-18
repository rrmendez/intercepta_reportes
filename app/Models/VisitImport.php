<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitImport extends Model
{
    protected $fillable = [
        'client_id',
        'user_id',
        'original_filename',
        'stored_file_path',
        'summary_message',
        'total_rows',
        'persisted_rows',
        'skipped_rows',
        'invalid_rows',
        'import_status',
        'errors',
        'warnings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'warnings' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
