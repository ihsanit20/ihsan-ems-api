<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\SessionFee;
use Illuminate\Http\Request;

class SessionFeeController extends Controller
{
    /**
     * List session fees with filters + optional pagination.
     *
     * GET /v1/session-fees
     */
    public function index(Request $request)
    {
        $query = SessionFee::query()
            ->with([
                'session:id,name',
                'grade:id,name',
                'fee:id,name,billing_type,recurring_cycle',
            ]);

        // Filters
        if ($sessionId = $request->input('academic_session_id')) {
            $query->where('academic_session_id', $sessionId);
        }

        if ($gradeId = $request->input('grade_id')) {
            $query->where('grade_id', $gradeId);
        }

        if ($feeId = $request->input('fee_id')) {
            $query->where('fee_id', $feeId);
        }

        // Search by fee name (optional)
        if ($q = trim((string) $request->input('q', ''))) {
            $query->whereHas('fee', function ($sub) use ($q) {
                $sub->where('name', 'like', '%' . $q . '%');
            });
        }

        // Optional: only_active (by fee)
        if (!is_null($request->input('only_active'))) {
            $onlyActive = $request->boolean('only_active');
            $query->whereHas('fee', function ($sub) use ($onlyActive) {
                $sub->where('is_active', $onlyActive);
            });
        }

        // paginate=false দিলে সব রিটার্ন করবে
        $paginate = $request->boolean('paginate', true);

        if (! $paginate) {
            $data = $query
                ->orderBy('academic_session_id')
                ->orderBy('grade_id')
                ->orderBy('fee_id')
                ->get();

            return response()->json(['data' => $data]);
        }

        // Pagination
        $perPage = (int) $request->input('per_page', 25);
        if ($perPage < 1 || $perPage > 200) {
            $perPage = 25;
        }

        $sessionFees = $query
            ->orderBy('academic_session_id')
            ->orderBy('grade_id')
            ->orderBy('fee_id')
            ->paginate($perPage);

        return response()->json($sessionFees);
    }

    /**
     * Store a new session fee.
     *
     * POST /v1/session-fees
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'academic_session_id' => ['required', 'integer', 'exists:tenant.academic_sessions,id'],
            'grade_id'            => ['required', 'integer', 'exists:tenant.grades,id'],
            'fee_id'              => ['required', 'integer', 'exists:tenant.fees,id'],
            'amount'              => ['required', 'numeric', 'min:0'],
        ]);

        // Unique constraint respect করার জন্য try/catch optional,
        // তবে DB-level unique থাকায় duplicate হলে exception যাবে।
        $sessionFee = SessionFee::create($data);

        return response()->json([
            'message' => 'Session fee created successfully.',
            'data'    => $sessionFee->load(['session', 'grade', 'fee']),
        ], 201);
    }

    /**
     * Show a single session fee.
     *
     * GET /v1/session-fees/{sessionFee}
     */
    public function show(SessionFee $sessionFee)
    {
        $sessionFee->load(['session', 'grade', 'fee']);

        return response()->json([
            'data' => $sessionFee,
        ]);
    }

    /**
     * Update an existing session fee.
     *
     * PUT/PATCH /v1/session-fees/{sessionFee}
     */
    public function update(Request $request, SessionFee $sessionFee)
    {
        $data = $request->validate([
            'academic_session_id' => ['sometimes', 'required', 'integer', 'exists:tenant.academic_sessions,id'],
            'grade_id'            => ['sometimes', 'required', 'integer', 'exists:tenant.grades,id'],
            'fee_id'              => ['sometimes', 'required', 'integer', 'exists:tenant.fees,id'],
            'amount'              => ['sometimes', 'required', 'numeric', 'min:0'],
        ]);

        $sessionFee->fill($data);
        $sessionFee->save();

        return response()->json([
            'message' => 'Session fee updated successfully.',
            'data'    => $sessionFee->load(['session', 'grade', 'fee']),
        ]);
    }

    /**
     * Delete a session fee.
     *
     * DELETE /v1/session-fees/{sessionFee}
     */
    public function destroy(SessionFee $sessionFee)
    {
        // ভবিষ্যতে যদি invoice/session_student_fee ইত্যাদি depend থাকে,
        // এখানে আগে exists() চেক করে block করতে পারবে।

        $sessionFee->delete();

        return response()->json([
            'message' => 'Session fee deleted successfully.',
        ]);
    }
}
