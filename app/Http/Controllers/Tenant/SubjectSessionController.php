<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\SubjectSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubjectSessionController extends Controller
{
    public function index(Request $req)
    {
        $q = SubjectSession::query()
            ->with(['subject', 'academicSession'])
            ->when($req->filled('session_id'), fn($qq) => $qq->where('session_id', $req->integer('session_id')))
            ->when($req->filled('subject_id'), fn($qq) => $qq->where('subject_id', $req->integer('subject_id')))
            ->orderBy('session_id')->orderBy('order_index');

        return $q->paginate($req->integer('per_page', 50));
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'session_id'  => ['required', 'exists:academic_sessions,id'],
            'subject_id'  => [
                'required',
                'exists:subjects,id',
                Rule::unique('subject_sessions')->where(
                    fn($q) =>
                    $q->where('session_id', $req->session_id)
                        ->where('subject_id', $req->subject_id)
                )
            ],
            'order_index' => ['sometimes', 'integer', 'min:0'],
            'book_name'   => ['nullable', 'string', 'max:190'],
        ]);

        // (ঐচ্ছিক কিন্তু পরামর্শযোগ্য) সেফটি: subject-এর grade কি সেশনে খোলা আছে?
        // যদি session_grades টেবিল থাকে:
        $gradeId = DB::table('subjects')->where('id', $data['subject_id'])->value('grade_id');
        if ($gradeId) {
            $opened = DB::table('session_grades')
                ->where('academic_session_id', $data['session_id'])
                ->where('grade_id', $gradeId)
                ->exists();

            if (!$opened) {
                return response()->json([
                    'message' => 'This subject\'s grade is not opened in the selected session.'
                ], 422);
            }
        }

        $m = SubjectSession::create($data + [
            'order_index' => $data['order_index'] ?? 0,
        ]);

        return response()->json($m->load(['subject', 'academicSession']), 201);
    }

    public function update(Request $req, SubjectSession $subjectSession)
    {
        $data = $req->validate([
            'session_id'  => ['sometimes', 'exists:academic_sessions,id'],
            'subject_id'  => [
                'sometimes',
                'exists:subjects,id',
                Rule::unique('subject_sessions')
                    ->ignore($subjectSession->id)
                    ->where(fn($q) => $q->where('session_id', $req->input('session_id', $subjectSession->session_id)))
            ],
            'order_index' => ['sometimes', 'integer', 'min:0'],
            'book_name'   => ['nullable', 'string', 'max:190'],
        ]);

        // সেফটি (উপরের মতো) — যদি session_id/subject_id বদলায়
        if (isset($data['session_id']) || isset($data['subject_id'])) {
            $sessionId = $data['session_id'] ?? $subjectSession->session_id;
            $subjectId = $data['subject_id'] ?? $subjectSession->subject_id;

            $gradeId = DB::table('subjects')->where('id', $subjectId)->value('grade_id');
            if ($gradeId) {
                $opened = DB::table('session_grades')
                    ->where('academic_session_id', $sessionId)
                    ->where('grade_id', $gradeId)
                    ->exists();

                if (!$opened) {
                    return response()->json([
                        'message' => 'This subject\'s grade is not opened in the selected session.'
                    ], 422);
                }
            }
        }

        $subjectSession->update($data);
        return $subjectSession->fresh(['subject', 'academicSession']);
    }

    public function destroy(SubjectSession $subjectSession)
    {
        $subjectSession->delete();
        return response()->noContent();
    }
}
