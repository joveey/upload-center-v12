<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadRun extends Model
{
    /**
     * Force UploadRun to use control DB connection.
     */
    protected $connection = 'sqlsrv';

    /**
     * Force UploadRun to always use the primary app connection (control DB),
     * regardless of any temporary default connection switches during data uploads.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $controlConnection = config('database.control_connection', env('DB_CONNECTION', config('database.default')));
        $isLegacy = fn($name) => $name === 'sqlsrv_legacy' || str_starts_with((string) $name, 'legacy_');
        if (! $controlConnection || $isLegacy($controlConnection)) {
            if (config('database.connections.sqlsrv')) {
                $controlConnection = 'sqlsrv';
            } else {
                $fallback = config('database.default');
                $controlConnection = $isLegacy($fallback) && config('database.connections.sqlsrv') ? 'sqlsrv' : $fallback;
            }
        }
        $this->setConnection($controlConnection);
    }

    protected $fillable = [
        'mapping_index_id',
        'user_id',
        'file_name',
        'stored_xlsx_path',
        'sheet_name',
        'upload_mode',
        'period_date',
        'selected_columns',
        'status',
        'progress_percent',
        'message',
        'started_at',
        'finished_at',
    ];

    public function mappingIndex()
    {
        return $this->belongsTo(\App\Models\MappingIndex::class, 'mapping_index_id');
    }
}
