<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeInvoiceItem extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'fee_invoice_id',
        'fee_id',
        'description',
        'amount',
        'discount_amount',
        'net_amount',
    ];

    public function feeInvoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class, 'fee_id');
    }
}
