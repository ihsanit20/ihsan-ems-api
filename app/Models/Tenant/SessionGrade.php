<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionGrade extends BaseTenantModel
{
    protected $table = 'session_grades';

    protected $fillable = [
        'academic_session_id',
        'grade_id',
    ];

    protected $casts = [
        'academic_session_id' => 'integer',
        'grade_id'            => 'integer',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    // যদি Section গুলো SessionGrade-এর অধীনেই থাকে, এটি রেখে দাও
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /* ---- Scopes ---- */
    public function scopeForSession($q, int $sessionId)
    {
        return $q->where('academic_session_id', $sessionId);
    }

    public function scopeFilter($q, ?int $gradeId = null)
    {
        if ($gradeId) {
            $q->where('grade_id', $gradeId);
        }
        return $q;
    }
}
