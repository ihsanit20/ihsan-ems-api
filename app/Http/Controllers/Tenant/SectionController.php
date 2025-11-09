<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\SessionGrade;
use App\Models\Tenant\Section;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    // GET /api/tenant/session-classes/{sessionGrade}/sections
    public function index(SessionGrade $sessionGrade, Request $req)
    {
        $q = $sessionGrade->sections()->with('classTeacher');

        if ($req->filled('search')) {
            $s = trim((string) $req->input('search'));
            $q->where(function ($qq) use ($s) {
                $qq->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            });
        }

        $data = $q->orderBy('sort_order')->orderBy('id')
            ->paginate((int) $req->input('per_page', 50));

        return response()->json(['data' => $data]);
    }

    // POST /api/tenant/session-classes/{sessionGrade}/sections
    public function store(SessionGrade $sessionGrade, Request $req)
    {
        $val = $req->validate([
            'name'             => ['required', 'string', 'max:50'],
            'code'             => ['nullable', 'string', 'max:50'],
            'capacity'         => ['nullable', 'integer', 'min:0', 'max:65535'],
            // FIX: employees নেই, users ব্যবহার করুন
            'class_teacher_id' => ['nullable', 'integer', 'exists:tenant.users,id'],
            'sort_order'       => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $exists = Section::where('session_grade_id', $sessionGrade->id)
            ->where('name', $val['name'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Section with this name already exists in this class.'
            ], 422);
        }

        // $val এ sort_order থাকলে সেটাও যাবে (array union নয়, merge ব্যবহার আরও স্পষ্ট হলেও এখানে প্রয়োজন নেই)
        $section = Section::create($val + ['session_grade_id' => $sessionGrade->id]);

        return response()->json([
            'data' => $section->load('classTeacher')
        ], 201);
    }

    // POST /api/tenant/session-classes/{sessionGrade}/sections/bulk
    public function bulkStore(SessionGrade $sessionGrade, Request $req)
    {
        $val = $req->validate([
            'names'            => ['required', 'array', 'min:1'],
            'names.*'          => ['string', 'max:50'],
            'capacity'         => ['nullable', 'integer', 'min:0', 'max:65535'],
            // FIX: employees নেই, users ব্যবহার করুন
            'class_teacher_id' => ['nullable', 'integer', 'exists:tenant.users,id'],
        ]);

        $created = [];

        foreach ($val['names'] as $i => $raw) {
            $name = trim($raw);
            if ($name === '') continue;

            $dup = Section::where('session_grade_id', $sessionGrade->id)
                ->where('name', $name)
                ->exists();

            if ($dup) continue;

            $created[] = Section::create([
                'session_grade_id' => $sessionGrade->id,
                'name'             => $name,
                'capacity'         => $val['capacity'] ?? null,
                'class_teacher_id' => $val['class_teacher_id'] ?? null,
                'sort_order'       => $i,
            ]);
        }

        return response()->json([
            'created_count' => count($created),
            'items' => collect($created)->load('classTeacher'),
        ], 201);
    }

    // PATCH /api/tenant/sections/{section}
    public function update(Section $section, Request $req)
    {
        $val = $req->validate([
            'name'             => ['sometimes', 'string', 'max:50'],
            'code'             => ['nullable', 'string', 'max:50'],
            'capacity'         => ['nullable', 'integer', 'min:0', 'max:65535'],
            // FIX: employees নেই, users ব্যবহার করুন
            'class_teacher_id' => ['nullable', 'integer', 'exists:tenant.users,id'],
            'sort_order'       => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        if (array_key_exists('name', $val)) {
            $dup = Section::where('session_grade_id', $section->session_grade_id)
                ->where('name', $val['name'])
                ->where('id', '!=', $section->id)
                ->exists();

            if ($dup) {
                return response()->json([
                    'message' => 'Another section with this name already exists in this class.'
                ], 422);
            }
        }

        $section->update($val);

        return response()->json([
            'data' => $section->load('classTeacher')
        ]);
    }

    // DELETE /api/tenant/sections/{section}
    public function destroy(Section $section)
    {
        $section->delete();
        return response()->json(['message' => 'Deleted']);
    }

    // PATCH /api/tenant/session-classes/{sessionGrade}/sections/reorder
    public function reorder(SessionGrade $sessionGrade, Request $req)
    {
        $val = $req->validate([
            'items'              => ['required', 'array', 'min:1'],
            'items.*.id'         => ['required', 'integer', 'exists:tenant.sections,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        foreach ($val['items'] as $item) {
            Section::where('id', $item['id'])
                ->where('session_grade_id', $sessionGrade->id)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Reordered']);
    }
}
