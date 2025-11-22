<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class StudentFee extends BaseTenantModel
{
    use HasFactory;


    protected $fillable = [
        'student_id',
        'academic_session_id',
        'session_fee_id',
        'amount',
        'discount_type',
        'discount_value',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function sessionFee(): BelongsTo
    {
        return $this->belongsTo(SessionFee::class, 'session_fee_id');
    }

    public function fee(): HasOneThrough
    {
        return $this->hasOneThrough(
            Fee::class,
            SessionFee::class,
            'id',       // session_fees.id
            'id',       // fees.id
            'session_fee_id', // student_fees.session_fee_id
            'fee_id'    // session_fees.fee_id
        );
    }
}
