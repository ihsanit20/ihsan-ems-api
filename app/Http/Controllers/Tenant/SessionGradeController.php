<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\SessionGrade;
use App\Models\Tenant\AcademicSession;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class SessionGradeController extends Controller
{
    // GET /api/tenant/sessions/{session}/classes
    public function index(AcademicSession $session, Request $req)
    {
        $q = SessionGrade::query()
            ->with(['grade'])
            ->forSession($session->id)
            ->filter($req->integer('grade_id'));

        $data = $q->orderByDesc('id')
            ->paginate((int) $req->input('per_page', 25));

        return response()->json(['data' => $data]);
    }

    // POST /api/tenant/sessions/{session}/classes
    public function store(AcademicSession $session, Request $req)
    {
        $val = $req->validate([
            'grade_id' => ['required', 'integer', 'exists:tenant.grades,id'],
        ]);

        // prevent duplicate: (academic_session_id, grade_id) must be unique
        $exists = SessionGrade::query()
            ->forSession($session->id)
            ->where('grade_id', $val['grade_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This grade is already opened for the session.'
            ], 422);
        }

        $sg = SessionGrade::create([
            'academic_session_id' => $session->id,
            'grade_id'            => $val['grade_id'],
        ]);

        return response()->json([
            'data' => $sg->load(['grade'])
        ], 201);
    }

    // POST /api/tenant/sessions/{session}/classes/bulk-open
    public function bulkOpen(AcademicSession $session, Request $req)
    {
        $val = $req->validate([
            'grade_ids'   => ['required', 'array', 'min:1'],
            'grade_ids.*' => ['integer', 'exists:tenant.grades,id'],
        ]);

        $created = [];

        foreach ($val['grade_ids'] as $gid) {
            $dup = SessionGrade::query()
                ->forSession($session->id)
                ->where('grade_id', $gid)
                ->exists();

            if ($dup) continue;

            $created[] = SessionGrade::create([
                'academic_session_id' => $session->id,
                'grade_id'            => $gid,
            ]);
        }

        if (empty($created)) {
            return response()->json([
                'created_count' => 0,
                'items'         => [],
            ], 201);
        }

        // Make an Eloquent collection so we can eager load
        $eloquent = (new EloquentCollection($created))->load('grade');

        return response()->json([
            'created_count' => $eloquent->count(),
            'items'         => $eloquent,
        ], 201);
    }

    // PATCH /api/tenant/session-classes/{sessionGrade}
    // নোট: নতুন স্কিমায় আপডেটযোগ্য একমাত্র ফিল্ড বাস্তবে 'grade_id' হতে পারে।
    // চাইলে একেবারেই disallow করতে পারো; এখানে guarded update হিসেবে শুধু grade_id রাখা হলো।
    public function update(SessionGrade $sessionGrade, Request $req)
    {
        $val = $req->validate([
            'grade_id' => ['required', 'integer', 'exists:tenant.grades,id'],
        ]);

        // unique check: same (session, grade) pair must not collide with others
        $dup = SessionGrade::query()
            ->forSession($sessionGrade->academic_session_id)
            ->where('grade_id', $val['grade_id'])
            ->where('id', '!=', $sessionGrade->id)
            ->exists();

        if ($dup) {
            return response()->json([
                'message' => 'Duplicate class for the same session and grade.'
            ], 422);
        }

        $sessionGrade->update([
            'grade_id' => $val['grade_id'],
        ]);

        return response()->json([
            'data' => $sessionGrade->load(['grade'])
        ]);
    }

    // DELETE /api/tenant/session-classes/{sessionGrade}
    public function destroy(SessionGrade $sessionGrade)
    {
        $sessionGrade->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
