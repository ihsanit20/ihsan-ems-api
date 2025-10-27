<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /** DB enum অনুযায়ী allowed roles */
    private const ROLES = [
        'Developer',
        'Owner',
        'Admin',
        'Teacher',
        'Accountant',
        'Guardian',
        'Student',
    ];

    /**
     * GET /v1/users
     * q, role, per_page, sort_by, sort_dir সাপোর্ট করে
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 200));

        $q       = trim((string) $request->input('q', ''));
        $role    = $request->filled('role') ? (string) $request->input('role') : null;

        $sortBy  = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['id', 'name', 'email', 'phone', 'role', 'created_at'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        $builder = User::query()
            ->when($role, fn($qb) => $qb->where('role', $role))
            ->when($q, function ($qb) use ($q) {
                $qb->where(function ($qq) use ($q) {
                    $qq->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->orderBy($sortBy, $sortDir);

        $paginator = $builder->paginate($perPage);
        $paginator->getCollection()->each->append('photo_url');

        return response()->json($paginator);
    }

    /** GET /v1/users/{user} */
    public function show(User $user)
    {
        $user->append('photo_url');
        return response()->json($user);
    }

    /** POST /v1/users  (password nullable; name+phone required) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:191'],
            'phone'    => ['required', 'string', 'max:32', Rule::unique('users', 'phone')],
            'email'    => ['nullable', 'email', 'max:191'], // unique নয়
            'role'     => ['nullable', 'string', Rule::in(self::ROLES)],
            'password' => ['nullable', 'string', 'min:6'],
            'photo'    => ['nullable', 'image', 'max:2048'],
        ]);

        $user = new User();
        $user->name  = $data['name'];
        $user->phone = $data['phone'];
        $user->email = $data['email'] ?? null;
        // role না দিলে DB default Guardian প্রযোজ্য হবে
        if (array_key_exists('role', $data)) {
            $user->role = $data['role'];
        }

        $user->password = !empty($data['password']) ? Hash::make($data['password']) : null;

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('users', 'public');
            $user->photo = $path;
        }

        $user->save();
        $user->append('photo_url');

        return response()->json($user, 201);
    }

    /** PUT/PATCH /v1/users/{user} */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'         => ['sometimes', 'required', 'string', 'max:191'],
            'phone'        => ['sometimes', 'required', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($user->id)],
            'email'        => ['nullable', 'email', 'max:191'], // unique নয়
            'role'         => ['nullable', 'string', Rule::in(self::ROLES)],
            'password'     => ['nullable', 'string', 'min:6'],
            'photo'        => ['nullable', 'image', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('name', $data))  $user->name  = $data['name'];
        if (array_key_exists('phone', $data)) $user->phone = $data['phone'];
        if (array_key_exists('email', $data)) $user->email = $data['email'] ?? null;
        if (array_key_exists('role', $data))  $user->role  = $data['role']; // null allow নয়; enum থেকে আসবে

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        } elseif (array_key_exists('password', $data) && $data['password'] === null) {
            // চাইলে পাসওয়ার্ড null করা যাবে
            $user->password = null;
        }

        // photo remove
        if ($request->boolean('remove_photo') && $user->photo) {
            Storage::disk('public')->delete($user->photo);
            $user->photo = null;
        }
        // new photo
        if ($request->hasFile('photo')) {
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }
            $path = $request->file('photo')->store('users', 'public');
            $user->photo = $path;
        }

        $user->save();
        $user->append('photo_url');

        return response()->json($user);
    }

    /** DELETE /v1/users/{user} */
    public function destroy(User $user)
    {
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }
        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }
}
