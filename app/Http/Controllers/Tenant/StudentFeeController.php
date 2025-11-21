<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\FeeInvoiceItem;
use App\Models\Tenant\StudentFee;
use App\Models\Tenant\Student;
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
        // Support both 'with_latest_enrollment' and 'auto_session' parameters
        $useLatestEnrollment = $request->boolean('with_latest_enrollment', false) || $request->boolean('auto_session', false);

        if ($useLatestEnrollment) {
            // If auto_session is enabled, academic_session_id is optional
            $validated = $request->validate([
                'student_id' => 'required|exists:tenant.students,id',
                'academic_session_id' => 'nullable|exists:tenant.academic_sessions,id',
                'session_fee_id' => 'required_without:items|exists:tenant.session_fees,id',
                'amount' => 'nullable|numeric|min:0',
                'discount_type' => 'nullable|in:flat,percent',
                'discount_value' => 'nullable|numeric|min:0',
                'items' => 'array',
                'items.*.session_fee_id' => 'required|exists:tenant.session_fees,id',
                'items.*.amount' => 'nullable|numeric|min:0',
                'items.*.discount_type' => 'nullable|in:flat,percent',
                'items.*.discount_value' => 'nullable|numeric|min:0',
            ]);

            // Get student's latest enrollment if not provided
            if (!$validated['academic_session_id']) {
                $student = Student::findOrFail($validated['student_id']);
                $enrollment = $student->getLatestEnrollment();

                if (!$enrollment) {
                    return response()->json([
                        'message' => 'Student has no enrollment. Please provide academic_session_id explicitly.',
                        'data' => null
                    ], 422);
                }

                $validated['academic_session_id'] = $enrollment->academic_session_id;
            }
        } else {
            // Original validation - academic_session_id is required
            $validated = $request->validate([
                'student_id' => 'required|exists:tenant.students,id',
                'academic_session_id' => 'required|exists:tenant.academic_sessions,id',
                'session_fee_id' => 'required_without:items|exists:tenant.session_fees,id',
                'amount' => 'nullable|numeric|min:0',
                'discount_type' => 'nullable|in:flat,percent',
                'discount_value' => 'nullable|numeric|min:0',
                'items' => 'array',
                'items.*.session_fee_id' => 'required|exists:tenant.session_fees,id',
                'items.*.amount' => 'nullable|numeric|min:0',
                'items.*.discount_type' => 'nullable|in:flat,percent',
                'items.*.discount_value' => 'nullable|numeric|min:0',
            ]);
        }

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
            'academic_session_id' => 'required|exists:tenant.academic_sessions,id',
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:tenant.students,id',
            'session_fee_ids' => 'required|array',
            'session_fee_ids.*' => 'exists:tenant.session_fees,id',
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

    public function dueFees(Request $request, int $studentId)
    {
        /**
         * ✅ We want DUE fees from student_fees table.
         * Exclude only those student_fees that are already invoiced.
         */

        // 1) already invoiced student_fee_ids for this student (optionally scoped by session)
        $invoicedStudentFeeIds = FeeInvoiceItem::whereHas('feeInvoice', function ($q) use ($studentId, $request) {
            $q->where('student_id', $studentId);

            if ($request->filled('academic_session_id')) {
                $q->where('academic_session_id', $request->input('academic_session_id'));
            }
        })
            ->whereNotNull('student_fee_id')
            ->pluck('student_fee_id')
            ->unique()
            ->values();

        // 2) now pull due StudentFee rows
        $dueFees = StudentFee::with(['sessionFee.fee', 'sessionFee.grade', 'academicSession'])
            ->where('student_id', $studentId)
            ->when($request->filled('academic_session_id'), function ($q) use ($request) {
                $q->where('academic_session_id', $request->input('academic_session_id'));
            })
            // optional grade filter (your frontend is sending grade_id)
            ->when($request->filled('grade_id'), function ($q) use ($request) {
                $gradeId = $request->input('grade_id');
                $q->whereHas('sessionFee', function ($sq) use ($gradeId) {
                    $sq->where('grade_id', $gradeId);
                });
            })
            // ✅ exclude by student_fees.id
            ->when($invoicedStudentFeeIds->count() > 0, function ($q) use ($invoicedStudentFeeIds) {
                $q->whereNotIn('id', $invoicedStudentFeeIds);
            })
            ->get();

        return response()->json($dueFees);
    }
}
