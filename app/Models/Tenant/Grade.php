<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $connection = 'tenant';

    protected $table = 'grades';

    protected $fillable = [
        'name',
        'code',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** Scope: active(true/false) | null = no filter */
    public function scopeActive($query, ?bool $active = true)
    {
        if ($active === null) return $query;
        return $query->where('is_active', $active);
    }

    /** Scope: search by name/code */
    public function scopeSearch($query, $term = null)
    {
        $term = trim((string) $term);
        if ($term === '') return $query;

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%");
        });
    }

    /** Scope: default ordering for UI */
    public function scopeOrdered($query)
    {
        return $query->orderByRaw('sort_order IS NULL') // NULL last
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');
    }
}
