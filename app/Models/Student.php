<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'first_name',
        'last_name',
        'email',
        'date_of_birth',
        'grade_level',
        'metadata',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'metadata' => 'array',
        'deleted_at' => 'datetime',
    ];

    /**
     * CRITICAL: Every student belongs to a school (the tenant)
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Tenant-aware: The user who manages this student
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'managed_by');
    }
}
