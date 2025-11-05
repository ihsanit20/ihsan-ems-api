<?php

use App\Http\Controllers\Tenant\AcademicSessionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\MetaController;
use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\GradeController;
use App\Http\Controllers\Tenant\InstituteProfileController;
use App\Http\Controllers\Tenant\LevelController;
use App\Http\Controllers\Tenant\SessionGradeController;
use App\Http\Controllers\Tenant\UserController;

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

// ---------- Role allow lists (Developer always included on all “+” levels) ----------
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
        Route::get('sessions/{session}', [AcademicSessionController::class, 'show'])->whereNumber('session')->name('sessions.show');
        Route::get('sessions/{session}/grades', [SessionGradeController::class, 'index'])->whereNumber('session')->name('sessions.grades.index');

        Route::get('levels', [LevelController::class, 'index'])->name('levels.index');
        Route::get('levels/{level}', [LevelController::class, 'show'])->whereNumber('level')->name('levels.show');

        Route::get('grades', [GradeController::class, 'index'])->name('grades.index');
        Route::get('grades/{grade}', [GradeController::class, 'show'])->whereNumber('grade')->name('grades.show');

        Route::get('session-grades', [SessionGradeController::class, 'index'])->name('session-grades.index');
        Route::get('session-grades/{sessionGrade}', [SessionGradeController::class, 'show'])->whereNumber('sessionGrade')->name('session-grades.show');

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
         | DevTools Packet — Developer only
         | (URL prefix সরানো হয়েছে; middleware আগের মতো)
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
         | (URL prefix সরানো হয়েছে)
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['OWNER_PLUS']])->group(function () {
            Route::get('settings', fn() => response()->json(['settings' => true]))->name('owner.settings.index');
            Route::post('settings', fn() => response()->json(['updated' => true]))->name('owner.settings.update');

            Route::get('billing', fn() => response()->json(['billing' => []]))->name('owner.billing.index');
            Route::post('billing/refresh', fn() => response()->json(['ok' => true]))->name('owner.billing.refresh');

            Route::match(['put', 'patch'], 'institute/profile', [InstituteProfileController::class, 'update'])->name('institute.profile.update');

            Route::delete('levels/{level}', [LevelController::class, 'destroy'])->whereNumber('level')->name('levels.destroy');
            Route::delete('grades/{grade}', [GradeController::class, 'destroy'])->whereNumber('grade')->name('grades.destroy');

            Route::delete('session-grades/{sessionGrade}', [SessionGradeController::class, 'destroy'])->whereNumber('sessionGrade')->name('session-grades.destroy');
        });

        /* ------------------------------------------------
         | Admin Suite — Admin plus (Admin, Owner, Developer)
         | Users CRUD (URL prefix সরানো হয়েছে)
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['ADMIN_PLUS']])->group(function () {
            // Users (CRUD) => /api/v1/users ...
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}', [UserController::class, 'show'])->whereNumber('user')->name('users.show');
            Route::put('users/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.update');
            Route::patch('users/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.patch');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->whereNumber('user')->name('users.destroy');

            Route::post('sessions', [AcademicSessionController::class, 'store'])->name('sessions.store');
            Route::match(['put', 'patch'], 'sessions/{session}', [AcademicSessionController::class, 'update'])->whereNumber('session')->name('sessions.update');
            Route::delete('sessions/{session}', [AcademicSessionController::class, 'destroy'])->whereNumber('session')->name('sessions.destroy');

            Route::post('levels', [LevelController::class, 'store'])->name('levels.store');
            Route::put('levels/{level}', [LevelController::class, 'update'])->whereNumber('level')->name('levels.update');
            Route::patch('levels/{level}', [LevelController::class, 'update'])->whereNumber('level')->name('levels.patch');

            Route::post('grades', [GradeController::class, 'store'])->name('grades.store');
            Route::put('grades/{grade}', [GradeController::class, 'update'])->whereNumber('grade')->name('grades.update');
            Route::patch('grades/{grade}', [GradeController::class, 'update'])->whereNumber('grade')->name('grades.patch');

            Route::post('session-grades', [SessionGradeController::class, 'store'])->name('session-grades.store');
            Route::match(['put', 'patch'], 'session-grades/{sessionGrade}', [SessionGradeController::class, 'update'])->whereNumber('sessionGrade')->name('session-grades.update');
        });

        /* ------------------------------------------------
         | Teacher Suite — Teacher plus
         | (URL prefix সরানো হয়েছে)
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
         | (URL prefix সরানো হয়েছে)
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['ACCOUNTANT_PLUS']])->group(function () {
            Route::get('invoices', fn() => response()->json(['invoices' => []]))->name('finance.invoices.index');
            Route::post('invoices', fn() => response()->json(['created' => true]))->name('finance.invoices.store');
            Route::post('reconcile', fn() => response()->json(['ok' => true]))->name('finance.reconcile');
        });

        /* ------------------------------------------------
         | Guardian Suite — Guardian plus
         | (URL prefix সরানো হয়েছে)
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['GUARDIAN_PLUS']])->group(function () {
            Route::get('wards', fn() => response()->json(['wards' => []]))->name('guardian.wards.index');
            Route::get('payments', fn() => response()->json(['payments' => []]))->name('guardian.payments.index');
        });

        /* ------------------------------------------------
         | Student Suite — Student plus (everyone)
         | (URL prefix সরানো হয়েছে)
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
