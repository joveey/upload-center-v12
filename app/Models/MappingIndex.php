<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MappingIndex extends Model
{
    use HasFactory;

    /**
     * Force MappingIndex to always use main/control DB (never legacy).
     */
    protected $connection = 'sqlsrv';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $control = config('database.control_connection');
        $isLegacy = fn($name) => $name === 'sqlsrv_legacy' || str_starts_with((string) $name, 'legacy_');
        if (! $control || $isLegacy($control)) {
            $control = config('database.connections.sqlsrv') ? 'sqlsrv' : config('database.default');
        }

        $this->setConnection($control);
    }

    protected $fillable = [
        'division_id',
        'code',
        'description',
        'table_name',
        'header_row',
        'upload_mode',
        'connection',
        'target_connection',
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
