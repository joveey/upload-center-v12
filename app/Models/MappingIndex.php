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
        'code',           // SESUAIKAN dengan database
        'description',    // SESUAIKAN dengan database
        'table_name',
        'header_row',
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