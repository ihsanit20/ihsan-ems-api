<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\SessionSubject;
use App\Models\Tenant\Subject;
use App\Models\Tenant\SessionGrade;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SessionSubjectController extends Controller
{
    public function index(Request $req)
    {
        $q = SessionSubject::query()
            ->with(['subject', 'academicSession'])
            ->when(
                $req->filled('session_id'),
                fn($qq) => $qq->where('session_id', $req->integer('session_id'))
            )
            ->when(
                $req->filled('subject_id'),
                fn($qq) => $qq->where('subject_id', $req->integer('subject_id'))
            )
            ->orderBy('session_id')
            ->orderBy('sort_order');

        return $q->paginate($req->integer('per_page', 50));
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            // ⬇⬇⬇ এখানে connection সহ
            'session_id'  => ['required', 'exists:tenant.academic_sessions,id'],
            'subject_id'  => [
                'required',
                'exists:tenant.subjects,id',
                Rule::unique('tenant.session_subjects')
                    ->where(
                        fn($q) =>
                        $q->where('session_id', $req->session_id)
                            ->where('subject_id', $req->subject_id)
                    ),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'book_name'   => ['nullable', 'string', 'max:190'],
        ]);

        // 🔒 সেফটি: subject-এর grade কি সেশনে খোলা আছে? (Tenant models দিয়ে)
        $gradeId = Subject::query()
            ->whereKey($data['subject_id'])
            ->value('grade_id');

        if ($gradeId) {
            $opened = SessionGrade::query()
                ->where('academic_session_id', $data['session_id'])
                ->where('grade_id', $gradeId)
                ->exists();

            if (! $opened) {
                return response()->json([
                    'message' => 'This subject\'s grade is not opened in the selected session.',
                ], 422);
            }
        }

        $m = SessionSubject::create($data + [
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json(
            $m->load(['subject', 'academicSession']),
            201
        );
    }

    public function update(Request $req, SessionSubject $sessionSubject)
    {
        $data = $req->validate([
            'session_id'  => ['sometimes', 'exists:tenant.academic_sessions,id'],
            'subject_id'  => [
                'sometimes',
                'exists:tenant.subjects,id',
                Rule::unique('tenant.session_subjects')
                    ->ignore($sessionSubject->id)
                    ->where(
                        fn($q) =>
                        $q->where(
                            'session_id',
                            $req->input('session_id', $sessionSubject->session_id)
                        )
                    ),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'book_name'   => ['nullable', 'string', 'max:190'],
        ]);

        // সেফটি — যদি session_id/subject_id বদলায়
        if (isset($data['session_id']) || isset($data['subject_id'])) {
            $sessionId = $data['session_id'] ?? $sessionSubject->session_id;
            $subjectId = $data['subject_id'] ?? $sessionSubject->subject_id;

            $gradeId = Subject::query()->whereKey($subjectId)->value('grade_id');

            if ($gradeId) {
                $opened = SessionGrade::query()
                    ->where('academic_session_id', $sessionId)
                    ->where('grade_id', $gradeId)
                    ->exists();

                if (! $opened) {
                    return response()->json([
                        'message' => 'This subject\'s grade is not opened in the selected session.',
                    ], 422);
                }
            }
        }

        $sessionSubject->update($data);

        return $sessionSubject->fresh(['subject', 'academicSession']);
    }

    public function destroy(SessionSubject $sessionSubject)
    {
        $sessionSubject->delete();

        return response()->noContent();
    }
}
