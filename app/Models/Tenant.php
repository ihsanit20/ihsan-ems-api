<?php

// app/Models/Tenant.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'domain',
        'db_name',
        'is_active',
        'db_host',
        'db_port',
        'db_username',
        'db_password',
    ];

    protected $hidden = ['db_password']; // serialize করলে লুকানো

    // set: write encrypted, get: decrypt (fallback plain if old)
    public function getDbPasswordAttribute($value)
    {
        if (!$value) return null;
        try {
            return decrypt($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }
    public function setDbPasswordAttribute($value)
    {
        $this->attributes['db_password'] = $value ? encrypt($value) : null;
    }
}
