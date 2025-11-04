<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Level;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LevelController extends Controller
{
    public function index(Request $request)
    {
        $withParam = $request->input('with');
        $with = collect(is_array($withParam) ? $withParam : (is_string($withParam) ? explode(',', $withParam) : []))
            ->map(fn($w) => trim((string) $w))
            ->filter(fn($w) => in_array($w, ['grades']))
            ->values()
            ->all();

        $allowedSort = ['id', 'name', 'sort_order', 'is_active', 'created_at', 'updated_at'];
        $sortBy  = in_array($request->get('sort_by'), $allowedSort, true) ? $request->get('sort_by') : 'sort_order';
        $sortDir = strtolower($request->get('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $q = Level::query()
            ->when($with, fn($x) => $x->with($with))
            ->search($request->q)
            ->active($request->has('is_active') ? $request->boolean('is_active') : null)
            ->orderBy($sortBy, $sortDir)
            ->orderBy('name');

        if ($request->boolean('paginate', true)) {
            $perPage = max(1, min((int) $request->get('per_page', 20), 200));
            return $q->paginate($perPage);
        }

        return ['data' => $q->get()];
    }

    /** POST /v1/levels */
    public function store(Request $request)
    {
        $data = $request->validate([
            // NOTE: point to tenant connection explicitly
            'name'       => ['required', 'string', 'max:255', 'unique:tenant.levels,name'],
            'code'       => ['nullable', 'string', 'max:32'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'  => ['boolean'],
        ]);

        $level = Level::create($data);
        return response()->json($level, 201);
    }

    public function show(Level $level, Request $request)
    {
        if ($request->boolean('with_grades', false)) {
            $level->loadMissing('grades');
        }
        return $level;
    }

    /** PUT/PATCH /v1/levels/{level} */
    public function update(Request $request, Level $level)
    {
        $data = $request->validate([
            // NOTE: tenant connection + ignore current id
            'name'       => ['sometimes', 'required', 'string', 'max:255', Rule::unique('tenant.levels', 'name')->ignore($level->id)],
            'code'       => ['nullable', 'string', 'max:32'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'  => ['boolean'],
        ]);

        $level->update($data);
        return response()->json($level->fresh());
    }

    public function destroy(Level $level)
    {
        $level->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function listGrades(Level $level)
    {
        return ['data' => $level->grades()->get()];
    }

    public function syncGrades(Request $request, Level $level)
    {
        $payload = $request->validate([
            'grade_ids'   => ['required', 'array'],
            // also ensure grades exist in the same (tenant) connection:
            'grade_ids.*' => ['integer', 'exists:tenant.grades,id'],
            'sort'        => ['nullable', 'array'],
        ]);

        $attach = [];
        $sortMap = $payload['sort'] ?? [];
        foreach ($payload['grade_ids'] as $gid) {
            $attach[$gid] = ['sort_order' => isset($sortMap[$gid]) ? (int) $sortMap[$gid] : null];
        }

        $level->grades()->sync($attach);
        return response()->json($level->load('grades'));
    }
}
