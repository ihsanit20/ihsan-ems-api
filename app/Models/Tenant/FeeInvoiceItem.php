<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Tenant\StudentFee;

class FeeInvoiceItem extends BaseTenantModel
{
    use HasFactory;


    protected $fillable = [
        'fee_invoice_id',
        'student_fee_id',
        'description',
        'amount',
        'discount_amount',
        'net_amount',
    ];

    public function feeInvoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function studentFee(): BelongsTo
    {
        return $this->belongsTo(StudentFee::class, 'student_fee_id');
    }
}
