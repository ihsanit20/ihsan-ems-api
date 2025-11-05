<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_grade_id',
        'name',
        'code',
        'capacity',
        'class_teacher_id',
        'sort_order'
    ];

    protected $casts = [
        'capacity'   => 'integer',
        'sort_order' => 'integer',
    ];

    public function sessionGrade()
    {
        return $this->belongsTo(SessionGrade::class);
    }

    public function classTeacher()
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }
}
