<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Section extends Model
{
    protected $connection = 'tenant';
    protected $table = 'sections';

    protected $fillable = [
        'session_grade_id',
        'name',
        'code',
        'capacity',
        'class_teacher_id',
        'sort_order',
    ];

    protected $casts = [
        'capacity'   => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sessionGrade(): BelongsTo
    {
        return $this->belongsTo(SessionGrade::class);
    }

    // FIX: employees নেই → users টেবিল মডেল ব্যবহার করুন
    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    /* ---- Optional scopes ---- */
    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }
}
