<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\MetaController;
use App\Http\Controllers\Tenant\AuthController;
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
| নীচের $ALLOW ম্যাপ ব্যবহার করে প্রতিটি প্যাকেটের জন্য সহজে role গার্ড সেট করা হয়েছে।
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

        Route::get('tenant/meta', [MetaController::class, 'show'])->name('meta.show');

        Route::post('auth/login', [AuthController::class, 'tokenLogin'])
            ->middleware('throttle:tenant-auth')
            ->name('auth.login');

        Route::post('auth/register', [AuthController::class, 'register'])
            ->middleware('throttle:tenant-auth')
            ->name('auth.register');

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
         | (Owner ইচ্ছাকৃতভাবেই নেই)
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['DEV_ONLY']])
            ->prefix('dev')->as('dev.')->group(function () {
                Route::get('info', function () {
                    return response()->json([
                        'env' => app()->environment(),
                        'php' => PHP_VERSION,
                        'time' => now()->toDateTimeString(),
                    ]);
                })->name('info');

                // উদাহরণ: টেন্যান্ট ডায়াগনস্টিক্স, মেইনটেন্যান্স টাস্ক, ইত্যাদি
                Route::post('maintenance/run', fn() => response()->json(['ok' => true]))->name('maintenance.run');
            });

        /* ------------------------------------------------
         | Owner Suite — Owner plus (Owner, Developer)
         | (Developer allowed, Admin/others not)
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['OWNER_PLUS']])
            ->prefix('owner')->as('owner.')
            ->group(function () {
                Route::get('settings', fn() => response()->json(['settings' => true]))->name('settings.index');
                Route::post('settings', fn() => response()->json(['updated' => true]))->name('settings.update');

                // Billing/plan endpoints (উদাহরণ)
                Route::get('billing', fn() => response()->json(['billing' => []]))->name('billing.index');
                Route::post('billing/refresh', fn() => response()->json(['ok' => true]))->name('billing.refresh');
            });

        /* ------------------------------------------------
         | Admin Suite — Admin plus (Admin, Owner, Developer)
         | এখানে Users CRUD রাখা হয়েছে
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['ADMIN_PLUS']])
            ->prefix('admin')->as('admin.')
            ->group(function () {
                // Users (CRUD)
                Route::get('users', [UserController::class, 'index'])->name('users.index');
                Route::post('users', [UserController::class, 'store'])->name('users.store');
                Route::get('users/{user}', [UserController::class, 'show'])->whereNumber('user')->name('users.show');
                Route::put('users/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.update');
                Route::patch('users/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.patch');
                Route::delete('users/{user}', [UserController::class, 'destroy'])->whereNumber('user')->name('users.destroy');

                // Admin রিপোর্ট/মনিটরিং (উদাহরণ)
                Route::get('reports/summary', fn() => response()->json(['report' => 'summary']))->name('reports.summary');
            });

        /* ------------------------------------------------
         | Teacher Suite — Teacher plus (Teacher, Admin, Owner, Developer)
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['TEACHER_PLUS']])
            ->prefix('teacher')->as('teacher.')
            ->group(function () {
                Route::get('courses', fn() => response()->json(['courses' => []]))->name('courses.index');
                Route::post('courses', fn() => response()->json(['created' => true]))->name('courses.store');

                Route::get('lectures', fn() => response()->json(['lectures' => []]))->name('lectures.index');
                Route::post('lectures', fn() => response()->json(['created' => true]))->name('lectures.store');

                Route::post('exams/publish', fn() => response()->json(['published' => true]))->name('exams.publish');
            });

        /* ------------------------------------------------
         | Accountant Suite — Accountant plus
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['ACCOUNTANT_PLUS']])
            ->prefix('finance')->as('finance.')
            ->group(function () {
                Route::get('invoices', fn() => response()->json(['invoices' => []]))->name('invoices.index');
                Route::post('invoices', fn() => response()->json(['created' => true]))->name('invoices.store');
                Route::post('reconcile', fn() => response()->json(['ok' => true]))->name('reconcile');
            });

        /* ------------------------------------------------
         | Guardian Suite — Guardian plus
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['GUARDIAN_PLUS']])
            ->prefix('guardian')->as('guardian.')
            ->group(function () {
                Route::get('wards', fn() => response()->json(['wards' => []]))->name('wards.index');
                Route::get('payments', fn() => response()->json(['payments' => []]))->name('payments.index');
            });

        /* ------------------------------------------------
         | Student Suite — Student plus (everyone)
         * ------------------------------------------------ */
        Route::middleware(['auth:sanctum', $ALLOW['STUDENT_PLUS']])
            ->prefix('student')->as('student.')
            ->group(function () {
                Route::get('dashboard', fn() => response()->json(['welcome' => 'student']))->name('dashboard');
                Route::get('classes', fn() => response()->json(['classes' => []]))->name('classes.index');
                Route::post('exams/attempt', fn() => response()->json(['attempted' => true]))->name('exams.attempt');
            });

        /* ---------- v1 Fallback (JSON 404) ---------- */
        Route::fallback(fn() => response()->json(['message' => 'Not Found.'], 404))
            ->name('fallback');
    });
