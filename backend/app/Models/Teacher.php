<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Teacher extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'teacher_code',
        'name',
        'email',
        'is_admin',
        'is_active',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function files()
    {
        return $this->hasMany(ArchiveFile::class, 'teacher_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'teacher_id');
    }
}
