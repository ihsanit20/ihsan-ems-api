<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    /**
     * List students with filters and pagination.
     *
     * GET /api/v1/students
     * Query params:
     * - q (search: name, code, phone)
     * - status (active, inactive, passed, tc_issued, dropped)
     * - gender (male, female, other)
     * - residential_type (residential, new_musafir, non_residential)
     * - academic_session_id (filter by enrollment)
     * - session_grade_id (filter by enrollment)
     * - section_id (filter by enrollment)
     * - per_page (default: 25)
     * - with (eager load: user, enrollments, enrollments.academicSession, enrollments.sessionGrade, enrollments.section)
     */
    public function index(Request $request)
    {
        $query = Student::query();

        // Eager loading
        $withParam = $request->input('with');
        $with = collect(is_array($withParam) ? $withParam : (is_string($withParam) ? explode(',', $withParam) : []))
            ->map(fn($w) => trim((string) $w))
            ->filter(fn($w) => in_array($w, ['user', 'enrollments', 'enrollments.academicSession', 'enrollments.sessionGrade', 'enrollments.section']))
            ->values()
            ->all();

        if ($with) {
            $query->with($with);
        }

        // Search
        if ($search = trim((string) $request->input('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name_bn', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('student_code', 'like', "%{$search}%")
                    ->orWhere('student_phone', 'like', "%{$search}%")
                    ->orWhere('father_name', 'like', "%{$search}%")
                    ->orWhere('father_phone', 'like', "%{$search}%")
                    ->orWhere('guardian_phone', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Gender filter
        if ($request->filled('gender')) {
            $query->where('gender', $request->input('gender'));
        }

        // Residential type filter
        if ($request->filled('residential_type')) {
            $query->where('residential_type', $request->input('residential_type'));
        }

        // Filter by enrollment (current session/grade/section)
        if ($request->filled('academic_session_id') || $request->filled('session_grade_id') || $request->filled('section_id')) {
            $query->whereHas('enrollments', function ($q) use ($request) {
                if ($request->filled('academic_session_id')) {
                    $q->where('academic_session_id', $request->integer('academic_session_id'));
                }
                if ($request->filled('session_grade_id')) {
                    $q->where('session_grade_id', $request->integer('session_grade_id'));
                }
                if ($request->filled('section_id')) {
                    $q->where('section_id', $request->integer('section_id'));
                }
            });
        }

        // Sorting
        $allowedSort = ['id', 'student_code', 'name_bn', 'name_en', 'status', 'created_at', 'updated_at'];
        $sortBy = in_array($request->get('sort_by'), $allowedSort, true) ? $request->get('sort_by') : 'id';
        $sortDir = strtolower($request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortDir);

        // Pagination
        if ($request->boolean('paginate', true)) {
            $perPage = max(1, min((int) $request->get('per_page', 25), 200));
            return response()->json($query->paginate($perPage));
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * Show single student with full details.
     *
     * GET /api/v1/students/{student}
     */
    public function show(Student $student, Request $request)
    {
        // Load relationships if requested
        $with = ['user'];

        if ($request->boolean('with_enrollments', true)) {
            $with[] = 'enrollments.academicSession';
            $with[] = 'enrollments.sessionGrade.grade.level';
            $with[] = 'enrollments.section';
        }

        $student->loadMissing($with);

        return response()->json($student);
    }

    /**
     * Create a new student.
     *
     * POST /api/v1/students
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'student_code'     => ['required', 'string', 'max:191', 'unique:tenant.students,student_code'],

            // Basic info
            'name_bn'          => ['required', 'string', 'max:255'],
            'name_en'          => ['nullable', 'string', 'max:255'],
            'gender'           => ['nullable', 'in:male,female,other'],
            'date_of_birth'    => ['nullable', 'date', 'before:today'],
            'student_phone'    => ['nullable', 'string', 'max:20'],
            'student_email'    => ['nullable', 'email', 'max:255'],

            // Father info
            'father_name'      => ['nullable', 'string', 'max:255'],
            'father_phone'     => ['nullable', 'string', 'max:20'],
            'father_occupation' => ['nullable', 'string', 'max:255'],

            // Mother info
            'mother_name'      => ['nullable', 'string', 'max:255'],
            'mother_phone'     => ['nullable', 'string', 'max:20'],
            'mother_occupation' => ['nullable', 'string', 'max:255'],

            // Guardian info
            'guardian_type'    => ['nullable', 'in:father,mother,other'],
            'guardian_name'    => ['nullable', 'string', 'max:255'],
            'guardian_phone'   => ['nullable', 'string', 'max:20'],
            'guardian_relation' => ['nullable', 'string', 'max:100'],

            // Address (JSON)
            'present_address'  => ['nullable', 'array'],
            'present_address.division_id'  => ['nullable', 'integer', 'exists:tenant.divisions,id'],
            'present_address.district_id'  => ['nullable', 'integer', 'exists:tenant.districts,id'],
            'present_address.area_id'      => ['nullable', 'integer', 'exists:tenant.areas,id'],
            'present_address.village_house_holding' => ['nullable', 'string', 'max:500'],

            'permanent_address' => ['nullable', 'array'],
            'permanent_address.division_id'  => ['nullable', 'integer', 'exists:tenant.divisions,id'],
            'permanent_address.district_id'  => ['nullable', 'integer', 'exists:tenant.districts,id'],
            'permanent_address.area_id'      => ['nullable', 'integer', 'exists:tenant.areas,id'],
            'permanent_address.village_house_holding' => ['nullable', 'string', 'max:500'],

            // Residential type
            'residential_type' => ['nullable', 'in:residential,new_musafir,non_residential'],

            // Status
            'status'           => ['nullable', 'in:active,inactive,passed,tc_issued,dropped'],

            // Photo
            'photo_path'       => ['nullable', 'string', 'max:255'],

            // Meta
            'meta_json'        => ['nullable', 'array'],

            // Optional: Create user account for student
            'create_user_account' => ['nullable', 'boolean'],
            'user_password'       => ['nullable', 'string', 'min:4'],
        ]);

        // Default status
        $data['status'] = $data['status'] ?? 'active';

        DB::beginTransaction();
        try {
            // Create student
            $student = Student::create($data);

            // Optionally create user account
            if ($request->boolean('create_user_account') && $request->filled('user_password')) {
                $user = User::create([
                    'name'     => $data['name_bn'],
                    'phone'    => $data['student_phone'] ?? $data['guardian_phone'] ?? $data['father_phone'],
                    'email'    => $data['student_email'],
                    'password' => Hash::make($request->input('user_password')),
                    'role'     => 'Student',
                ]);

                $student->update(['user_id' => $user->id]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Student created successfully.',
                'data'    => $student->fresh()->load('user'),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update student information.
     *
     * PUT/PATCH /api/v1/students/{student}
     */
    public function update(Request $request, Student $student)
    {
        $data = $request->validate([
            'student_code'     => [
                'sometimes',
                'required',
                'string',
                'max:191',
                Rule::unique('tenant.students', 'student_code')->ignore($student->id)
            ],

            // Basic info
            'name_bn'          => ['sometimes', 'required', 'string', 'max:255'],
            'name_en'          => ['nullable', 'string', 'max:255'],
            'gender'           => ['nullable', 'in:male,female,other'],
            'date_of_birth'    => ['nullable', 'date', 'before:today'],
            'student_phone'    => ['nullable', 'string', 'max:20'],
            'student_email'    => ['nullable', 'email', 'max:255'],

            // Father info
            'father_name'      => ['nullable', 'string', 'max:255'],
            'father_phone'     => ['nullable', 'string', 'max:20'],
            'father_occupation' => ['nullable', 'string', 'max:255'],

            // Mother info
            'mother_name'      => ['nullable', 'string', 'max:255'],
            'mother_phone'     => ['nullable', 'string', 'max:20'],
            'mother_occupation' => ['nullable', 'string', 'max:255'],

            // Guardian info
            'guardian_type'    => ['nullable', 'in:father,mother,other'],
            'guardian_name'    => ['nullable', 'string', 'max:255'],
            'guardian_phone'   => ['nullable', 'string', 'max:20'],
            'guardian_relation' => ['nullable', 'string', 'max:100'],

            // Address (JSON)
            'present_address'  => ['nullable', 'array'],
            'permanent_address' => ['nullable', 'array'],

            // Residential type
            'residential_type' => ['nullable', 'in:residential,new_musafir,non_residential'],

            // Status
            'status'           => ['nullable', 'in:active,inactive,passed,tc_issued,dropped'],

            // Photo
            'photo_path'       => ['nullable', 'string', 'max:255'],

            // Meta
            'meta_json'        => ['nullable', 'array'],
        ]);

        $student->update($data);

        return response()->json([
            'message' => 'Student updated successfully.',
            'data'    => $student->fresh(),
        ]);
    }

    /**
     * Delete student (soft delete by status change or hard delete).
     *
     * DELETE /api/v1/students/{student}
     */
    public function destroy(Request $request, Student $student)
    {
        // Check if student has enrollments
        if ($student->enrollments()->exists() && !$request->boolean('force', false)) {
            return response()->json([
                'message' => 'Cannot delete student with existing enrollments. Use force=1 to override or change status to inactive.',
            ], 409);
        }

        // Hard delete if forced
        if ($request->boolean('force', false)) {
            $student->enrollments()->delete();
            $student->delete();
        } else {
            // Soft delete by status change
            $student->update(['status' => 'inactive']);
        }

        return response()->json([
            'message' => 'Student deleted successfully.',
        ]);
    }

    /**
     * Bulk update student status.
     *
     * POST /api/v1/students/bulk-status
     * Body: { student_ids: [1,2,3], status: "passed" }
     */
    public function bulkUpdateStatus(Request $request)
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:tenant.students,id'],
            'status' => ['required', 'in:active,inactive,passed,tc_issued,dropped'],
        ]);

        $updated = Student::whereIn('id', $data['student_ids'])
            ->update(['status' => $data['status']]);

        return response()->json([
            'message' => "Updated {$updated} student(s) status to {$data['status']}.",
            'count' => $updated,
        ]);
    }

    /**
     * Get student enrollment history.
     *
     * GET /api/v1/students/{student}/enrollments
     */
    public function enrollments(Student $student)
    {
        $enrollments = $student->enrollments()
            ->with(['academicSession', 'sessionGrade.grade.level', 'section'])
            ->orderByDesc('academic_session_id')
            ->get();

        return response()->json($enrollments);
    }

    /**
     * Create user account for student.
     *
     * POST /api/v1/students/{student}/create-account
     * Body: { phone: "017...", email: "...", password: "..." }
     */
    public function createUserAccount(Request $request, Student $student)
    {
        if ($student->user_id) {
            return response()->json([
                'message' => 'Student already has a user account.',
            ], 409);
        }

        $data = $request->validate([
            'phone'    => ['required', 'string', 'max:32', 'unique:tenant.users,phone'],
            'email'    => ['nullable', 'email', 'max:191', 'unique:tenant.users,email'],
            'password' => ['required', 'string', 'min:4'],
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name'     => $student->name_bn,
                'phone'    => $data['phone'],
                'email'    => $data['email'] ?? null,
                'password' => Hash::make($data['password']),
                'role'     => 'Student',
            ]);

            $student->update(['user_id' => $user->id]);

            DB::commit();

            return response()->json([
                'message' => 'User account created successfully.',
                'data'    => $user,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Upload student photo.
     *
     * POST /api/v1/students/{student}/upload-photo
     * Body: multipart/form-data with photo file
     */
    public function uploadPhoto(Request $request, Student $student)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:2048'], // 2MB
        ]);

        $file = $request->file('photo');
        $path = $file->store('students/photos', 'public');

        // Delete old photo if exists
        if ($student->photo_path) {
            Storage::disk('public')->delete($student->photo_path);
        }

        $student->update(['photo_path' => $path]);

        return response()->json([
            'message' => 'Photo uploaded successfully.',
            'photo_path' => $path,
            'photo_url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Get student statistics.
     *
     * GET /api/v1/students/stats
     */
    public function stats(Request $request)
    {
        $query = Student::query();

        // Filter by session if provided
        if ($request->filled('academic_session_id')) {
            $query->whereHas('enrollments', function ($q) use ($request) {
                $q->where('academic_session_id', $request->integer('academic_session_id'));
            });
        }

        $stats = [
            'total' => (clone $query)->count(),
            'by_status' => [
                'active' => (clone $query)->where('status', 'active')->count(),
                'inactive' => (clone $query)->where('status', 'inactive')->count(),
                'passed' => (clone $query)->where('status', 'passed')->count(),
                'tc_issued' => (clone $query)->where('status', 'tc_issued')->count(),
                'dropped' => (clone $query)->where('status', 'dropped')->count(),
            ],
            'by_gender' => [
                'male' => (clone $query)->where('gender', 'male')->count(),
                'female' => (clone $query)->where('gender', 'female')->count(),
                'other' => (clone $query)->where('gender', 'other')->count(),
            ],
            'by_residential_type' => [
                'residential' => (clone $query)->where('residential_type', 'residential')->count(),
                'new_musafir' => (clone $query)->where('residential_type', 'new_musafir')->count(),
                'non_residential' => (clone $query)->where('residential_type', 'non_residential')->count(),
            ],
        ];

        return response()->json($stats);
    }

    /**
     * Transfer student to another grade/section.
     *
     * POST /api/v1/students/{student}/transfer
     * Body: {
     *   academic_session_id: 1,
     *   session_grade_id: 2,
     *   section_id: 3,
     *   roll_no: "15",
     *   remarks: "Transferred from..."
     * }
     */
    public function transfer(Request $request, Student $student)
    {
        $data = $request->validate([
            'academic_session_id' => ['required', 'integer', 'exists:tenant.academic_sessions,id'],
            'session_grade_id'    => ['required', 'integer', 'exists:tenant.session_grades,id'],
            'section_id'          => ['nullable', 'integer', 'exists:tenant.sections,id'],
            'roll_no'             => ['nullable', 'string', 'max:50'],
            'remarks'             => ['nullable', 'string', 'max:500'],
        ]);

        DB::beginTransaction();
        try {
            // Check if enrollment already exists
            $existing = StudentEnrollment::where('student_id', $student->id)
                ->where('academic_session_id', $data['academic_session_id'])
                ->first();

            if ($existing) {
                // Update existing enrollment
                $existing->update([
                    'session_grade_id' => $data['session_grade_id'],
                    'section_id' => $data['section_id'] ?? null,
                    'roll_no' => $data['roll_no'] ?? $existing->roll_no,
                    'remarks' => $data['remarks'] ?? $existing->remarks,
                ]);

                $enrollment = $existing;
            } else {
                // Create new enrollment
                $enrollment = StudentEnrollment::create([
                    'student_id' => $student->id,
                    'academic_session_id' => $data['academic_session_id'],
                    'session_grade_id' => $data['session_grade_id'],
                    'section_id' => $data['section_id'] ?? null,
                    'roll_no' => $data['roll_no'] ?? null,
                    'admission_type' => 'transfer_in',
                    'status' => 'active',
                    'remarks' => $data['remarks'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Student transferred successfully.',
                'data' => $enrollment->load(['academicSession', 'sessionGrade', 'section']),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Promote students to next grade.
     *
     * POST /api/v1/students/bulk-promote
     * Body: {
     *   student_ids: [1,2,3],
     *   from_session_id: 1,
     *   to_session_id: 2,
     *   to_session_grade_id: 3,
     *   to_section_id: null
     * }
     */
    public function bulkPromote(Request $request)
    {
        $data = $request->validate([
            'student_ids'         => ['required', 'array', 'min:1'],
            'student_ids.*'       => ['integer', 'exists:tenant.students,id'],
            'from_session_id'     => ['required', 'integer', 'exists:tenant.academic_sessions,id'],
            'to_session_id'       => ['required', 'integer', 'exists:tenant.academic_sessions,id'],
            'to_session_grade_id' => ['required', 'integer', 'exists:tenant.session_grades,id'],
            'to_section_id'       => ['nullable', 'integer', 'exists:tenant.sections,id'],
        ]);

        DB::beginTransaction();
        try {
            $promoted = 0;

            foreach ($data['student_ids'] as $studentId) {
                // Check if student has enrollment in from_session
                $oldEnrollment = StudentEnrollment::where('student_id', $studentId)
                    ->where('academic_session_id', $data['from_session_id'])
                    ->first();

                if (!$oldEnrollment) {
                    continue;
                }

                // Update old enrollment status
                $oldEnrollment->update(['status' => 'promoted']);

                // Create new enrollment
                StudentEnrollment::create([
                    'student_id' => $studentId,
                    'academic_session_id' => $data['to_session_id'],
                    'session_grade_id' => $data['to_session_grade_id'],
                    'section_id' => $data['to_section_id'] ?? null,
                    'admission_type' => 'promotion',
                    'status' => 'active',
                    'remarks' => "Promoted from session {$data['from_session_id']}",
                ]);

                $promoted++;
            }

            DB::commit();

            return response()->json([
                'message' => "Promoted {$promoted} student(s) successfully.",
                'count' => $promoted,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Issue TC (Transfer Certificate) for student.
     *
     * POST /api/v1/students/{student}/issue-tc
     * Body: { remarks: "TC issued on..." }
     */
    public function issueTC(Request $request, Student $student)
    {
        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $student->update([
            'status' => 'tc_issued',
            'meta_json' => array_merge($student->meta_json ?? [], [
                'tc_issued_at' => now()->toDateTimeString(),
                'tc_remarks' => $data['remarks'] ?? null,
            ]),
        ]);

        // Update all active enrollments
        $student->enrollments()
            ->where('status', 'active')
            ->update(['status' => 'tc_issued']);

        return response()->json([
            'message' => 'Transfer Certificate issued successfully.',
            'data' => $student,
        ]);
    }

    /**
     * Export students to CSV/Excel.
     *
     * GET /api/v1/students/export
     * Query: format=csv|xlsx, filters same as index
     */
    public function export(Request $request)
    {
        // This would typically use a package like Laravel Excel
        // For now, return JSON that can be processed by frontend

        $students = Student::query()
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('academic_session_id'), function ($q) use ($request) {
                $q->whereHas('enrollments', fn($eq) => $eq->where('academic_session_id', $request->academic_session_id));
            })
            ->with(['enrollments.academicSession', 'enrollments.sessionGrade.grade', 'enrollments.section'])
            ->get()
            ->map(function ($student) {
                return [
                    'Student Code' => $student->student_code,
                    'Name (Bengali)' => $student->name_bn,
                    'Name (English)' => $student->name_en,
                    'Gender' => $student->gender,
                    'Date of Birth' => $student->date_of_birth?->format('Y-m-d'),
                    'Phone' => $student->student_phone,
                    'Father Name' => $student->father_name,
                    'Father Phone' => $student->father_phone,
                    'Guardian Phone' => $student->guardian_phone,
                    'Status' => $student->status,
                    'Residential Type' => $student->residential_type,
                ];
            });

        return response()->json([
            'data' => $students,
            'count' => $students->count(),
        ]);
    }
}
