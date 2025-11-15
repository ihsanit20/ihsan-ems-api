<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Fee extends Model
{
    protected $connection = 'tenant';

    protected $table = 'fees';

    protected $fillable = [
        'name',
        'billing_type',
        'recurring_cycle',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
    ];

    /* ---------- Helpers ---------- */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isOneTime(): bool
    {
        return $this->billing_type === 'one_time';
    }

    public function isMonthly(): bool
    {
        return $this->billing_type === 'recurring'
            && $this->recurring_cycle === 'monthly';
    }

    // পরে যখন SessionFee মডেল করবে তখন আনকমেন্ট করে দিতে পারো
    // public function sessionFees()
    // {
    //     return $this->hasMany(SessionFee::class);
    // }
}