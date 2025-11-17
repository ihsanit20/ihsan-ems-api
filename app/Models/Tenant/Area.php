<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'areas';

    protected $fillable = ['district_id', 'name', 'en_name'];

    public function district()
    {
        return $this->belongsTo(District::class);
    }
}
