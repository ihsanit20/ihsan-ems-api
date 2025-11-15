<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Section;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    /**
     * List sections for a given session_grade_id (with optional search).
     *
     * GET /api/v1/sections?session_grade_id=1&search=A
     */
    public function index(Request $request)
    {
        $sessionGradeId = (int) $request->query('session_grade_id', 0);

        if (! $sessionGradeId) {
            return response()->json([
                'message' => 'session_grade_id parameter is required.',
            ], 422);
        }

        $search = trim((string) $request->query('search', ''));

        $query = Section::query()
            ->where('session_grade_id', $sessionGradeId);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // 🔁 এখন pagination নাই, শুধু ordered list
        $sections = $query->ordered()->get();

        return response()->json($sections);
    }

    /**
     * Create a new section.
     *
     * POST /api/v1/sections
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'session_grade_id' => ['required', 'integer'],
            'name'             => ['required', 'string', 'max:100'],
            'code'             => ['nullable', 'string', 'max:50'],
            'capacity'         => ['nullable', 'integer', 'min:0'],
            'class_teacher_id' => ['nullable', 'integer'],
            'sort_order'       => ['nullable', 'integer', 'min:0'],
        ]);

        $section = Section::create($data);

        return response()->json($section, 201);
    }

    /**
     * Show a single section.
     *
     * GET /api/v1/sections/{section}
     */
    public function show(Section $section)
    {
        return response()->json($section);
    }

    /**
     * Update a section.
     *
     * PUT/PATCH /api/v1/sections/{section}
     */
    public function update(Request $request, Section $section)
    {
        $data = $request->validate([
            // session_grade_id সাধারণত change করতে হয় না, তাই এখানে রাখিনি।
            'name'             => ['sometimes', 'required', 'string', 'max:100'],
            'code'             => ['sometimes', 'nullable', 'string', 'max:50'],
            'capacity'         => ['sometimes', 'nullable', 'integer', 'min:0'],
            'class_teacher_id' => ['sometimes', 'nullable', 'integer'],
            'sort_order'       => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $section->update($data);

        return response()->json($section);
    }

    /**
     * Delete a section.
     *
     * DELETE /api/v1/sections/{section}
     */
    public function destroy(Section $section)
    {
        $section->delete();

        return response()->json(null, 204);
    }
}