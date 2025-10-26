<?php

// app/Models/Tenant.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        'branding',
    ];

    protected $casts = [
        'branding' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = ['db_password'];

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

    /** ✅ Always build S3/CloudFront URLs */
    public function getBrandingUrlsAttribute(): array
    {
        $b = $this->branding ?? [];
        $disk = Storage::disk('s3');

        return [
            'logo_url'    => !empty($b['logo_key'])    ? $disk->url($b['logo_key'])    : null,
            'favicon_url' => !empty($b['favicon_key']) ? $disk->url($b['favicon_key']) : null,
        ];
    }
}
