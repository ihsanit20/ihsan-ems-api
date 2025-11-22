<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Payment;
use App\Models\Tenant\FeeInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        // ✅ List view–তে heavy invoice items দরকার নেই
        $query = Payment::with(['student', 'feeInvoice']);

        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->has('fee_invoice_id')) {
            $query->where('fee_invoice_id', $request->fee_invoice_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        if ($request->has('method')) {
            $query->where('method', $request->get('method'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query
            ->orderBy('payment_date', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($payments);
    }

    public function show(Payment $payment)
    {
        // ✅ Receipt page-এ fee invoice items + fee name লাগবে
        $payment->load([
            'student',
            'feeInvoice' => function ($q) {
                $q->with([
                    'items.studentFee.sessionFee.fee',   // ✅ fee name
                    'items.studentFee.sessionFee.grade' // optional
                ])->withCount('items');
            },
        ]);

        return response()->json($payment);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id'     => 'required|exists:tenant.students,id',
            'fee_invoice_id' => 'nullable|exists:tenant.fee_invoices,id',
            'payment_date'   => 'required|date',
            'method'         => 'required|string',
            'amount'         => 'required|numeric|min:0',
            'reference_no'   => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated) {
            $payment = Payment::create([
                'student_id'     => $validated['student_id'],
                'fee_invoice_id' => $validated['fee_invoice_id'] ?? null,
                'payment_date'   => $validated['payment_date'],
                'method'         => $validated['method'],
                'amount'         => $validated['amount'],
                'status'         => 'completed',
                'reference_no'   => $validated['reference_no'] ?? null,
            ]);

            if ($payment->fee_invoice_id) {
                $this->updateInvoiceStatus($payment->fee_invoice_id);
            }

            // ✅ store response-এও nested items দরকার নেই, শুধু basic
            $payment->load(['student', 'feeInvoice']);

            return response()->json([
                'message' => 'Payment created successfully',
                'data'    => $payment
            ], 201);
        });
    }

    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'method'       => 'required|string',
            'amount'       => 'required|numeric|min:0',
            'status'       => 'required|in:pending,completed,failed,refunded',
            'reference_no' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($payment, $validated) {
            $payment->update($validated);

            if ($payment->fee_invoice_id) {
                $this->updateInvoiceStatus($payment->fee_invoice_id);
            }

            $payment->load(['student', 'feeInvoice']);

            return response()->json([
                'message' => 'Payment updated successfully',
                'data'    => $payment
            ]);
        });
    }

    public function destroy(Payment $payment)
    {
        return DB::transaction(function () use ($payment) {
            $invoiceId = $payment->fee_invoice_id;

            $payment->delete();

            if ($invoiceId) {
                $this->updateInvoiceStatus($invoiceId);
            }

            // ✅ 204 এ body যায় না, তাই 200 return করলাম
            return response()->json([
                'message' => 'Payment deleted successfully'
            ]);
        });
    }

    public function studentPayments(int $studentId)
    {
        $payments = Payment::where('student_id', $studentId)
            ->with('feeInvoice')
            ->orderBy('payment_date', 'desc')
            ->get();

        return response()->json($payments);
    }

    public function invoicePayments(FeeInvoice $feeInvoice)
    {
        return $feeInvoice->payments()
            ->with(['student', 'feeInvoice'])
            ->orderByDesc('payment_date')
            ->get();
    }

    /**
     * ✅ Update invoice status based on completed payments
     */
    private function updateInvoiceStatus(int $invoiceId): void
    {
        $invoice = FeeInvoice::findOrFail($invoiceId);

        $totalPaid = Payment::where('fee_invoice_id', $invoiceId)
            ->where('status', 'completed')
            ->sum('amount');

        $payable = (float) $invoice->payable_amount; // ✅ string safe cast

        if ($totalPaid <= 0) {
            $newStatus = 'pending';
        } elseif ($totalPaid >= $payable) {
            $newStatus = 'paid';
        } else {
            $newStatus = 'partial';
        }

        $invoice->update(['status' => $newStatus]);
    }
}
