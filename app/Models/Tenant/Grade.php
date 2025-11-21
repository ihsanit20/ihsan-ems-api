<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends BaseTenantModel
{
    protected $table = 'grades';

    protected $fillable = [
        'level_id',
        'name',
        'code',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'level_id'   => 'integer',
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* ---------- Relations ---------- */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /* ---------- Scopes ---------- */
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

    public function sessionGrades()
    {
        return $this->hasMany(SessionGrade::class);
    }
}
