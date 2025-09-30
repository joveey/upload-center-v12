<?php
// app/Models/Division.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_super_user',
    ];

    protected $casts = [
        'is_super_user' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function mappingIndices(): HasMany
    {
        return $this->hasMany(MappingIndex::class);
    }

    public function uploadLogs(): HasMany
    {
        return $this->hasMany(UploadLog::class);
    }

    public function isSuperUser(): bool
    {
        return $this->is_super_user === true;
    }
}