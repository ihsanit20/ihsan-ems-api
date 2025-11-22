<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\FeeInvoice;
use App\Models\Tenant\FeeInvoiceItem;
use App\Models\Tenant\Payment;
use App\Models\Tenant\StudentFee;
use App\Models\Tenant\StudentEnrollment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FeeInvoiceController extends Controller
{
    public function generateMonthly(Request $request)
    {
        $validated = $request->validate([
            'month' => 'nullable|date_format:Y-m',
        ]);

        try {
            $invoiceDate = $this->resolveMonth($validated['month'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $invoiceMonth = $invoiceDate->format('Y-m');
        $summary = [
            'month'   => $invoiceMonth,
            'created' => 0,
            'skipped' => 0,
            'failed'  => 0,
        ];

        StudentEnrollment::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->chunk(100, function ($enrollments) use ($invoiceDate, $invoiceMonth, &$summary) {
                foreach ($enrollments as $enrollment) {
                    try {
                        $studentFees = StudentFee::with(['sessionFee.fee'])
                            ->where('student_id', $enrollment->student_id)
                            ->where('academic_session_id', $enrollment->academic_session_id)
                            ->whereHas('sessionFee.fee', function ($q) {
                                $q->where('billing_type', 'recurring')
                                    ->where('recurring_cycle', 'monthly')
                                    ->where('is_active', true);
                            })
                            ->get();

                        if ($studentFees->isEmpty()) {
                            $summary['skipped']++;
                            continue;
                        }

                        $created = DB::transaction(function () use ($enrollment, $invoiceDate, $invoiceMonth, $studentFees) {
                            $exists = FeeInvoice::where('student_id', $enrollment->student_id)
                                ->where('academic_session_id', $enrollment->academic_session_id)
                                ->where('invoice_month', $invoiceMonth)
                                ->lockForUpdate()
                                ->exists();

                            if ($exists) {
                                return false;
                            }

                            $this->createInvoice(
                                $enrollment->student_id,
                                $enrollment->academic_session_id,
                                $invoiceDate,
                                $invoiceMonth,
                                $studentFees
                            );

                            return true;
                        });

                        $summary[$created ? 'created' : 'skipped']++;
                    } catch (\Throwable $e) {
                        $summary['failed']++;

                        Log::error('Monthly invoice generation failed', [
                            'student_id'          => $enrollment->student_id ?? null,
                            'academic_session_id' => $enrollment->academic_session_id ?? null,
                            'invoice_month'       => $invoiceMonth,
                            'error'               => $e->getMessage(),
                        ]);
                    }
                }
            });

        return response()->json($summary);
    }

    public function index(Request $request)
    {
        $query = FeeInvoice::with('student')->withCount('items');

        if ($request->has('academic_session_id')) {
            $query->where('academic_session_id', $request->academic_session_id);
        }

        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('invoice_no')) {
            $query->where('invoice_no', 'like', '%' . $request->invoice_no . '%');
        }

        $invoices = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($invoices);
    }

    public function show(FeeInvoice $feeInvoice)
    {
        $feeInvoice->load([
            'student',
            'academicSession',
            'items.studentFee.sessionFee.fee',
            'items.studentFee.sessionFee.grade',
            'payments'
        ]);

        return response()->json($feeInvoice);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:tenant.students,id',
            'academic_session_id' => 'required|exists:tenant.academic_sessions,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.student_fee_id' => 'required|exists:tenant.student_fees,id',
            'items.*.description' => 'nullable|string',
            'items.*.amount' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {

            // ✅ NEW invoice no format: INV-YYMMDD-000
            $invoiceDate = Carbon::parse($validated['invoice_date']);
            $invoiceNo = $this->generateInvoiceNo($invoiceDate);

            // Create invoice
            $invoice = FeeInvoice::create([
                'student_id' => $validated['student_id'],
                'academic_session_id' => $validated['academic_session_id'],
                'invoice_no' => $invoiceNo,
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $validated['due_date'] ?? null,
                'total_amount' => 0,
                'total_discount' => 0,
                'payable_amount' => 0,
                'status' => 'pending',
            ]);

            // Create invoice items and calculate totals
            $totalAmount = 0;
            $totalDiscount = 0;

            foreach ($validated['items'] as $item) {
                $studentFee = StudentFee::findOrFail($item['student_fee_id']);
                $amount = $item['amount'] ?? $studentFee->amount;
                $discountAmount = $item['discount_amount'] ?? 0;
                $netAmount = $amount - $discountAmount;

                FeeInvoiceItem::create([
                    'fee_invoice_id' => $invoice->id,
                    'student_fee_id' => $item['student_fee_id'],
                    'description' => $item['description'] ?? null,
                    'amount' => $amount,
                    'discount_amount' => $discountAmount,
                    'net_amount' => $netAmount,
                ]);

                $totalAmount += $amount;
                $totalDiscount += $discountAmount;
            }

            // Update invoice totals
            $invoice->update([
                'total_amount' => $totalAmount,
                'total_discount' => $totalDiscount,
                'payable_amount' => $totalAmount - $totalDiscount,
            ]);

            $invoice->load('items.studentFee.sessionFee.fee');

            return response()->json([
                'message' => 'Invoice created successfully',
                'data' => $invoice
            ], 201);
        });
    }

    public function update(Request $request, FeeInvoice $feeInvoice)
    {
        $validated = $request->validate([
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.student_fee_id' => 'required|exists:tenant.student_fees,id',
            'items.*.description' => 'nullable|string',
            'items.*.amount' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($feeInvoice, $validated) {
            // Update invoice dates
            $feeInvoice->update([
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
            ]);

            // Delete existing items
            $feeInvoice->items()->delete();

            // Recreate items and calculate totals
            $totalAmount = 0;
            $totalDiscount = 0;

            foreach ($validated['items'] as $item) {
                $studentFee = StudentFee::findOrFail($item['student_fee_id']);
                $amount = $item['amount'] ?? $studentFee->amount;
                $discountAmount = $item['discount_amount'] ?? 0;
                $netAmount = $amount - $discountAmount;

                FeeInvoiceItem::create([
                    'fee_invoice_id' => $feeInvoice->id,
                    'student_fee_id' => $item['student_fee_id'],
                    'description' => $item['description'] ?? null,
                    'amount' => $amount,
                    'discount_amount' => $discountAmount,
                    'net_amount' => $netAmount,
                ]);

                $totalAmount += $amount;
                $totalDiscount += $discountAmount;
            }

            // Update invoice totals
            $feeInvoice->update([
                'total_amount' => $totalAmount,
                'total_discount' => $totalDiscount,
                'payable_amount' => $totalAmount - $totalDiscount,
            ]);

            $feeInvoice->load('items.studentFee.sessionFee.fee');

            return response()->json([
                'message' => 'Invoice updated successfully',
                'data' => $feeInvoice
            ]);
        });
    }

    public function destroy(FeeInvoice $feeInvoice)
    {
        $feeInvoice->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Invoice cancelled successfully'
        ]);
    }

    protected function resolveMonth(?string $month): Carbon
    {
        if ($month === null) {
            return now()->startOfMonth();
        }

        try {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Invalid month format. Use YYYY-MM.');
        }
    }

    protected function createInvoice(
        int $studentId,
        int $academicSessionId,
        Carbon $invoiceDate,
        string $invoiceMonth,
        Collection $studentFees
    ): FeeInvoice {
        $invoiceNo = $this->generateInvoiceNo($invoiceDate);

        $invoice = FeeInvoice::create([
            'student_id'          => $studentId,
            'academic_session_id' => $academicSessionId,
            'invoice_no'          => $invoiceNo,
            'invoice_month'       => $invoiceMonth,
            'invoice_date'        => $invoiceDate->toDateString(),
            'due_date'            => $invoiceDate->copy()->addDays(10)->toDateString(),
            'status'              => 'pending',
        ]);

        $totalAmount   = 0;
        $totalDiscount = 0;

        foreach ($studentFees as $studentFee) {
            $amount = (float) $studentFee->amount;
            $discountAmount = $this->calculateDiscount($studentFee, $amount);
            $netAmount = max($amount - $discountAmount, 0);

            FeeInvoiceItem::create([
                'fee_invoice_id'  => $invoice->id,
                'student_fee_id'  => $studentFee->id,
                'amount'          => $amount,
                'discount_amount' => $discountAmount,
                'net_amount'      => $netAmount,
            ]);

            $totalAmount += $amount;
            $totalDiscount += $discountAmount;
        }

        $invoice->update([
            'total_amount'   => round($totalAmount, 2),
            'total_discount' => round($totalDiscount, 2),
            'payable_amount' => round($totalAmount - $totalDiscount, 2),
        ]);

        return $invoice;
    }

    protected function calculateDiscount(StudentFee $studentFee, float $amount): float
    {
        $value = $studentFee->discount_value ?? 0;

        if ($studentFee->discount_type === 'percent' && $value > 0) {
            return round($amount * ($value / 100), 2);
        }

        if ($studentFee->discount_type === 'flat' && $value > 0) {
            return (float) min($amount, $value);
        }

        return 0.0;
    }

    /**
     * ✅ NEW FORMAT:
     * INV-YYMMDD-000 (daily 3-digit serial)
     */
    protected function generateInvoiceNo(Carbon $invoiceDate): string
    {
        $prefix = 'INV-' . $invoiceDate->format('ymd'); // YYMMDD

        $countForDay = FeeInvoice::whereDate('invoice_date', $invoiceDate->toDateString())
            ->lockForUpdate()
            ->count();

        $serial = str_pad($countForDay + 1, 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$serial}";
    }

    public function studentInvoices(int $studentId)
    {
        $query = FeeInvoice::where('student_id', $studentId)
            ->with('academicSession')
            ->withCount('items');

        if (request()->has('status')) {
            $query->where('status', request()->status);
        }

        $invoices = $query->orderBy('invoice_date', 'desc')->get();

        return response()->json($invoices);
    }

    public function dashboardSummary(Request $request)
    {
        $validated = $request->validate([
            'academic_session_id' => 'nullable|integer|exists:tenant.academic_sessions,id',
            'recent_limit' => 'nullable|integer|min:1|max:50',
            'pending_limit' => 'nullable|integer|min:1|max:50',
        ]);

        $sessionId = $validated['academic_session_id'] ?? null;
        $recentLimit = $validated['recent_limit'] ?? 10;
        $pendingLimit = $validated['pending_limit'] ?? 10;

        $cacheKey = "fees:dashboard:" . ($sessionId ?? 'all');

        return Cache::remember($cacheKey, 60, function () use ($sessionId, $recentLimit, $pendingLimit) {

            $invoiceBase = FeeInvoice::query();
            if ($sessionId) {
                $invoiceBase->where('academic_session_id', $sessionId);
            }

            $totalInvoices = (clone $invoiceBase)->count();

            $pendingAmount = (clone $invoiceBase)
                ->where('status', 'pending')
                ->sum('payable_amount');

            $partialAmount = (clone $invoiceBase)
                ->where('status', 'partial')
                ->sum('payable_amount');

            $paidAmount = (clone $invoiceBase)
                ->where('status', 'paid')
                ->sum('payable_amount');

            $studentsWithDues = (clone $invoiceBase)
                ->whereIn('status', ['pending', 'partial'])
                ->distinct('student_id')
                ->count('student_id');

            $pendingInvoices = (clone $invoiceBase)
                ->with('student')
                ->withCount('items')
                ->whereIn('status', ['pending', 'partial'])
                ->orderByDesc('due_date')
                ->limit($pendingLimit)
                ->get();

            $paymentBase = Payment::query()->with(['student', 'feeInvoice']);
            if ($sessionId) {
                // যদি payments টেবিলে academic_session_id না থাকে, এই ফিল্টারটা বাদ দাও
                $paymentBase->where('academic_session_id', $sessionId);
            }

            $recentPayments = $paymentBase
                ->orderByDesc('payment_date')
                ->limit($recentLimit)
                ->get();

            return response()->json([
                'total_invoices' => $totalInvoices,
                'pending_amount' => (float) $pendingAmount,
                'partial_amount' => (float) $partialAmount,
                'paid_amount' => (float) $paidAmount,
                'students_with_dues' => $studentsWithDues,
                'pending_invoices' => $pendingInvoices,
                'recent_payments' => $recentPayments,
            ]);
        });
    }
}
