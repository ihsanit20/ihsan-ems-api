<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionGrade extends Model
{
    protected $connection = 'tenant';
    protected $table = 'session_grades';

    protected $fillable = [
        'academic_session_id',
        'grade_id',
        'shift',
        'medium',
        'capacity',
        'class_teacher_id',
        'code',
        'meta_json',
    ];

    protected $casts = [
        'capacity'   => 'integer',
        'meta_json'  => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'class_teacher_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /* ---- Optional scopes ---- */
    public function scopeForSession($q, int $sessionId)
    {
        return $q->where('academic_session_id', $sessionId);
    }
    public function scopeFilter($q, ?int $gradeId = null, ?string $shift = null, ?string $medium = null)
    {
        if ($gradeId) $q->where('grade_id', $gradeId);
        if ($shift !== null && $shift !== '') $q->where('shift', $shift);
        if ($medium !== null && $medium !== '') $q->where('medium', $medium);
        return $q;
    }
}