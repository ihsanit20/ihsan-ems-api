<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends BaseTenantModel
{
    protected $table = 'students';

    protected $fillable = [
        'student_code',
        'user_id',

        'name_bn',
        'name_en',
        'gender',
        'date_of_birth',
        'student_phone',
        'student_email',

        'father_name',
        'father_phone',
        'father_occupation',
        'mother_name',
        'mother_phone',
        'mother_occupation',

        'guardian_type',
        'guardian_name',
        'guardian_phone',
        'guardian_relation',

        'present_address',
        'permanent_address',

        'residential_type',
        'status',
        'photo_path',
        'meta_json',
    ];

    protected $casts = [
        'present_address'   => 'array',
        'permanent_address' => 'array',
        'meta_json'         => 'array',
        'date_of_birth'     => 'date',
    ];

    /* -------- Relationships -------- */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'student_id');
    }

    /* -------- Helper Methods -------- */

    public function getLatestEnrollment(): ?StudentEnrollment
    {
        return $this->enrollments()
            ->orderByDesc('academic_session_id')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Old full details (used when needed)
     */
    public function getLatestEnrollmentWithDetails(): ?StudentEnrollment
    {
        return $this->enrollments()
            ->with([
                'academicSession',
                'sessionGrade.grade.level',
                'section',
            ])
            ->orderByDesc('academic_session_id')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * ✅ Slim latest enrollment (id + name only)
     * Search/list/readmit UI-র জন্য best.
     */
    public function getLatestEnrollmentWithDetailsSlim(): ?StudentEnrollment
    {
        return $this->enrollments()
            ->select([
                'id',
                'student_id',
                'academic_session_id',
                'session_grade_id',
                'section_id',
                'roll_no',
                'status',
                'admission_type',
            ])
            ->with([
                'academicSession:id,name,is_active',
                'sessionGrade:id,academic_session_id,grade_id',
                'sessionGrade.grade:id,level_id,name',
                'sessionGrade.grade.level:id,name',
                'section:id,session_grade_id,name',
            ])
            ->orderByDesc('academic_session_id')
            ->orderByDesc('id')
            ->first();
    }
}