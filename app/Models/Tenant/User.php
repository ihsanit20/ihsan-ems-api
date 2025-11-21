<?php

namespace App\Models\Tenant;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    // Use tenant database connection
    protected $connection = 'tenant';
    protected $table = 'users';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'role',
        'email_verified_at',
        'photo'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * ছবি না থাকলে UI Avatars থেকে ডামি ছবি জেনারেট করা
     */
    public function getPhotoUrlAttribute()
    {
        if ($this->photo) {
            return asset('storage/' . $this->photo);
        }

        return $this->generateDummyPhoto();
    }

    /**
     * UI Avatars ব্যবহার করে ডামি ছবি জেনারেট করা
     */
    protected function generateDummyPhoto()
    {
        $name = $this->name ?: 'User';
        $backgroundColor = $this->getBackgroundColor();
        $initial = strtoupper(mb_substr($name, 0, 1));

        return "https://ui-avatars.com/api/?name={$initial}&background={$backgroundColor}&color=fff&size=200&bold=true";
    }

    /**
     * ব্যাকগ্রাউন্ড কালর জেনারেট করা
     */
    protected function getBackgroundColor()
    {
        $colors = [
            'f44336',
            'e91e63',
            '9c27b0',
            '673ab7',
            '3f51b5',
            '2196f3',
            '03a9f4',
            '00bcd4',
            '009688',
            '4caf50',
            '8bc34a',
            'cddc39',
            'ffeb3b',
            'ffc107',
            'ff9800',
            'ff5722',
            '795548',
            '607d8b'
        ];

        $name = $this->name ?: 'User';
        $hash = crc32($name);
        $index = abs($hash) % count($colors);

        return $colors[$index];
    }
}
