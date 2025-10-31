<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AcademicSession;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicSessionController extends Controller
{
    /**
     * GET /api/v1/sessions
     * Simple list (default: paginate)
     * Query (optional): active=1|0, paginate=1|0, per_page
     */
    public function index(Request $request)
    {
        $q = AcademicSession::query()
            ->when($request->filled('active'), function ($qry) use ($request) {
                $qry->where('is_active', (bool) $request->boolean('active'));
            })
            ->orderByDesc('start_date');

        if ($request->boolean('paginate', true)) {
            $perPage = max(1, (int) $request->get('per_page', 20));
            return $q->paginate($perPage);
        }

        return response()->json($q->get());
    }

    /**
     * GET /api/v1/sessions/{session}
     */
    public function show(AcademicSession $session)
    {
        return response()->json($session);
    }

    /**
     * POST /api/v1/sessions
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:150', Rule::unique(AcademicSession::class, 'name')],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after:start_date'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        $session = AcademicSession::create($data);

        return response()->json($session, 201);
    }

    /**
     * PUT/PATCH /api/v1/sessions/{session}
     */
    public function update(Request $request, AcademicSession $session)
    {
        $data = $request->validate([
            'name'       => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique(AcademicSession::class, 'name')->ignore($session->id),
            ],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date'   => ['sometimes', 'required', 'date', 'after:start_date'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        $session->fill($data)->save();

        return response()->json($session);
    }

    /**
     * DELETE /api/v1/sessions/{session}
     */
    public function destroy(AcademicSession $session)
    {
        $session->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
