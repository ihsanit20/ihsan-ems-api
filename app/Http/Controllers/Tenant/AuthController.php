<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Services\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * POST /api/tenant/token/login
     * body: { identifier: "017...", password: "...", device?: "nuxt" }
     * identifier = phone বা email (দুটোই সাপোর্ট)
     */
    public function tokenLogin(Request $request, TenantManager $tm)
    {
        abort_unless($tm->tenant(), 400, 'Tenant context missing');

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:191'], // phone or email
            'password'   => ['required', 'string', 'min:4'],
            'device'     => ['nullable', 'string', 'max:60'],
        ]);

        $identifier = $data['identifier'];

        $query = User::query();
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $query->where('email', $identifier);
        } else {
            $query->where('phone', $identifier);
        }

        /** @var User|null $user */
        $user = $query->first();

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        $token = $user->createToken($data['device'] ?? 'api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }

    /**
     * GET /api/tenant/me  (Authorization: Bearer xxx)
     */
    public function me(Request $request)
    {
        /** @var User|null $u */
        $u = $request->user();
        return response()->json($u);
    }

    /**
     * POST /api/tenant/token/logout
     */
    public function tokenLogout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/tenant/token/logout-all
     */
    public function revokeAllTokens(Request $request)
    {
        $request->user()?->tokens()->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/tenant/register  (optional)
     * body: { name, phone, email?, password?, role? }
     */
    public function register(Request $request, TenantManager $tm)
    {
        abort_unless($tm->tenant(), 400, 'Tenant context missing');

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:191'],
            'phone'    => ['required', 'string', 'max:32', 'unique:tenant.users,phone'],
            'email'    => ['nullable', 'email', 'max:191', 'unique:tenant.users,email'],
            'password' => ['nullable', 'string', 'min:4'],
            'role'     => ['nullable', Rule::in(['Developer', 'Owner', 'Admin', 'Teacher', 'Accountant', 'Guardian', 'Student'])],
        ]);

        $u = User::create($data + [
            'role' => $data['role'] ?? 'Guardian',
        ]);

        return response()->json([
            'id' => $u->id,
            'name' => $u->name,
            'phone' => $u->phone,
            'email' => $u->email,
            'role' => $u->role,
        ], 201);
    }
}
