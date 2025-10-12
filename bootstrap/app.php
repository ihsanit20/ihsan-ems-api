<?php

use App\Http\Middleware\IdentifyTenant;          // ⬅️ যোগ করুন
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',      // ⬅️ api ব্যবহার করলে ঠিক আছে
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Cookie encryption
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // 🔰 টেন্যান্ট আইডেন্টিফাই—stack-এর একদম শুরুতে (প্রথমেই চলবে)
        // Central domain হলে IdentifyTenant নিজেই স্কিপ করবে।
        $middleware->web(prepend: [
            IdentifyTenant::class,
        ]);

        // (ঐচ্ছিক) যদি API-ও সাবডোমেইনভিত্তিক টেন্যান্টে চালাতে চান, এটাও রাখুন।
        // দরকার না হলে এই ব্লকটা মুছে দিন।
        $middleware->api(prepend: [
            IdentifyTenant::class,
        ]);

        // বাকি web middleware গুলো আগের মতো append থাকুক
        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
