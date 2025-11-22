<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeInvoice extends BaseTenantModel
{
    use HasFactory;


    protected $fillable = [
        'student_id',
        'academic_session_id',
        'invoice_no',
        'invoice_month',
        'invoice_date',
        'due_date',
        'total_amount',
        'total_discount',
        'payable_amount',
        'status',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeeInvoiceItem::class, 'fee_invoice_id', 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'fee_invoice_id', 'id');
    }
}
