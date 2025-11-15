<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Subject;
use App\Models\Tenant\SessionSubject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    public function index(Request $req)
    {
        $q = Subject::query()
            ->when($req->filled('grade_id'), fn($qq) => $qq->where('grade_id', $req->integer('grade_id')))
            ->when($req->filled('only_active'), fn($qq) => $qq->where('is_active', filter_var($req->only_active, FILTER_VALIDATE_BOOL)))
            ->when($req->filled('q'), function ($qq) use ($req) {
                $term = $req->q;
                $qq->where(function ($w) use ($term) {
                    $w->where('name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%");
                });
            })
            ->orderBy('grade_id')
            ->orderBy('name');

        return $q->paginate($req->integer('per_page', 50));
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'grade_id'  => [
                'required',
                // 🔹 tenant connection ব্যবহার করে grades টেবিল চেক
                Rule::exists('tenant.grades', 'id'),
            ],
            'name'      => ['required', 'string', 'max:190'],
            'code'      => [
                'required',
                'string',
                'max:100',
                // 🔹 tenant.subjects এ unique
                Rule::unique('tenant.subjects')->where(
                    fn($q) => $q->where('grade_id', $req->grade_id)
                ),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $m = Subject::create($data);
        return response()->json($m, 201);
    }

    public function update(Request $req, Subject $subject)
    {
        $data = $req->validate([
            'grade_id'  => [
                'sometimes',
                // 🔹 update-এও tenant.grades এ exists
                Rule::exists('tenant.grades', 'id'),
            ],
            'name'      => ['sometimes', 'string', 'max:190'],
            'code'      => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('tenant.subjects')
                    ->ignore($subject->id)
                    ->where(
                        fn($q) =>
                        $q->where(
                            'grade_id',
                            $req->input('grade_id', $subject->grade_id)
                        )
                    ),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $subject->update($data);
        return $subject;
    }

    public function destroy(Subject $subject)
    {
        // সেফটি: সেশনে লিঙ্ক থাকলে ব্লক
        if (SessionSubject::where('subject_id', $subject->id)->exists()) {
            return response()->json([
                'message' => 'Cannot delete: subject is used in one or more sessions.'
            ], 409);
        }

        $subject->delete();
        return response()->noContent();
    }
}
