<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\SessionGrade;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SessionGradeController extends Controller
{
    /**
     * GET /api/v1/session-grades
     * Query (optional):
     *  - academic_session_id, grade_id, class_teacher_id
     *  - with=grade,academicSession,sections,classTeacher
     *  - paginate=1|0, per_page
     */
    public function index(Request $request)
    {
        $q = SessionGrade::query()
            ->when($request->filled('academic_session_id'), fn($qry) =>
            $qry->where('academic_session_id', (int) $request->academic_session_id))
            ->when($request->filled('grade_id'), fn($qry) =>
            $qry->where('grade_id', (int) $request->grade_id))
            ->when($request->filled('class_teacher_id'), fn($qry) =>
            $qry->where('class_teacher_id', (int) $request->class_teacher_id));

        // eager loads
        $with = collect(
            is_array($request->with) ? $request->with : (is_string($request->with) ? explode(',', $request->with) : [])
        )
            ->map(fn($w) => trim($w))
            ->intersect(['grade', 'academicSession', 'sections', 'classTeacher'])
            ->values()
            ->all();

        if ($with) {
            $q->with($with);
        }

        $q->latest('id');

        if ($request->boolean('paginate', true)) {
            $perPage = max(1, (int) $request->get('per_page', 20));
            return $q->paginate($perPage);
        }

        return response()->json($q->get());
    }

    /**
     * GET /api/v1/session-grades/{sessionGrade}
     * ?with=grade,academicSession,sections,classTeacher
     */
    public function show(SessionGrade $sessionGrade, Request $request)
    {
        $with = collect(
            is_array($request->with) ? $request->with : (is_string($request->with) ? explode(',', $request->with) : [])
        )
            ->map(fn($w) => trim($w))
            ->intersect(['grade', 'academicSession', 'sections', 'classTeacher'])
            ->values()
            ->all();

        if ($with) {
            $sessionGrade->load($with);
        }

        return response()->json($sessionGrade);
    }

    /**
     * POST /api/v1/session-grades
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'grade_id'            => [
                'required',
                'integer',
                'exists:grades,id',
                // composite unique: academic_session_id + grade_id
                Rule::unique('session_grades', 'grade_id')->where(function ($q) use ($request) {
                    return $q->where('academic_session_id', (int) $request->academic_session_id);
                }),
            ],
            'capacity'            => ['required', 'integer', 'min:0', 'max:65535'],
            'class_teacher_id'    => ['nullable', 'integer', 'exists:users,id'],
            'meta_json'           => ['nullable', 'array'],
        ]);

        $sessionGrade = SessionGrade::create($data);

        return response()->json($sessionGrade, 201);
    }

    /**
     * PUT/PATCH /api/v1/session-grades/{sessionGrade}
     */
    public function update(Request $request, SessionGrade $sessionGrade)
    {
        $data = $request->validate([
            'academic_session_id' => ['sometimes', 'required', 'integer', 'exists:academic_sessions,id'],
            'grade_id'            => [
                'sometimes',
                'required',
                'integer',
                'exists:grades,id',
                Rule::unique('session_grades', 'grade_id')
                    ->ignore($sessionGrade->id)
                    ->where(function ($q) use ($request, $sessionGrade) {
                        // when absent, fall back to current value to validate pair
                        $sessionId = (int) ($request->academic_session_id ?? $sessionGrade->academic_session_id);
                        return $q->where('academic_session_id', $sessionId);
                    }),
            ],
            'capacity'            => ['sometimes', 'required', 'integer', 'min:0', 'max:65535'],
            'class_teacher_id'    => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'meta_json'           => ['sometimes', 'nullable', 'array'],
        ]);

        $sessionGrade->fill($data)->save();

        return response()->json($sessionGrade);
    }

    /**
     * DELETE /api/v1/session-grades/{sessionGrade}
     */
    public function destroy(SessionGrade $sessionGrade)
    {
        $sessionGrade->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
