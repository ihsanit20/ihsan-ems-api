<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\MetaController;
use App\Http\Controllers\Tenant\UserController;
use App\Http\Controllers\Tenant\LevelController;
use App\Http\Controllers\Tenant\GradeController;
use App\Http\Controllers\Tenant\InstituteProfileController;
use App\Http\Controllers\Tenant\AcademicSessionController;
use App\Http\Controllers\Tenant\SessionGradeController;
use App\Http\Controllers\Tenant\SubjectController;
use App\Http\Controllers\Tenant\SectionController;
use App\Http\Controllers\Tenant\SessionSubjectController;
use App\Http\Controllers\Tenant\FeeController;
use App\Http\Controllers\Tenant\SessionFeeController;
use App\Http\Controllers\Tenant\AdmissionApplicationController;
use App\Http\Controllers\Tenant\StudentController;
use App\Http\Controllers\Tenant\AddressController;
use App\Http\Controllers\Tenant\StudentFeeController;
use App\Http\Controllers\Tenant\FeeInvoiceController;
use App\Http\Controllers\Tenant\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes (v1) — Role "Packets"
|--------------------------------------------------------------------------
*/

$ALLOW = [
    'DEV_ONLY'        => 'role:Developer',
    'OWNER_PLUS'      => 'role:Developer,Owner',
    'ADMIN_PLUS'      => 'role:Developer,Owner,Admin',
    'TEACHER_PLUS'    => 'role:Developer,Owner,Admin,Teacher',
    'ACCOUNTANT_PLUS' => 'role:Developer,Owner,Admin,Accountant',
    'GUARDIAN_PLUS'   => 'role:Developer,Owner,Admin,Teacher,Accountant,Guardian',
    'STUDENT_PLUS'    => 'role:Developer,Owner,Admin,Teacher,Accountant,Guardian,Student',
];

Route::prefix('v1')
    ->as('api.v1.')
    ->middleware(['throttle:api'])
    ->group(function () use ($ALLOW) {

        /* ------------------------------------------------
         | Public (no auth)
         * ------------------------------------------------ */
        Route::get('ping', fn() => response()->json([
            'ok' => true,
            'ts' => now()->toIso8601String(),
        ]))->name('ping');

        Route::post('auth/login', [AuthController::class, 'tokenLogin'])
            ->middleware('throttle:tenant-auth')
            ->name('auth.login');

        Route::post('auth/register', [AuthController::class, 'register'])
            ->middleware('throttle:tenant-auth')
            ->name('auth.register');

        Route::get('tenant/meta', [MetaController::class, 'show'])->name('meta.show');
        Route::get('institute/profile',  [InstituteProfileController::class, 'show'])->name('institute.profile.show');

        Route::get('sessions', [AcademicSessionController::class, 'index'])->name('sessions.index');
        Route::get('sessions/{session}', [AcademicSessionController::class, 'show'])
            ->whereNumber('session')->name('sessions.show');

        Route::get('levels', [LevelController::class, 'index'])->name('levels.index');
        Route::get('levels/{level}', [LevelController::class, 'show'])
            ->whereNumber('level')->name('levels.show');

        Route::get('grades', [GradeController::class, 'index'])->name('grades.index');
        Route::get('grades/{grade}', [GradeController::class, 'show'])
            ->whereNumber('grade')->name('grades.show');

        Route::get('sessions/{session}/grades',  [SessionGradeController::class, 'index'])
            ->whereNumber('session')->name('sessions.grades.index'); // legacy
        Route::get('sessions/{session}/classes', [SessionGradeController::class, 'index'])
            ->whereNumber('session')->name('sessions.classes.index');

        Route::get('session-grades', [SessionGradeController::class, 'index'])->name('session-grades.index');
        Route::get('session-grades/{sessionGrade}', [SessionGradeController::class, 'show'])
            ->whereNumber('sessionGrade')->name('session-grades.show');

        Route::get('subjects', [SubjectController::class, 'index'])
            ->name('subjects.index');

        Route::get('session-subjects', [SessionSubjectController::class, 'index'])
            ->name('session-subjects.index');

        Route::get('sessions/{session}/subjects', function (int $session, Request $req, SessionSubjectController $ctrl) {
            $req->merge(['session_id' => $session]);
            return $ctrl->index($req);
        })
            ->whereNumber('session')
            ->name('sessions.subjects.index');

        // Sections public read (index + show)
        Route::get('sections', [SectionController::class, 'index'])
            ->name('sections.index');

        Route::get('sections/{section}', [SectionController::class, 'show'])
            ->whereNumber('section')
            ->name('sections.show');

        // Fees public read (index + show) – read-only
        Route::get('fees', [FeeController::class, 'index'])
            ->name('fees.index');

        Route::get('fees/{fee}', [FeeController::class, 'show'])
            ->whereNumber('fee')
            ->name('fees.show');

        Route::get('session-fees', [SessionFeeController::class, 'index'])
            ->name('session-fees.index');

        Route::get('session-fees/{sessionFee}', [SessionFeeController::class, 'show'])
            ->whereNumber('sessionFee')
            ->name('session-fees.show');

        // Admission applications – public meta + form submit
        Route::get('admission-applications/meta', [AdmissionApplicationController::class, 'formMeta'])
            ->name('admission-applications.meta');

        Route::post('admission-applications', [AdmissionApplicationController::class, 'store'])
            ->name('admission-applications.store');

        // Address endpoints – public (for dropdowns in forms)
        Route::get('divisions', [AddressController::class, 'divisions'])
            ->name('divisions.index');

        Route::get('districts', [AddressController::class, 'districts'])
            ->name('districts.index');

        Route::get('areas', [AddressController::class, 'areas'])
            ->name('areas.index');

        /* ------------------------------------------------
         | Authenticated (shared by all signed-in roles)
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['STUDENT_PLUS']])->group(function () {
            Route::as('auth.')->group(function () {
                Route::get('me', [AuthController::class, 'me'])->name('me');
                Route::post('auth/logout', [AuthController::class, 'tokenLogout']);
                Route::post('auth/logout-all', [AuthController::class, 'revokeAllTokens']);
            });
        });

        /* ------------------------------------------------
         | DevTools — Developer only
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['DEV_ONLY']])->group(function () {
            Route::get('info', function () {
                return response()->json([
                    'env'  => app()->environment(),
                    'php'  => PHP_VERSION,
                    'time' => now()->toDateTimeString(),
                ]);
            })->name('dev.info');

            Route::post('maintenance/run', fn() => response()->json(['ok' => true]))
                ->name('dev.maintenance.run');
        });

        /* ------------------------------------------------
         | Owner Suite — Owner plus (Owner, Developer)
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['OWNER_PLUS']])->group(function () {
            Route::get('settings', fn() => response()->json(['settings' => true]))->name('owner.settings.index');
            Route::post('settings', fn() => response()->json(['updated' => true]))->name('owner.settings.update');

            Route::get('billing', fn() => response()->json(['billing' => []]))->name('owner.billing.index');
            Route::post('billing/refresh', fn() => response()->json(['ok' => true]))->name('owner.billing.refresh');

            Route::match(['put', 'patch'], 'institute/profile', [InstituteProfileController::class, 'update'])
                ->name('institute.profile.update');

            Route::delete('levels/{level}', [LevelController::class, 'destroy'])
                ->whereNumber('level')->name('levels.destroy');

            Route::delete('grades/{grade}', [GradeController::class, 'destroy'])
                ->whereNumber('grade')->name('grades.destroy');

            Route::delete('session-grades/{sessionGrade}', [SessionGradeController::class, 'destroy'])
                ->whereNumber('sessionGrade')->name('session-grades.destroy');

            // Owner can hard-delete a section
            Route::delete('sections/{section}', [SectionController::class, 'destroy'])
                ->whereNumber('section')
                ->name('sections.destroy');

            // Owner can hard-delete a fee
            Route::delete('fees/{fee}', [FeeController::class, 'destroy'])
                ->whereNumber('fee')
                ->name('fees.destroy');

            Route::delete('session-fees/{sessionFee}', [SessionFeeController::class, 'destroy'])
                ->whereNumber('sessionFee')
                ->name('session-fees.destroy');
        });

        /* ------------------------------------------------
         | Admin Suite — Admin plus (Admin, Owner, Developer)
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['ADMIN_PLUS']])->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}', [UserController::class, 'show'])->whereNumber('user')->name('users.show');
            Route::put('users/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.update');
            Route::patch('users/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.patch');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->whereNumber('user')->name('users.destroy');

            Route::post('sessions', [AcademicSessionController::class, 'store'])->name('sessions.store');
            Route::match(['put', 'patch'], 'sessions/{session}', [AcademicSessionController::class, 'update'])
                ->whereNumber('session')->name('sessions.update');
            Route::delete('sessions/{session}', [AcademicSessionController::class, 'destroy'])
                ->whereNumber('session')->name('sessions.destroy');

            Route::post('levels', [LevelController::class, 'store'])->name('levels.store');
            Route::put('levels/{level}', [LevelController::class, 'update'])->whereNumber('level')->name('levels.update');
            Route::patch('levels/{level}', [LevelController::class, 'update'])->whereNumber('level')->name('levels.patch');

            Route::post('grades', [GradeController::class, 'store'])->name('grades.store');
            Route::put('grades/{grade}', [GradeController::class, 'update'])->whereNumber('grade')->name('grades.update');
            Route::patch('grades/{grade}', [GradeController::class, 'update'])->whereNumber('grade')->name('grades.patch');

            Route::post('sessions/{session}/classes', [SessionGradeController::class, 'store'])
                ->whereNumber('session')->name('sessions.classes.store');

            Route::post('sessions/{session}/classes/bulk-open', [SessionGradeController::class, 'bulkOpen'])
                ->whereNumber('session')->name('sessions.classes.bulk-open');

            Route::match(['put', 'patch'], 'session-classes/{sessionGrade}', [SessionGradeController::class, 'update'])
                ->whereNumber('sessionGrade')->name('session-classes.update');

            Route::post('subjects', [SubjectController::class, 'store'])
                ->name('subjects.store');

            Route::match(['put', 'patch'], 'subjects/{subject}', [SubjectController::class, 'update'])
                ->whereNumber('subject')
                ->name('subjects.update');

            Route::delete('subjects/{subject}', [SubjectController::class, 'destroy'])
                ->whereNumber('subject')
                ->name('subjects.destroy');

            Route::post('session-subjects', [SessionSubjectController::class, 'store'])
                ->name('session-subjects.store');

            Route::match(['put', 'patch'], 'session-subjects/{sessionSubject}', [SessionSubjectController::class, 'update'])
                ->whereNumber('sessionSubject')
                ->name('session-subjects.update');

            Route::delete('session-subjects/{sessionSubject}', [SessionSubjectController::class, 'destroy'])
                ->whereNumber('sessionSubject')
                ->name('session-subjects.destroy');

            // Sections create + update (Admin+)
            Route::post('sections', [SectionController::class, 'store'])
                ->name('sections.store');

            Route::match(['put', 'patch'], 'sections/{section}', [SectionController::class, 'update'])
                ->whereNumber('section')
                ->name('sections.update');

            // Fees create + update (Admin+)
            Route::post('fees', [FeeController::class, 'store'])
                ->name('fees.store');

            Route::put('fees/{fee}', [FeeController::class, 'update'])
                ->whereNumber('fee')
                ->name('fees.update');

            Route::patch('fees/{fee}', [FeeController::class, 'update'])
                ->whereNumber('fee')
                ->name('fees.patch');

            Route::post('session-fees', [SessionFeeController::class, 'store'])
                ->name('session-fees.store');

            Route::match(['put', 'patch'], 'session-fees/{sessionFee}', [SessionFeeController::class, 'update'])
                ->whereNumber('sessionFee')
                ->name('session-fees.update');

            // Admission applications (Admin)
            Route::get('admission-applications', [AdmissionApplicationController::class, 'index'])
                ->name('admission-applications.index');

            Route::get('admission-applications/stats', [AdmissionApplicationController::class, 'stats'])
                ->name('admission-applications.stats');

            Route::get('admission-applications/{id}', [AdmissionApplicationController::class, 'show'])
                ->whereNumber('id')
                ->name('admission-applications.show');

            Route::match(['put', 'patch'], 'admission-applications/{id}', [AdmissionApplicationController::class, 'update'])
                ->whereNumber('id')
                ->name('admission-applications.update');

            Route::delete('admission-applications/{id}', [AdmissionApplicationController::class, 'destroy'])
                ->whereNumber('id')
                ->name('admission-applications.destroy');

            Route::post('admission-applications/{id}/status', [AdmissionApplicationController::class, 'updateStatus'])
                ->whereNumber('id')
                ->name('admission-applications.update-status');

            Route::post('admission-applications/{id}/admit', [AdmissionApplicationController::class, 'admit'])
                ->whereNumber('id')
                ->name('admission-applications.admit');

            // Student Management (Admin+)
            Route::get('students', [StudentController::class, 'index'])
                ->name('students.index');

            Route::get('students/stats', [StudentController::class, 'stats'])
                ->name('students.stats');

            Route::get('students/export', [StudentController::class, 'export'])
                ->name('students.export');

            Route::post('students', [StudentController::class, 'store'])
                ->name('students.store');

            Route::get('students/{student}', [StudentController::class, 'show'])
                ->whereNumber('student')
                ->name('students.show');

            Route::match(['put', 'patch'], 'students/{student}', [StudentController::class, 'update'])
                ->whereNumber('student')
                ->name('students.update');

            Route::delete('students/{student}', [StudentController::class, 'destroy'])
                ->whereNumber('student')
                ->name('students.destroy');

            Route::get('students/{student}/enrollments', [StudentController::class, 'enrollments'])
                ->whereNumber('student')
                ->name('students.enrollments');

            Route::post('students/{student}/create-account', [StudentController::class, 'createUserAccount'])
                ->whereNumber('student')
                ->name('students.create-account');

            Route::post('students/{student}/upload-photo', [StudentController::class, 'uploadPhoto'])
                ->whereNumber('student')
                ->name('students.upload-photo');

            Route::post('students/{student}/transfer', [StudentController::class, 'transfer'])
                ->whereNumber('student')
                ->name('students.transfer');

            Route::post('students/{student}/issue-tc', [StudentController::class, 'issueTC'])
                ->whereNumber('student')
                ->name('students.issue-tc');

            Route::post('students/bulk-status', [StudentController::class, 'bulkUpdateStatus'])
                ->name('students.bulk-status');

            Route::post('students/bulk-promote', [StudentController::class, 'bulkPromote'])
                ->name('students.bulk-promote');
        });

        /* ------------------------------------------------
         | Teacher Suite — Teacher plus
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['TEACHER_PLUS']])->group(function () {
            Route::get('courses', fn() => response()->json(['courses' => []]))->name('teacher.courses.index');
            Route::post('courses', fn() => response()->json(['created' => true]))->name('teacher.courses.store');

            Route::get('lectures', fn() => response()->json(['lectures' => []]))->name('teacher.lectures.index');
            Route::post('lectures', fn() => response()->json(['created' => true]))->name('teacher.lectures.store');

            Route::post('exams/publish', fn() => response()->json(['published' => true]))->name('teacher.exams.publish');
        });

        /* ------------------------------------------------
         | Accountant Suite — Accountant plus
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['ACCOUNTANT_PLUS']])->group(function () {
            Route::get('invoices', fn() => response()->json(['invoices' => []]))->name('finance.invoices.index');
            Route::post('invoices', fn() => response()->json(['created' => true]))->name('finance.invoices.store');
            Route::post('reconcile', fn() => response()->json(['ok' => true]))->name('finance.reconcile');

            // Student Fees
            Route::get('student-fees', [StudentFeeController::class, 'index'])
                ->name('student-fees.index');

            Route::get('student-fees/{studentFee}', [StudentFeeController::class, 'show'])
                ->whereNumber('studentFee')
                ->name('student-fees.show');

            Route::post('student-fees', [StudentFeeController::class, 'store'])
                ->name('student-fees.store');

            Route::match(['put', 'patch'], 'student-fees/{studentFee}', [StudentFeeController::class, 'update'])
                ->whereNumber('studentFee')
                ->name('student-fees.update');

            Route::delete('student-fees/{studentFee}', [StudentFeeController::class, 'destroy'])
                ->whereNumber('studentFee')
                ->name('student-fees.destroy');

            Route::post('student-fees/bulk-assign', [StudentFeeController::class, 'bulkAssign'])
                ->name('student-fees.bulk-assign');

            Route::post('student-fees/bulk-update', [StudentFeeController::class, 'bulkUpdate'])
                ->name('student-fees.bulk-update');

            // Fee Invoices
            Route::get('fee-invoices', [FeeInvoiceController::class, 'index'])
                ->name('fee-invoices.index');

            Route::get('fee-invoices/{feeInvoice}', [FeeInvoiceController::class, 'show'])
                ->whereNumber('feeInvoice')
                ->name('fee-invoices.show');

            Route::post('fee-invoices', [FeeInvoiceController::class, 'store'])
                ->name('fee-invoices.store');

            Route::match(['put', 'patch'], 'fee-invoices/{feeInvoice}', [FeeInvoiceController::class, 'update'])
                ->whereNumber('feeInvoice')
                ->name('fee-invoices.update');

            Route::delete('fee-invoices/{feeInvoice}', [FeeInvoiceController::class, 'destroy'])
                ->whereNumber('feeInvoice')
                ->name('fee-invoices.destroy');

            Route::get('students/{studentId}/invoices', [FeeInvoiceController::class, 'studentInvoices'])
                ->whereNumber('studentId')
                ->name('students.invoices');

            // Payments
            Route::get('payments', [PaymentController::class, 'index'])
                ->name('payments.index');

            Route::get('payments/{payment}', [PaymentController::class, 'show'])
                ->whereNumber('payment')
                ->name('payments.show');

            Route::post('payments', [PaymentController::class, 'store'])
                ->name('payments.store');

            Route::match(['put', 'patch'], 'payments/{payment}', [PaymentController::class, 'update'])
                ->whereNumber('payment')
                ->name('payments.update');

            Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])
                ->whereNumber('payment')
                ->name('payments.destroy');

            Route::get('students/{studentId}/payments', [PaymentController::class, 'studentPayments'])
                ->whereNumber('studentId')
                ->name('students.payments');

            Route::get('invoices/{invoiceId}/payments', [PaymentController::class, 'invoicePayments'])
                ->whereNumber('invoiceId')
                ->name('invoices.payments');
        });

        /* ------------------------------------------------
         | Guardian Suite — Guardian plus
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['GUARDIAN_PLUS']])->group(function () {
            Route::get('wards', fn() => response()->json(['wards' => []]))->name('guardian.wards.index');
            Route::get('payments', fn() => response()->json(['payments' => []]))->name('guardian.payments.index');

            // Guardian + others: view own applications
            Route::get('admission-applications/my', [AdmissionApplicationController::class, 'myApplications'])
                ->name('admission-applications.my');
        });

        /* ------------------------------------------------
         | Student Suite — Student plus (everyone)
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['STUDENT_PLUS']])->group(function () {
            Route::get('dashboard', fn() => response()->json(['welcome' => 'student']))->name('student.dashboard');
            Route::get('classes', fn() => response()->json(['classes' => []]))->name('student.classes.index');
            Route::post('exams/attempt', fn() => response()->json(['attempted' => true]))->name('student.exams.attempt');
        });

        /* ---------- v1 Fallback (JSON 404) ---------- */
        Route::fallback(fn() => response()->json(['message' => 'Not Found.'], 404))
            ->name('fallback');
    });
