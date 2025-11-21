<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Division extends BaseTenantModel
{
    use HasFactory;


    protected $table = 'divisions';

    protected $fillable = ['name', 'en_name', 'url'];

    public function districts()
    {
        return $this->hasMany(District::class);
    }
}
