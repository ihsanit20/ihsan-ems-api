<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends BaseTenantModel
{

    protected $table = 'student_enrollments';

    protected $fillable = [
        'student_id',
        'academic_session_id',
        'session_grade_id',
        'section_id',
        'roll_no',
        'admission_type',
        'application_id',
        'admission_date',
        'status',
        'remarks',
    ];

    protected $casts = [
        'admission_date' => 'date',
    ];

    /* ---------- Relationships ---------- */

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    // Alias for academic session (more explicit naming)
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function sessionGrade(): BelongsTo
    {
        return $this->belongsTo(SessionGrade::class, 'session_grade_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'application_id');
    }
}
