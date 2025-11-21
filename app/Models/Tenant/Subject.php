<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subject extends BaseTenantModel
{
    use HasFactory;


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

    public function SessionSubjects()
    {
        return $this->hasMany(SessionSubject::class);
    }
}
