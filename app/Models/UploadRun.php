<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadRun extends Model
{
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
