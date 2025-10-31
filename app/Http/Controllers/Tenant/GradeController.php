<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Grade;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;

class GradeController extends Controller
{
    /**
     * GET /v1/grades
     * Query params:
     * - q: string (search in name/code)
     * - is_active: 0|1 (optional filter)
     * - paginate: bool=true
     * - per_page: int (default 20, max 200)
     * - sort_by: id|name|sort_order|is_active|created_at|updated_at
     * - sort_dir: asc|desc
     */
    public function index(Request $request)
    {
        $allowedSort = ['id', 'name', 'sort_order', 'is_active', 'created_at', 'updated_at'];

        $sortBy  = in_array($request->get('sort_by'), $allowedSort, true)
            ? $request->get('sort_by') : 'sort_order';
        $sortDir = strtolower($request->get('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = Grade::query()
            ->search($request->string('q'))
            ->active($request->has('is_active') ? $request->boolean('is_active') : null)
            ->orderBy($sortBy, $sortDir)
            ->orderBy('name');

        $paginate = $request->boolean('paginate', true);

        if ($paginate) {
            $perPage = max(1, min((int) $request->get('per_page', 20), 200));
            return $query->paginate($perPage);
        }

        return ['data' => $query->get()];
    }

    /**
     * POST /v1/grades
     * Body: name (required), code, sort_order, is_active
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:150', Rule::unique(Grade::class, 'name')],
            'code'       => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'  => ['boolean'],
        ]);

        $grade = Grade::create($data);

        return response()->json($grade, 201);
    }

    /**
     * GET /v1/grades/{id}
     */
    public function show(Grade $grade)
    {
        return $grade;
    }

    /**
     * PUT/PATCH /v1/grades/{id}
     */
    public function update(Request $request, Grade $grade)
    {
        $data = $request->validate([
            'name'       => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('grades', 'name')->ignore($grade->id),
            ],
            'code'       => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'  => ['boolean'],
        ]);

        $grade->update($data);

        return response()->json($grade);
    }

    /**
     * DELETE /v1/grades/{id}
     */
    public function destroy(Grade $grade)
    {
        try {
            $grade->delete();
            return response()->json(['message' => 'Deleted'], 200);
        } catch (QueryException $e) {
            // FK constraint (e.g., cohorts.grade_id) blockage
            if ((int) $e->getCode() === 23000) {
                return response()->json([
                    'message' => 'Cannot delete: grade is in use by other records.'
                ], 409);
            }
            throw $e;
        }
    }
}
