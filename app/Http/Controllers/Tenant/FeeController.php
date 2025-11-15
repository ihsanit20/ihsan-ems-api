<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Fee;
use Illuminate\Http\Request;

class FeeController extends Controller
{
    /**
     * List fees with filters + pagination.
     *
     * GET /admin/fees
     */
    public function index(Request $request)
    {
        $query = Fee::query();

        // --- Filters ---
        if ($search = trim((string) $request->input('q', ''))) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        if ($billingType = $request->input('billing_type')) {
            // one_time | recurring
            $query->where('billing_type', $billingType);
        }

        if ($recurringCycle = $request->input('recurring_cycle')) {
            // monthly | yearly | term
            $query->where('recurring_cycle', $recurringCycle);
        }

        if (!is_null($request->input('is_active'))) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // --- Sorting ---
        $allowedSorts = ['name', 'sort_order', 'billing_type', 'created_at'];
        $sortBy  = $request->input('sort_by', 'sort_order');
        $sortDir = $request->input('sort_dir', 'asc');

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'sort_order';
        }
        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        $query->orderBy($sortBy, $sortDir)->orderBy('id', 'asc');

        // --- Pagination ---
        $perPage = (int) $request->input('per_page', 25);
        if ($perPage < 1 || $perPage > 200) {
            $perPage = 25;
        }

        $fees = $query->paginate($perPage);

        return response()->json($fees);
    }

    /**
     * Store a new fee.
     *
     * POST /admin/fees
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'billing_type'    => ['required', 'in:one_time,recurring'],
            'recurring_cycle' => ['nullable', 'in:monthly,yearly,term'],
            'sort_order'      => ['nullable', 'integer', 'min:0'],
            'is_active'       => ['boolean'],
        ]);

        // এককালীন হলে recurring_cycle null করে দিচ্ছি
        if ($data['billing_type'] === 'one_time') {
            $data['recurring_cycle'] = null;
        }

        if (!isset($data['sort_order'])) {
            $data['sort_order'] = 0;
        }

        $fee = Fee::create($data);

        return response()->json([
            'message' => 'Fee created successfully.',
            'data'    => $fee,
        ], 201);
    }

    /**
     * Show a single fee.
     *
     * GET /admin/fees/{fee}
     */
    public function show(Fee $fee)
    {
        return response()->json([
            'data' => $fee,
        ]);
    }

    /**
     * Update an existing fee.
     *
     * PUT/PATCH /admin/fees/{fee}
     */
    public function update(Request $request, Fee $fee)
    {
        $data = $request->validate([
            'name'            => ['sometimes', 'required', 'string', 'max:255'],
            'billing_type'    => ['sometimes', 'required', 'in:one_time,recurring'],
            'recurring_cycle' => ['nullable', 'in:monthly,yearly,term'],
            'sort_order'      => ['nullable', 'integer', 'min:0'],
            'is_active'       => ['boolean'],
        ]);

        // যদি billing_type আপডেট করা হয়, তাহলে recurring_cycle adjust করো
        if (array_key_exists('billing_type', $data)) {
            if ($data['billing_type'] === 'one_time') {
                $data['recurring_cycle'] = null;
            } else {
                // যদি recurring হয় কিন্তু recurring_cycle না পাঠায়,
                // আগে পুরনো ভ্যালু থাকলে সেটাই থাকবে, না থাকলে null
                if (!array_key_exists('recurring_cycle', $data)) {
                    $data['recurring_cycle'] = $fee->recurring_cycle ?? null;
                }
            }
        }

        $fee->fill($data);
        $fee->save();

        return response()->json([
            'message' => 'Fee updated successfully.',
            'data'    => $fee,
        ]);
    }

    /**
     * Delete a fee (if not in use).
     *
     * DELETE /admin/fees/{fee}
     */
    public function destroy(Fee $fee)
    {
        // ভবিষ্যতে session_fees তৈরি করলে এখানে use check করতে পারবে:
        // if ($fee->sessionFees()->exists()) {
        //     return response()->json([
        //         'message' => 'Cannot delete fee because it is used in session fees.',
        //     ], 422);
        // }

        $fee->delete();

        return response()->json([
            'message' => 'Fee deleted successfully.',
        ]);
    }
}