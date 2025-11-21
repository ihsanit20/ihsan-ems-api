<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeInvoiceItem extends BaseTenantModel
{
    use HasFactory;


    protected $fillable = [
        'fee_invoice_id',
        'session_fee_id',
        'description',
        'amount',
        'discount_amount',
        'net_amount',
    ];

    public function feeInvoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function sessionFee(): BelongsTo
    {
        return $this->belongsTo(SessionFee::class, 'session_fee_id');
    }
}
