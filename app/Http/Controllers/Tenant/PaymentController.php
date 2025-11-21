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

        $payments = $query->orderBy('payment_date', 'desc')->paginate($request->get('per_page', 15));

        return response()->json($payments);
    }

    public function show(Payment $payment)
    {
        $payment->load(['student', 'feeInvoice']);
        return response()->json($payment);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:tenant.students,id',
            'fee_invoice_id' => 'nullable|exists:tenant.fee_invoices,id',
            'payment_date' => 'required|date',
            'method' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'reference_no' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated) {
            // Create payment
            $payment = Payment::create([
                'student_id' => $validated['student_id'],
                'fee_invoice_id' => $validated['fee_invoice_id'] ?? null,
                'payment_date' => $validated['payment_date'],
                'method' => $validated['method'],
                'amount' => $validated['amount'],
                'status' => 'completed',
                'reference_no' => $validated['reference_no'] ?? null,
            ]);

            // Update invoice status if linked to an invoice
            if ($payment->fee_invoice_id) {
                $this->updateInvoiceStatus($payment->fee_invoice_id);
            }

            $payment->load(['student', 'feeInvoice']);

            return response()->json([
                'message' => 'Payment created successfully',
                'data' => $payment
            ], 201);
        });
    }

    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'method' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:pending,completed,failed,refunded',
            'reference_no' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($payment, $validated) {
            $payment->update($validated);

            // Update invoice status if linked to an invoice
            if ($payment->fee_invoice_id) {
                $this->updateInvoiceStatus($payment->fee_invoice_id);
            }

            $payment->load(['student', 'feeInvoice']);

            return response()->json([
                'message' => 'Payment updated successfully',
                'data' => $payment
            ]);
        });
    }

    public function destroy(Payment $payment)
    {
        return DB::transaction(function () use ($payment) {
            $invoiceId = $payment->fee_invoice_id;

            $payment->delete();

            // Update invoice status if it was linked to an invoice
            if ($invoiceId) {
                $this->updateInvoiceStatus($invoiceId);
            }

            return response()->json([
                'message' => 'Payment deleted successfully'
            ], 204);
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
     * Update invoice status based on payments
     */
    private function updateInvoiceStatus(int $invoiceId): void
    {
        $invoice = FeeInvoice::findOrFail($invoiceId);

        // Calculate total paid (only completed payments)
        $totalPaid = Payment::where('fee_invoice_id', $invoiceId)
            ->where('status', 'completed')
            ->sum('amount');

        // Determine new status
        if ($totalPaid == 0) {
            $newStatus = 'pending';
        } elseif ($totalPaid >= $invoice->payable_amount) {
            $newStatus = 'paid';
        } else {
            $newStatus = 'partial';
        }

        // Update invoice status
        $invoice->update(['status' => $newStatus]);
    }
}
