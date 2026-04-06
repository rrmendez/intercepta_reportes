<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'birthday',
        'document_number',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'active' => 'bool',
        ];
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
