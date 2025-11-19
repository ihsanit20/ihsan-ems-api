<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFee extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'student_id',
        'academic_session_id',
        'fee_id',
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

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class, 'fee_id');
    }
}
