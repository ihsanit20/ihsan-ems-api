<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionApplication extends Model
{
    protected $connection = 'tenant';

    protected $table = 'admission_applications';

    protected $fillable = [
        'application_no',
        'academic_session_id',
        'session_grade_id',
        'application_type',
        'existing_student_id',

        'applicant_name',
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
        'is_present_same_as_permanent',

        'previous_institution_name',
        'previous_class',
        'previous_result',
        'previous_result_division',

        'residential_type',
        'applied_via',
        'application_date',

        'status',
        'status_note',
        'admitted_student_id',

        'photo_path',
        'meta_json',
    ];

    protected $casts = [
        'present_address' => 'array',
        'permanent_address' => 'array',
        'meta_json' => 'array',
        'is_present_same_as_permanent' => 'boolean',
        'application_date' => 'date',
        'date_of_birth' => 'date',
    ];

    /* -------- Relationships -------- */

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function sessionGrade(): BelongsTo
    {
        return $this->belongsTo(SessionGrade::class, 'session_grade_id');
    }

    public function existingStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'existing_student_id');
    }

    public function admittedStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'admitted_student_id');
    }
}
