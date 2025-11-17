<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

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
