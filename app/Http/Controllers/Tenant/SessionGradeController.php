<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\SessionGrade;
use App\Models\Tenant\AcademicSession;
use Illuminate\Http\Request;

class SessionGradeController extends Controller
{
    // GET /api/tenant/sessions/{session}/classes
    public function index(AcademicSession $session, Request $req)
    {
        $q = SessionGrade::query()
            ->with(['grade', 'classTeacher'])
            ->forSession($session->id)
            ->filter(
                $req->integer('grade_id'),
                $req->input('shift'),
                $req->input('medium')
            );

        $data = $q->orderByDesc('id')->paginate((int) $req->input('per_page', 25));
        return response()->json(['data' => $data]);
    }

    // POST /api/tenant/sessions/{session}/classes
    public function store(AcademicSession $session, Request $req)
    {
        $val = $req->validate([
            'grade_id'         => ['required', 'integer', 'exists:tenant.grades,id'],
            'shift'            => ['nullable', 'string', 'max:50'],
            'medium'           => ['nullable', 'string', 'max:50'],
            'capacity'         => ['nullable', 'integer', 'min:0', 'max:65535'],
            'class_teacher_id' => ['nullable', 'integer', 'exists:tenant.employees,id'],
            'code'             => ['nullable', 'string', 'max:100'],
            'meta_json'        => ['nullable', 'array'],
        ]);

        $exists = SessionGrade::query()
            ->forSession($session->id)
            ->where('grade_id', $val['grade_id'])
            ->where('shift',  $val['shift']  ?? null)
            ->where('medium', $val['medium'] ?? null)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'This class is already opened for the session.'], 422);
        }

        $sg = SessionGrade::create($val + ['academic_session_id' => $session->id]);
        return response()->json(['data' => $sg->load(['grade', 'classTeacher'])], 201);
    }

    // POST /api/tenant/sessions/{session}/classes/bulk-open
    public function bulkOpen(AcademicSession $session, Request $req)
    {
        $val = $req->validate([
            'grade_ids'        => ['required', 'array', 'min:1'],
            'grade_ids.*'      => ['integer', 'exists:tenant.grades,id'],
            'shift'            => ['nullable', 'string', 'max:50'],
            'medium'           => ['nullable', 'string', 'max:50'],
            'capacity'         => ['nullable', 'integer', 'min:0', 'max:65535'],
            'class_teacher_id' => ['nullable', 'integer', 'exists:tenant.employees,id'],
        ]);

        $created = [];
        foreach ($val['grade_ids'] as $gid) {
            $dup = SessionGrade::query()
                ->forSession($session->id)
                ->where('grade_id', $gid)
                ->where('shift',  $val['shift']  ?? null)
                ->where('medium', $val['medium'] ?? null)
                ->exists();
            if ($dup) continue;

            $created[] = SessionGrade::create([
                'academic_session_id' => $session->id,
                'grade_id'            => $gid,
                'shift'               => $val['shift']  ?? null,
                'medium'              => $val['medium'] ?? null,
                'capacity'            => $val['capacity'] ?? null,
                'class_teacher_id'    => $val['class_teacher_id'] ?? null,
            ]);
        }

        return response()->json([
            'created_count' => count($created),
            'items' => collect($created)->load(['grade', 'classTeacher']),
        ], 201);
    }

    // PATCH /api/tenant/session-classes/{sessionGrade}
    public function update(SessionGrade $sessionGrade, Request $req)
    {
        $val = $req->validate([
            'shift'            => ['nullable', 'string', 'max:50'],
            'medium'           => ['nullable', 'string', 'max:50'],
            'capacity'         => ['nullable', 'integer', 'min:0', 'max:65535'],
            'class_teacher_id' => ['nullable', 'integer', 'exists:tenant.employees,id'],
            'code'             => ['nullable', 'string', 'max:100'],
            'meta_json'        => ['nullable', 'array'],
        ]);

        if (array_key_exists('shift', $val) || array_key_exists('medium', $val)) {
            $dup = SessionGrade::query()
                ->forSession($sessionGrade->academic_session_id)
                ->where('grade_id', $sessionGrade->grade_id)
                ->where('id', '!=', $sessionGrade->id)
                ->where('shift',  $val['shift']  ?? $sessionGrade->shift)
                ->where('medium', $val['medium'] ?? $sessionGrade->medium)
                ->exists();
            if ($dup) {
                return response()->json(['message' => 'Duplicate class for same session/grade/shift/medium.'], 422);
            }
        }

        $sessionGrade->update($val);
        return response()->json(['data' => $sessionGrade->load(['grade', 'classTeacher'])]);
    }

    // DELETE /api/tenant/session-classes/{sessionGrade}
    public function destroy(SessionGrade $sessionGrade)
    {
        $sessionGrade->delete();
        return response()->json(['message' => 'Deleted']);
    }
}