<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class District extends BaseTenantModel
{
    use HasFactory;


    protected $table = 'districts';

    protected $fillable = ['division_id', 'name', 'en_name'];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function areas()
    {
        return $this->hasMany(Area::class);
    }
}
