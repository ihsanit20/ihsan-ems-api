<?php

namespace App\Http\Controllers;

use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TenantAuthController extends Controller
{
    // POST /api/auth/register
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'phone'    => ['required', 'string', 'max:32', 'unique:tenant.users,phone'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = TenantUser::create($data); // password অটো-হ্যাশ হবে (casts)
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'ok'    => true,
            'token' => $token,
            'user'  => ['id' => $user->id, 'name' => $user->name, 'phone' => $user->phone],
        ], 201);
    }

    // POST /api/auth/login
    public function login(Request $request)
    {
        $creds = $request->validate([
            'phone'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Auth ডিফল্ট গার্ড API-তে কাজ নাও করতে পারে, তাই ম্যানুয়াল চেক
        $user = TenantUser::where('phone', $creds['phone'])->first();

        if (! $user || ! password_verify($creds['password'], $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['Invalid credentials.'],
            ]);
        }

        // পুরনো টোকেন ক্লিয়ার করতে চাইলে uncomment করুন:
        // $user->tokens()->delete();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'ok'    => true,
            'token' => $token,
            'user'  => ['id' => $user->id, 'name' => $user->name, 'phone' => $user->phone],
        ]);
    }

    // GET /api/auth/me
    public function me(Request $request)
    {
        /** @var TenantUser $user */
        $user = $request->user();
        return response()->json([
            'ok'   => true,
            'user' => ['id' => $user->id, 'name' => $user->name, 'phone' => $user->phone],
        ]);
    }

    // POST /api/auth/logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }
}
