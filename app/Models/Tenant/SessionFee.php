<?php

namespace App\Models\Tenant;

class SessionFee extends BaseTenantModel
{

    protected $table = 'session_fees';

    protected $fillable = [
        'academic_session_id',
        'grade_id',
        'fee_id',
        'amount',
    ];

    protected $casts = [
        'academic_session_id' => 'integer',
        'grade_id'            => 'integer',
        'fee_id'              => 'integer',
        'amount'              => 'decimal:2',
    ];

    /* ---------- Relationships ---------- */

    public function session()
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function fee()
    {
        return $this->belongsTo(Fee::class, 'fee_id');
    }
}
