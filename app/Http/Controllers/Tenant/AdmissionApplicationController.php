<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AdmissionApplication;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\SessionGrade;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdmissionApplicationController extends Controller
{
    /* ---------------------------------------
     * 1) Application list (Admin side)
     * -------------------------------------*/
    public function index(Request $request)
    {
        $query = AdmissionApplication::query()
            ->with(['session', 'sessionGrade', 'admittedStudent']);

        // filters: session, class, status, search
        if ($request->filled('academic_session_id')) {
            $query->where('academic_session_id', $request->integer('academic_session_id'));
        }

        if ($request->filled('session_grade_id')) {
            $query->where('session_grade_id', $request->integer('session_grade_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status')); // pending/accepted/rejected/admitted
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('application_no', 'like', "%{$search}%")
                    ->orWhere('applicant_name', 'like', "%{$search}%")
                    ->orWhere('father_name', 'like', "%{$search}%")
                    ->orWhere('student_phone', 'like', "%{$search}%")
                    ->orWhere('guardian_phone', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 25);

        if ($request->boolean('all')) {
            $items = $query->orderByDesc('id')->get();
        } else {
            $items = $query->orderByDesc('id')->paginate($perPage);
        }

        return response()->json($items);
    }

    /* ---------------------------------------
     * 2) Store (Public / Frontend)
     * -------------------------------------*/
    public function store(Request $request)
    {
        $data = $request->validate([
            'academic_session_id' => ['required', 'exists:tenant.academic_sessions,id'],
            'session_grade_id'    => ['required', 'exists:tenant.session_grades,id'],

            'application_type'    => ['nullable', 'in:new,re_admission'],
            'existing_student_id' => [
                'nullable',
                'exists:tenant.students,id',
                function ($attr, $value, $fail) use ($request) {
                    if ($request->get('application_type') === 're_admission' && !$value) {
                        $fail('existing_student_id is required for re_admission.');
                    }
                },
            ],

            'applicant_name'      => ['nullable', 'string', 'max:255'],
            'gender'              => ['nullable', 'string', 'max:10'],
            'date_of_birth'       => ['nullable', 'date'],
            'student_phone'       => ['nullable', 'string', 'max:20'],
            'student_email'       => ['nullable', 'email', 'max:255'],

            'father_name'         => ['nullable', 'string', 'max:255'],
            'father_phone'        => ['nullable', 'string', 'max:20'],
            'father_occupation'   => ['nullable', 'string', 'max:255'],

            'mother_name'         => ['nullable', 'string', 'max:255'],
            'mother_phone'        => ['nullable', 'string', 'max:20'],
            'mother_occupation'   => ['nullable', 'string', 'max:255'],

            'guardian_type'       => ['nullable', 'in:father,mother,other'],
            'guardian_name'       => ['nullable', 'string', 'max:255'],
            'guardian_phone'      => ['nullable', 'string', 'max:20'],
            'guardian_relation'   => ['nullable', 'string', 'max:100'],

            'present_address'     => ['nullable', 'array'],
            'present_address.division_id'  => ['nullable', 'integer', 'exists:tenant.divisions,id'],
            'present_address.district_id'  => ['nullable', 'integer', 'exists:tenant.districts,id'],
            'present_address.area_id'      => ['nullable', 'integer', 'exists:tenant.areas,id'],
            'present_address.village_house_holding' => ['nullable', 'string', 'max:500'],

            'permanent_address'   => ['nullable', 'array'],
            'permanent_address.division_id'  => ['nullable', 'integer', 'exists:tenant.divisions,id'],
            'permanent_address.district_id'  => ['nullable', 'integer', 'exists:tenant.districts,id'],
            'permanent_address.area_id'      => ['nullable', 'integer', 'exists:tenant.areas,id'],
            'permanent_address.village_house_holding' => ['nullable', 'string', 'max:500'],

            'is_present_same_as_permanent' => ['nullable', 'boolean'],

            'previous_institution_name'    => ['nullable', 'string', 'max:255'],
            'previous_class'               => ['nullable', 'string', 'max:100'],
            'previous_result'              => ['nullable', 'string', 'max:255'],
            'previous_result_division'     => ['nullable', 'string', 'max:100'],

            'residential_type'    => ['nullable', 'in:residential,new_musafir,non_residential'],
            'applied_via'         => ['nullable', 'in:online,offline'],
            'application_date'    => ['nullable', 'date'],

            'photo_path'          => ['nullable', 'string', 'max:255'],
            'meta_json'           => ['nullable', 'array'],
        ]);

        // defaults
        $data['application_no']   = $this->generateApplicationNo();
        $data['application_type'] = $data['application_type'] ?? 'new';
        $data['applied_via']      = $data['applied_via'] ?? 'online';
        $data['status']           = 'pending';

        /** 1) Duplicate guard for existing student */
        if (!empty($data['existing_student_id'])) {
            $exists = AdmissionApplication::where('existing_student_id', $data['existing_student_id'])
                ->where('academic_session_id', $data['academic_session_id'])
                ->whereIn('status', ['pending', 'accepted', 'admitted'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'এই ছাত্রের নতুন সেশনে আবেদন আগেই আছে।',
                ], 422);
            }
        }

        /** 2) Prefill from existing student (recommended) */
        if (!empty($data['existing_student_id'])) {
            $student = Student::findOrFail($data['existing_student_id']);

            $data['applicant_name'] = $data['applicant_name'] ?? $student->name_bn;
            $data['gender']         = $data['gender'] ?? $student->gender;
            $data['date_of_birth']  = $data['date_of_birth'] ?? $student->date_of_birth;
            $data['student_phone']  = $data['student_phone'] ?? $student->student_phone;
            $data['student_email']  = $data['student_email'] ?? $student->student_email;

            $data['father_name']       = $data['father_name'] ?? $student->father_name;
            $data['father_phone']      = $data['father_phone'] ?? $student->father_phone;
            $data['father_occupation'] = $data['father_occupation'] ?? $student->father_occupation;

            $data['mother_name']       = $data['mother_name'] ?? $student->mother_name;
            $data['mother_phone']      = $data['mother_phone'] ?? $student->mother_phone;
            $data['mother_occupation'] = $data['mother_occupation'] ?? $student->mother_occupation;

            $data['guardian_type']     = $data['guardian_type'] ?? $student->guardian_type;
            $data['guardian_name']     = $data['guardian_name'] ?? $student->guardian_name;
            $data['guardian_phone']    = $data['guardian_phone'] ?? $student->guardian_phone;
            $data['guardian_relation'] = $data['guardian_relation'] ?? $student->guardian_relation;

            $data['present_address']   = $data['present_address'] ?? $student->present_address;
            $data['permanent_address'] = $data['permanent_address'] ?? $student->permanent_address;

            $data['residential_type']  = $data['residential_type'] ?? $student->residential_type;
            $data['photo_path']        = $data['photo_path'] ?? $student->photo_path;
            $data['meta_json']         = $data['meta_json'] ?? $student->meta_json;
        }

        /** 3) Guardian auto-fill safety */
        if (($data['guardian_type'] ?? 'father') === 'father') {
            $data['guardian_name']     = $data['guardian_name'] ?? $data['father_name'] ?? null;
            $data['guardian_phone']    = $data['guardian_phone'] ?? $data['father_phone'] ?? null;
            $data['guardian_relation'] = $data['guardian_relation'] ?? 'Father';
        } elseif (($data['guardian_type'] ?? null) === 'mother') {
            $data['guardian_name']     = $data['guardian_name'] ?? $data['mother_name'] ?? null;
            $data['guardian_phone']    = $data['guardian_phone'] ?? $data['mother_phone'] ?? null;
            $data['guardian_relation'] = $data['guardian_relation'] ?? 'Mother';
        }

        $application = AdmissionApplication::create($data);

        return response()->json([
            'message' => 'Application submitted successfully.',
            'data'    => $application->fresh(),
        ], 201);
    }

    /* ---------------------------------------
     * 3) Show single application
     * -------------------------------------*/
    public function show($id)
    {
        $application = AdmissionApplication::with(['session', 'sessionGrade', 'admittedStudent'])
            ->findOrFail($id);

        return response()->json($application);
    }

    /* ---------------------------------------
     * 4) My applications (Guardian/User)
     * -------------------------------------*/
    public function myApplications(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // simple heuristic: email/phone match
        $query = AdmissionApplication::query();

        $query->where(function ($q) use ($user) {
            if ($user->email) {
                $q->where('student_email', $user->email);
            }
            if ($user->phone) {
                $q->orWhere('student_phone', $user->phone)
                    ->orWhere('guardian_phone', $user->phone);
            }
        });

        $items = $query->orderByDesc('id')->get();

        return response()->json($items);
    }

    /* ---------------------------------------
     * 5) Form meta (sessions, classes, enums)
     * -------------------------------------*/
    public function formMeta()
    {
        $sessions = AcademicSession::query()
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'start_date', 'end_date', 'is_active']);

        $sessionGrades = SessionGrade::query()
            ->with('grade:id,name,code')
            ->orderBy('academic_session_id')
            ->orderBy('grade_id')
            ->get(['id', 'academic_session_id', 'grade_id']);

        return response()->json([
            'sessions'        => $sessions,
            'session_grades'  => $sessionGrades,
            'guardian_types'  => ['father', 'mother', 'other'],
            'residential_types' => ['residential', 'new_musafir', 'non_residential'],
        ]);
    }

    /* ---------------------------------------
     * 6) Update application (Admin)
     * -------------------------------------*/
    public function update(Request $request, $id)
    {
        $application = AdmissionApplication::findOrFail($id);

        // admitted হলে সাধারণত edit না করতে দেয়, চাইলে এই guard রাখো
        if ($application->status === 'admitted') {
            return response()->json([
                'message' => 'Admitted applications cannot be modified.',
            ], 422);
        }

        $data = $request->validate([
            'session_grade_id'    => ['sometimes', 'exists:tenant.session_grades,id'],
            'application_type'    => ['sometimes', 'in:new,re_admission'],
            'existing_student_id' => ['nullable', 'exists:tenant.students,id'],

            'applicant_name'      => ['sometimes', 'string', 'max:255'],
            'gender'              => ['sometimes', 'string', 'max:10'],
            'date_of_birth'       => ['sometimes', 'date'],
            'student_phone'       => ['sometimes', 'string', 'max:20'],
            'student_email'       => ['sometimes', 'email', 'max:255'],

            'father_name'         => ['sometimes', 'string', 'max:255'],
            'father_phone'        => ['sometimes', 'string', 'max:20'],
            'father_occupation'   => ['sometimes', 'string', 'max:255'],

            'mother_name'         => ['sometimes', 'string', 'max:255'],
            'mother_phone'        => ['sometimes', 'string', 'max:20'],
            'mother_occupation'   => ['sometimes', 'string', 'max:255'],

            'guardian_type'       => ['sometimes', 'in:father,mother,other'],
            'guardian_name'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'guardian_phone'      => ['sometimes', 'nullable', 'string', 'max:20'],
            'guardian_relation'   => ['sometimes', 'nullable', 'string', 'max:100'],

            'present_address'     => ['sometimes', 'nullable', 'array'],
            'present_address.division_id'  => ['sometimes', 'nullable', 'integer', 'exists:tenant.divisions,id'],
            'present_address.district_id'  => ['sometimes', 'nullable', 'integer', 'exists:tenant.districts,id'],
            'present_address.area_id'      => ['sometimes', 'nullable', 'integer', 'exists:tenant.areas,id'],
            'present_address.village_house_holding' => ['sometimes', 'nullable', 'string', 'max:500'],

            'permanent_address'   => ['sometimes', 'nullable', 'array'],
            'permanent_address.division_id'  => ['sometimes', 'nullable', 'integer', 'exists:tenant.divisions,id'],
            'permanent_address.district_id'  => ['sometimes', 'nullable', 'integer', 'exists:tenant.districts,id'],
            'permanent_address.area_id'      => ['sometimes', 'nullable', 'integer', 'exists:tenant.areas,id'],
            'permanent_address.village_house_holding' => ['sometimes', 'nullable', 'string', 'max:500'],

            'is_present_same_as_permanent' => ['sometimes', 'boolean'],

            'previous_institution_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'previous_class'               => ['sometimes', 'nullable', 'string', 'max:100'],
            'previous_result'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'previous_result_division'     => ['sometimes', 'nullable', 'string', 'max:100'],

            'residential_type'    => ['sometimes', 'nullable', 'in:residential,new_musafir,non_residential'],

            'photo_path'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_json'           => ['sometimes', 'nullable', 'array'],
        ]);

        // guardian auto-fill safety again
        $guardianType = $data['guardian_type'] ?? $application->guardian_type;

        if ($guardianType === 'father') {
            $fatherName  = $data['father_name'] ?? $application->father_name;
            $fatherPhone = $data['father_phone'] ?? $application->father_phone;

            $data['guardian_name']  = $data['guardian_name']  ?? $fatherName;
            $data['guardian_phone'] = $data['guardian_phone'] ?? $fatherPhone;
            $data['guardian_relation'] = $data['guardian_relation'] ?? 'Father';
        } elseif ($guardianType === 'mother') {
            $motherName  = $data['mother_name'] ?? $application->mother_name;
            $motherPhone = $data['mother_phone'] ?? $application->mother_phone;

            $data['guardian_name']  = $data['guardian_name']  ?? $motherName;
            $data['guardian_phone'] = $data['guardian_phone'] ?? $motherPhone;
            $data['guardian_relation'] = $data['guardian_relation'] ?? 'Mother';
        }

        $application->fill($data)->save();

        return response()->json([
            'message' => 'Application updated successfully.',
            'data'    => $application->fresh(),
        ]);
    }

    /* ---------------------------------------
     * 7) Destroy (Admin)
     * -------------------------------------*/
    public function destroy($id)
    {
        $application = AdmissionApplication::findOrFail($id);

        if ($application->status === 'admitted') {
            return response()->json([
                'message' => 'Admitted applications cannot be deleted.',
            ], 422);
        }

        $application->delete();

        return response()->json([
            'message' => 'Application deleted successfully.',
        ]);
    }

    /* ---------------------------------------
     * 8) Update status (accept / reject)
     * -------------------------------------*/
    public function updateStatus(Request $request, $id)
    {
        $application = AdmissionApplication::findOrFail($id);

        $data = $request->validate([
            'status'      => ['required', 'in:pending,accepted,rejected,admitted'],
            'status_note' => ['nullable', 'string'],
        ]);

        // admitted এখানে allow না দিলে ভালো, আলাদা admit() আছে
        if ($data['status'] === 'admitted') {
            return response()->json([
                'message' => 'Use the admit endpoint to admit a student.',
            ], 422);
        }

        $application->status = $data['status'];
        $application->status_note = $data['status_note'] ?? $application->status_note;
        $application->save();

        return response()->json([
            'message' => 'Status updated successfully.',
            'data'    => $application,
        ]);
    }

    /* ---------------------------------------
     * 9) Admit (Approve & create Student + Enrollment)
     * -------------------------------------*/
    public function admit(Request $request, $id)
    {
        $application = AdmissionApplication::findOrFail($id);

        if ($application->status === 'admitted') {
            return response()->json([
                'message' => 'This application is already admitted.',
            ], 422);
        }

        $data = $request->validate([
            'section_id'     => ['nullable', 'exists:tenant.sections,id'],
            'roll_no'        => ['nullable', 'string', 'max:50'],
            'admission_date' => ['nullable', 'date'],
        ]);

        $admissionDate = $data['admission_date'] ?? now()->toDateString();

        $result = DB::connection('tenant')->transaction(function () use ($application, $data, $admissionDate) {
            // Auto-assign roll number if not provided and section exists
            $rollNo = $data['roll_no'] ?? null;

            if (!$rollNo && isset($data['section_id'])) {
                // Get the next roll number for this session + section
                $maxRoll = StudentEnrollment::where('academic_session_id', $application->academic_session_id)
                    ->where('section_id', $data['section_id'])
                    ->max(DB::raw('CAST(roll_no AS UNSIGNED)'));

                $rollNo = (string) (($maxRoll ?? 0) + 1);
            }
            // 1) Student resolve/create
            if ($application->existing_student_id) {
                $student = Student::findOrFail($application->existing_student_id);
            } else {
                $student = Student::create([
                    'student_code'    => $this->generateStudentCode(),
                    'user_id'         => null,

                    'name_bn'         => $application->applicant_name,
                    'name_en'         => null,
                    'gender'          => $application->gender,
                    'date_of_birth'   => $application->date_of_birth,
                    'student_phone'   => $application->student_phone,
                    'student_email'   => $application->student_email,

                    'father_name'     => $application->father_name,
                    'father_phone'    => $application->father_phone,
                    'father_occupation' => $application->father_occupation,

                    'mother_name'     => $application->mother_name,
                    'mother_phone'    => $application->mother_phone,
                    'mother_occupation' => $application->mother_occupation,

                    'guardian_type'   => $application->guardian_type,
                    'guardian_name'   => $application->guardian_name,
                    'guardian_phone'  => $application->guardian_phone,
                    'guardian_relation' => $application->guardian_relation,

                    'present_address'   => $application->present_address,
                    'permanent_address' => $application->permanent_address,

                    'residential_type'  => $application->residential_type,
                    'status'            => 'active',
                    'photo_path'        => $application->photo_path,
                    'meta_json'         => $application->meta_json,
                ]);
            }

            // 2) Enrollment create
            $enrollment = StudentEnrollment::create([
                'student_id'          => $student->id,
                'academic_session_id' => $application->academic_session_id,
                'session_grade_id'    => $application->session_grade_id,
                'section_id'          => $data['section_id'] ?? null,
                'roll_no'             => $rollNo,

                'admission_type'      => $application->application_type === 're_admission'
                    ? 're_admission'
                    : 'new',

                'application_id'      => $application->id,
                'admission_date'      => $admissionDate,
                'status'              => 'active',
                'remarks'             => null,
            ]);

            // 3) Application update
            $application->status = 'admitted';
            $application->admitted_student_id = $student->id;
            if (!$application->existing_student_id) {
                $application->existing_student_id = $student->id;
            }
            $application->save();

            // 4) Optional: fees assign সার্ভিস কল করার জায়গা
            // app(\App\Services\Tenant\FeeAssignmentService::class)
            //     ->assignForEnrollment($enrollment);

            return compact('student', 'enrollment', 'application');
        });

        return response()->json([
            'message'    => 'Student admitted successfully.',
            'student'    => $result['student'],
            'enrollment' => $result['enrollment'],
            'application' => $result['application'],
        ]);
    }

    /* ---------------------------------------
     * 10) Stats (Dashboard)
     * -------------------------------------*/
    public function stats(Request $request)
    {
        $query = AdmissionApplication::query();

        if ($request->filled('academic_session_id')) {
            $query->where('academic_session_id', $request->integer('academic_session_id'));
        }

        $total = (clone $query)->count();

        $byStatus = (clone $query)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        $bySession = AdmissionApplication::select(
            'academic_session_id',
            DB::raw('COUNT(*) as count')
        )
            ->groupBy('academic_session_id')
            ->get();

        return response()->json([
            'total'     => $total,
            'by_status' => $byStatus,
            'by_session' => $bySession,
        ]);
    }

    /* ---------------------------------------
     * Helpers
     * -------------------------------------*/
    protected function generateApplicationNo(): string
    {
        // খুব সিম্পল হিউম্যান-রিডেবল নম্বর: ADM-YY-XXXXX
        $year = now()->format('y');
        $lastId = AdmissionApplication::max('id') ?? 0;
        $seq = str_pad((string) ($lastId + 1), 5, '0', STR_PAD_LEFT);

        return "ADM-{$year}-{$seq}";
    }

    protected function generateStudentCode(): string
    {
        // সহজ মনে রাখার মত স্থায়ী নম্বর: শেষ 2 ডিজিট বছর + ক্রমিক সংখ্যা
        // উদাহরণ: 250001, 250002, 250003... (6 ডিজিট)

        $year = now()->format('y'); // শেষ 2 ডিজিট বছর: 25, 26...

        // এই বছরের সর্বশেষ student code খুঁজে বের করা
        $lastStudent = Student::where('student_code', 'like', $year . '%')
            ->orderBy('student_code', 'desc')
            ->first();

        if ($lastStudent) {
            // বিদ্যমান code থেকে sequence বের করা
            $lastCode = $lastStudent->student_code;
            $lastSeq = (int) substr($lastCode, strlen($year));
            $nextSeq = $lastSeq + 1;
        } else {
            // এই বছরের প্রথম student
            $nextSeq = 1;
        }

        // 4 ডিজিটের sequence (0001, 0002, ...)
        $seq = str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);

        return $year . $seq; // 250001, 250002...
    }
}