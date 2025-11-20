<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\StudentFee;
use Illuminate\Http\Request;

class StudentFeeController extends Controller
{
    public function index(Request $request)
    {
        $query = StudentFee::with(['student', 'sessionFee.fee', 'sessionFee.grade', 'academicSession']);

        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->has('academic_session_id')) {
            $query->where('academic_session_id', $request->academic_session_id);
        }

        if ($request->has('session_fee_id')) {
            $query->where('session_fee_id', $request->session_fee_id);
        }

        $studentFees = $query->paginate($request->get('per_page', 15));

        return response()->json($studentFees);
    }

    public function show(StudentFee $studentFee)
    {
        $studentFee->load(['student', 'sessionFee.fee', 'sessionFee.grade', 'academicSession']);
        return response()->json($studentFee);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'session_fee_id' => 'required_without:items|exists:session_fees,id',
            'amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:flat,percent',
            'discount_value' => 'nullable|numeric|min:0',
            'items' => 'array',
            'items.*.session_fee_id' => 'required|exists:session_fees,id',
            'items.*.amount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:flat,percent',
            'items.*.discount_value' => 'nullable|numeric|min:0',
        ]);

        $created = [];

        if ($request->has('items')) {
            // Multiple items format
            foreach ($validated['items'] as $item) {
                $sessionFee = \App\Models\Tenant\SessionFee::findOrFail($item['session_fee_id']);
                $created[] = StudentFee::create([
                    'student_id' => $validated['student_id'],
                    'academic_session_id' => $validated['academic_session_id'],
                    'session_fee_id' => $item['session_fee_id'],
                    'amount' => $item['amount'] ?? $sessionFee->amount,
                    'discount_type' => $item['discount_type'] ?? null,
                    'discount_value' => $item['discount_value'] ?? null,
                ]);
            }
        } else {
            // Single item format
            $sessionFee = \App\Models\Tenant\SessionFee::findOrFail($validated['session_fee_id']);
            $created[] = StudentFee::create([
                'student_id' => $validated['student_id'],
                'academic_session_id' => $validated['academic_session_id'],
                'session_fee_id' => $validated['session_fee_id'],
                'amount' => $validated['amount'] ?? $sessionFee->amount,
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_value' => $validated['discount_value'] ?? null,
            ]);
        }

        return response()->json([
            'message' => 'Student fee(s) created successfully',
            'data' => $created
        ], 201);
    }

    public function update(Request $request, StudentFee $studentFee)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'discount_type' => 'nullable|in:flat,percent',
            'discount_value' => 'nullable|numeric|min:0',
        ]);

        $studentFee->update($validated);

        return response()->json([
            'message' => 'Student fee updated successfully',
            'data' => $studentFee
        ]);
    }

    public function destroy(StudentFee $studentFee)
    {
        $studentFee->delete();

        return response()->json([
            'message' => 'Student fee deleted successfully'
        ], 204);
    }

    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
            'session_fee_ids' => 'required|array',
            'session_fee_ids.*' => 'exists:session_fees,id',
            'amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:flat,percent',
            'discount_value' => 'nullable|numeric|min:0',
        ]);

        $created = 0;
        $existing = 0;

        foreach ($validated['student_ids'] as $studentId) {
            foreach ($validated['session_fee_ids'] as $sessionFeeId) {
                $sessionFee = \App\Models\Tenant\SessionFee::findOrFail($sessionFeeId);

                $studentFee = StudentFee::firstOrCreate(
                    [
                        'student_id' => $studentId,
                        'academic_session_id' => $validated['academic_session_id'],
                        'session_fee_id' => $sessionFeeId,
                    ],
                    [
                        'amount' => $validated['amount'] ?? $sessionFee->amount,
                        'discount_type' => $validated['discount_type'] ?? null,
                        'discount_value' => $validated['discount_value'] ?? null,
                    ]
                );

                if ($studentFee->wasRecentlyCreated) {
                    $created++;
                } else {
                    $existing++;
                }
            }
        }

        return response()->json([
            'message' => 'Bulk assignment completed',
            'created' => $created,
            'existing' => $existing
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:student_fees,id',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:flat,percent',
            'items.*.discount_value' => 'nullable|numeric|min:0',
        ]);

        $updated = [];

        foreach ($validated['items'] as $item) {
            $studentFee = StudentFee::findOrFail($item['id']);
            $studentFee->update([
                'amount' => $item['amount'],
                'discount_type' => $item['discount_type'] ?? null,
                'discount_value' => $item['discount_value'] ?? null,
            ]);
            $updated[] = $studentFee;
        }

        return response()->json([
            'message' => 'Bulk update completed',
            'data' => $updated
        ]);
    }
}
