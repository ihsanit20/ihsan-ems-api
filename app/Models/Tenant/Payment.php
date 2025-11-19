<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'student_id',
        'fee_invoice_id',
        'payment_date',
        'method',
        'amount',
        'status',
        'reference_no',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function feeInvoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }
}
