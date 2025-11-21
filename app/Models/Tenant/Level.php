<?php

namespace App\Models\Tenant;

class Level extends BaseTenantModel
{
    protected $table = 'levels';

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

    /* ---------- Scopes (level-only) ---------- */
    public function scopeActive($q, ?bool $active = true)
    {
        if ($active === null) return $q;
        return $q->where('is_active', $active);
    }

    public function scopeSearch($q, ?string $term = null)
    {
        $term = trim((string) $term);
        if ($term === '') return $q;

        return $q->where(function ($x) use ($term) {
            $x->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%");
        });
    }

    public function scopeOrdered($q)
    {
        return $q->orderByRaw('sort_order IS NULL')
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
