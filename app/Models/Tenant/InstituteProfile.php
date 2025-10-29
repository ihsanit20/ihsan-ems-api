<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class InstituteProfile extends Model
{
    /** Tenant DB */
    protected $connection = 'tenant';

    protected $table = 'institute_profiles';

    protected $fillable = ['names', 'contact'];

    protected $casts = [
        'names'   => 'array', // { en?, bn?, ar? }
        'contact' => 'array', // { address, phone?, email?, website?, social?{...} }
    ];

    /**
     * Convenience: get the single row (create one if missing).
     */
    public static function singleton(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        $row = new static();
        // minimal default so UI/print never breaks
        $row->names = null; // optional extra names
        $row->contact = ['address' => '']; // required field to be filled later
        $row->save();

        return $row;
    }
}
