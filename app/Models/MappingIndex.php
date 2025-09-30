<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MappingIndex extends Model
{
    use HasFactory;

    protected $fillable = [
        'division_id',
        'name',
        'original_headers',
        'destination_table',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'original_headers' => 'array',
    ];

    /**
     * Get the division that owns the mapping index.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the columns for the mapping index.
     */
    public function columns(): HasMany
    {
        return $this->hasMany(MappingColumn::class);
    }
}