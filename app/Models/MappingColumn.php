<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MappingColumn extends Model
{
    use HasFactory;

    protected $fillable = [
        'mapping_index_id',
        'excel_column_index',
        'table_column_name',
        'data_type',
        'is_required',
        'is_unique_key',
    ];

    /**
     * Get the mapping index that owns the column.
     */
    public function mappingIndex(): BelongsTo
    {
        return $this->belongsTo(MappingIndex::class);
    }
}