<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\FeeInvoice;
use App\Models\Tenant\FeeInvoiceItem;
use App\Models\Tenant\SessionFee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeInvoiceController extends Controller
{
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

        $invoices = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return response()->json($invoices);
    }

    public function show(FeeInvoice $feeInvoice)
    {
        $feeInvoice->load([
            'student',
            'academicSession',
            'items.sessionFee.fee',
            'items.sessionFee.grade',
            'payments'
        ]);

        return response()->json($feeInvoice);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.session_fee_id' => 'required|exists:session_fees,id',
            'items.*.description' => 'nullable|string',
            'items.*.amount' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {
            // Generate invoice number
            $invoiceNo = 'INV-' . date('Ymd') . '-' . str_pad(FeeInvoice::count() + 1, 5, '0', STR_PAD_LEFT);

            // Create invoice
            $invoice = FeeInvoice::create([
                'student_id' => $validated['student_id'],
                'academic_session_id' => $validated['academic_session_id'],
                'invoice_no' => $invoiceNo,
                'invoice_date' => $validated['invoice_date'],
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
                $sessionFee = SessionFee::findOrFail($item['session_fee_id']);
                $amount = $item['amount'] ?? $sessionFee->amount;
                $discountAmount = $item['discount_amount'] ?? 0;
                $netAmount = $amount - $discountAmount;

                FeeInvoiceItem::create([
                    'fee_invoice_id' => $invoice->id,
                    'session_fee_id' => $item['session_fee_id'],
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

            $invoice->load('items.sessionFee.fee');

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
            'items.*.session_fee_id' => 'required|exists:session_fees,id',
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
                $sessionFee = SessionFee::findOrFail($item['session_fee_id']);
                $amount = $item['amount'] ?? $sessionFee->amount;
                $discountAmount = $item['discount_amount'] ?? 0;
                $netAmount = $amount - $discountAmount;

                FeeInvoiceItem::create([
                    'fee_invoice_id' => $feeInvoice->id,
                    'session_fee_id' => $item['session_fee_id'],
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

            $feeInvoice->load('items.sessionFee.fee');

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
}
