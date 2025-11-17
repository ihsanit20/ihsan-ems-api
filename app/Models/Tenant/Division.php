<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'divisions';

    protected $fillable = ['name', 'en_name', 'url'];

    public function districts()
    {
        return $this->hasMany(District::class);
    }
}
