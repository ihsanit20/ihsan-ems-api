<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Level;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LevelController extends Controller
{
    /** GET /v1/levels */
    public function index(Request $request)
    {
        $allowedSort = ['id', 'name', 'sort_order', 'is_active', 'created_at', 'updated_at'];
        $sortBy  = in_array($request->get('sort_by'), $allowedSort, true) ? $request->get('sort_by') : 'sort_order';
        $sortDir = strtolower($request->get('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $q = Level::query()
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
            'name'       => ['required', 'string', 'max:255', 'unique:tenant.levels,name'],
            'code'       => ['nullable', 'string', 'max:32'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'  => ['boolean'],
        ]);

        $level = Level::create($data);
        return response()->json($level, 201);
    }

    /** GET /v1/levels/{level} */
    public function show(Level $level)
    {
        return $level;
    }

    /** PUT/PATCH /v1/levels/{level} */
    public function update(Request $request, Level $level)
    {
        $data = $request->validate([
            'name'       => ['sometimes', 'required', 'string', 'max:255', Rule::unique('tenant.levels', 'name')->ignore($level->id)],
            'code'       => ['nullable', 'string', 'max:32'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'  => ['boolean'],
        ]);

        $level->update($data);
        return response()->json($level->fresh());
    }

    /** DELETE /v1/levels/{level} */
    public function destroy(Level $level)
    {
        $level->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
