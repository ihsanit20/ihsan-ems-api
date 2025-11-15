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
use App\Http\Controllers\Tenant\SubjectSessionController;
use App\Http\Controllers\Tenant\SectionController; // ✅ NEW

/*
|--------------------------------------------------------------------------
| API Routes (v1) — Role "Packets"
|--------------------------------------------------------------------------
| Prereq:
| - bootstrap/app.php এ $middleware->alias([...]) এর মধ্যে 'role' নিবন্ধন করা আছে।
|   উদাহরণ: $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class, ...]);
|
| রোল হায়ারার্কি (highest -> lowest):
| Developer > Owner > Admin > Teacher, Accountant > Guardian > Student
|
| নীচের $ALLOW ম্যাপ ব্যবহার করে প্রতিটি প্যাকেটের জন্য role গার্ড সেট করা হয়েছে।
*/

$ALLOW = [
    // Only Developer
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

        Route::get('subject-sessions', [SubjectSessionController::class, 'index'])
            ->name('subject-sessions.index');

        Route::get('sessions/{session}/subjects', function (int $session, Request $req, SubjectSessionController $ctrl) {
            $req->merge(['session_id' => $session]);
            return $ctrl->index($req);
        })
            ->whereNumber('session')
            ->name('sessions.subjects.index');

        // ✅ NEW: Sections public read (index + show)
        // index: /api/v1/sections?session_grade_id=123&search=A&per_page=25
        Route::get('sections', [SectionController::class, 'index'])
            ->name('sections.index');

        Route::get('sections/{section}', [SectionController::class, 'show'])
            ->whereNumber('section')
            ->name('sections.show');

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

            // Owner can hard-delete a session-grade
            Route::delete('session-grades/{sessionGrade}', [SessionGradeController::class, 'destroy'])
                ->whereNumber('sessionGrade')->name('session-grades.destroy');

            // ✅ NEW: Owner can hard-delete a section
            Route::delete('sections/{section}', [SectionController::class, 'destroy'])
                ->whereNumber('section')
                ->name('sections.destroy');
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

            Route::post('subject-sessions', [SubjectSessionController::class, 'store'])
                ->name('subject-sessions.store');

            Route::match(['put', 'patch'], 'subject-sessions/{subjectSession}', [SubjectSessionController::class, 'update'])
                ->whereNumber('subjectSession')
                ->name('subject-sessions.update');

            Route::delete('subject-sessions/{subjectSession}', [SubjectSessionController::class, 'destroy'])
                ->whereNumber('subjectSession')
                ->name('subject-sessions.destroy');

            // ✅ NEW: Sections create + update (Admin+)
            Route::post('sections', [SectionController::class, 'store'])
                ->name('sections.store');

            Route::match(['put', 'patch'], 'sections/{section}', [SectionController::class, 'update'])
                ->whereNumber('section')
                ->name('sections.update');
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
        });

        /* ------------------------------------------------
         | Guardian Suite — Guardian plus
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['GUARDIAN_PLUS']])->group(function () {
            Route::get('wards', fn() => response()->json(['wards' => []]))->name('guardian.wards.index');
            Route::get('payments', fn() => response()->json(['payments' => []]))->name('guardian.payments.index');
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