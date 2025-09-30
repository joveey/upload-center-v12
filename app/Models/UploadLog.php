<?php
// app/Models/UploadLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'division_id',
        'mapping_index_id',
        'file_name',
        'rows_imported',
        'status',
        'error_message',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function mappingIndex()
    {
        return $this->belongsTo(MappingIndex::class);
    }
}