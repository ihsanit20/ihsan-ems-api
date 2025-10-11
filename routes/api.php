<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'ok'  => true,
        'env' => app()->environment(),
        'ts'  => now()->toIso8601String(),
    ]);
});
