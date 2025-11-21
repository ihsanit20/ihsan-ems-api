<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Area extends BaseTenantModel
{
    use HasFactory;


    protected $table = 'areas';

    protected $fillable = ['district_id', 'name', 'en_name'];

    public function district()
    {
        return $this->belongsTo(District::class);
    }
}
