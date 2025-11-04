<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Grade;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GradeController extends Controller
{
    /** GET /v1/grades */
    public function index(Request $request)
    {
        // eager loads
        $withParam = $request->input('with');
        $with = collect(is_array($withParam) ? $withParam : (is_string($withParam) ? explode(',', $withParam) : []))
            ->map(fn($w) => trim((string) $w))
            ->filter(fn($w) => in_array($w, ['level']))
            ->values()
            ->all();

        // sorting
        $allowedSort = ['id', 'name', 'code', 'sort_order', 'is_active', 'level_id', 'created_at', 'updated_at'];
        $sortBy  = in_array($request->get('sort_by'), $allowedSort, true) ? $request->get('sort_by') : 'sort_order';
        $sortDir = strtolower($request->get('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $q = Grade::query()
            ->when($with, fn($x) => $x->with($with))
            ->when($request->filled('level_id'), fn($x) => $x->where('level_id', (int) $request->level_id))
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

    /** GET /v1/grades/{grade} */
    public function show(Grade $grade, Request $request)
    {
        if ($request->boolean('with_level', false)) {
            $grade->loadMissing('level');
        }
        return $grade;
    }

    /** POST /v1/grades */
    public function store(Request $request)
    {
        $data = $request->validate([
            'level_id'   => ['required', 'integer', 'exists:tenant.levels,id'],
            'name'       => ['required', 'string', 'max:255', 'unique:tenant.grades,name'],
            'code'       => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'  => ['boolean'],
        ]);

        $grade = Grade::create($data);
        return response()->json($grade->fresh(), 201);
    }

    /** PUT/PATCH /v1/grades/{grade} */
    public function update(Request $request, Grade $grade)
    {
        $data = $request->validate([
            'level_id'   => ['sometimes', 'required', 'integer', 'exists:tenant.levels,id'],
            'name'       => ['sometimes', 'required', 'string', 'max:255', Rule::unique('tenant.grades', 'name')->ignore($grade->id)],
            'code'       => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'  => ['boolean'],
        ]);

        $grade->update($data);
        return response()->json($grade->fresh());
    }

    /** DELETE /v1/grades/{grade} */
    public function destroy(Grade $grade)
    {
        $grade->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
