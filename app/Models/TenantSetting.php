<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    // টেন্যান্ট DB তে পয়েন্ট করি
    protected $connection = 'tenant';
    protected $table = 'tenant_settings';

    protected $fillable = [
        'name',
        'short_name',
        'branding',
        'locale',
        'currency',
        'features',
        'policy',
        'maintenance'
    ];

    protected $casts = [
        'branding'   => 'array',
        'locale'     => 'array',
        'currency'   => 'array',
        'features'   => 'array',
        'policy'     => 'array',
        'maintenance' => 'boolean',
    ];
}
