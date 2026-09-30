<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LineGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'line_group_id',
        'group_name',
        'google_drive_folder_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function files()
    {
        return $this->hasMany(ArchiveFile::class, 'line_group_id');
    }
}
