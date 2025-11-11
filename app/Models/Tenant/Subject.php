<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

    // Tenant DB connection
    protected $connection = 'tenant';

    protected $fillable = [
        'grade_id',
        'name',
        'code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Relations */
    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function subjectSessions()
    {
        return $this->hasMany(SubjectSession::class);
    }
}