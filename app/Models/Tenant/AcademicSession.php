<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class AcademicSession extends Model
{
    /** Tenant DB */
    protected $connection = 'tenant';

    /** Table name */
    protected $table = 'academic_sessions';

    /** Mass-assignable fields */
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_active',
    ];

    /** Casts */
    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
