<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $connection = 'tenant';

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

    // ভবিষ্যতে যখন student_enrollments বানাবে, তখন এভাবে ব্যবহার করতে পারবে
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'student_id');
    }
}
