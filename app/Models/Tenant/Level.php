<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Level extends Model
{
    protected $connection = 'tenant';

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

    /** Grades mapped under this level (via level_grade_maps) */
    public function grades(): BelongsToMany
    {
        return $this->belongsToMany(Grade::class, 'level_grade_maps', 'level_id', 'grade_id')
            ->withTimestamps()
            ->withPivot(['sort_order'])
            ->orderByPivot('sort_order')
            ->orderByRaw('level_grade_maps.sort_order IS NULL')
            ->orderBy('level_grade_maps.sort_order')
            ->orderBy('grades.name');
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
}
