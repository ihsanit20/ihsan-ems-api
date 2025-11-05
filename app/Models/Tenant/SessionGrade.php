<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SessionGrade extends Model
{
    use HasFactory;

    protected $table = 'session_grades';

    protected $fillable = [
        'academic_session_id',
        'grade_id',
        'capacity',
        'class_teacher_id',
        'meta_json',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'meta_json' => 'array',
    ];

    /** Relations */
    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function classTeacher()
    {
        // ধরে নিচ্ছি tenant users টেবিলের মডেল App\Models\Tenant\User
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    public function sections()
    {
        return $this->hasMany(Section::class, 'session_grade_id');
    }

    /** Scopes (optional) */
    public function scopeForSession($q, $sessionId)
    {
        return $q->where('academic_session_id', $sessionId);
    }

    public function scopeForGrade($q, $gradeId)
    {
        return $q->where('grade_id', $gradeId);
    }
}
