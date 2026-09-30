<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArchiveFile extends Model
{
    use HasFactory;

    protected $table = 'files';

    protected $fillable = [
        'line_message_id',
        'line_group_id',
        'teacher_id',
        'sender_line_id',
        'sender_name',
        'original_filename',
        'stored_filename',
        'mime_type',
        'file_size',
        'google_drive_file_id',
        'google_drive_url',
        'status',
        'error_message',
        'uploaded_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function group()
    {
        return $this->belongsTo(LineGroup::class, 'line_group_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'file_id');
    }
}
