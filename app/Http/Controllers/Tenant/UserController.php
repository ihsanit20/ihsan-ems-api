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
     * Role ranks (strict hierarchy)
     * Higher is more powerful
     */
    private const ROLE_RANK = [
        'Student'    => 1,
        'Guardian'   => 2,
        'Teacher'    => 3,
        'Accountant' => 3,
        'Admin'      => 4,
        'Owner'      => 5,
        'Developer'  => 6,
    ];

    /** ------- helpers: hierarchy & permission checks ------- */

    private static function rankOf(?string $role): int
    {
        return $role && isset(self::ROLE_RANK[$role]) ? self::ROLE_RANK[$role] : 0;
    }

    /**
     * Core guard:
     * - If self-update: allowed, but cannot change own role.
     * - If updating others: actorRank must be strictly greater than targetRank.
     * - If payload includes 'role':
     *      actorRank must be strictly greater than rank(newRole).
     */
    private static function assertCanModify(
        Request $request,
        User $actor,
        User $target,
        array $payload,
        bool $creating = false
    ): void {
        $actorRank  = self::rankOf($actor->role);
        $targetRole = $creating
            ? ($payload['role'] ?? 'Guardian') // creating: target not yet exists; assume payload role or default
            : $target->role;

        $targetRank = self::rankOf($targetRole);

        // 1) Self vs Others
        if (!$creating && $actor->id === $target->id) {
            // Self update allowed (profile/password/photo...), but cannot change own role
            if (array_key_exists('role', $payload) && $payload['role'] !== $target->role) {
                abort(403, 'You cannot change your own role.');
            }
        } else {
            // Updating others: actor must be strictly higher than target
            if (!($actorRank > $targetRank)) {
                abort(403, 'Forbidden: insufficient role to modify this user.');
            }
        }

        // 2) If role is being set/changed (both create & update)
        if (array_key_exists('role', $payload) && $payload['role'] !== null) {
            $newRole  = (string) $payload['role'];
            if (!in_array($newRole, self::ROLES, true)) {
                abort(422, 'Invalid role value.');
            }
            $newRank = self::rankOf($newRole);

            // actor must be strictly higher than the role they are assigning
            if (!($actorRank > $newRank)) {
                abort(403, 'Forbidden: you cannot assign this role.');
            }

            // Optional: prevent lowering/raising beyond policy—already covered by strict ranks.
            // Example: Owner cannot make Developer because 5 > 6 (false) — auto blocked.
        }
    }

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
        // Must be logged-in; routes layer will already require auth, but double-check
        $actor = $request->user();
        if (!$actor) {
            abort(401, 'Unauthenticated.');
        }

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:191'],
            'phone'    => ['required', 'string', 'max:32', Rule::unique(User::class, 'phone')],
            'email'    => ['nullable', 'email', 'max:191'], // unique নয়
            'role'     => ['nullable', 'string', Rule::in(self::ROLES)],
            'password' => ['nullable', 'string', 'min:6'],
            'photo'    => ['nullable', 'image', 'max:2048'],
        ]);

        // Hierarchy guard for CREATE (role assignment)
        self::assertCanModify($request, $actor, new User(), $data, creating: true);

        $user = new User();
        $user->name  = $data['name'];
        $user->phone = $data['phone'];
        $user->email = $data['email'] ?? null;

        // role না দিলে DB default Guardian প্রযোজ্য হবে; দিলেও assertCanModify আগেই চেক করছে
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
        // Must be logged-in
        $actor = $request->user();
        if (!$actor) {
            abort(401, 'Unauthenticated.');
        }

        $data = $request->validate([
            'name'         => ['sometimes', 'required', 'string', 'max:191'],
            'phone'        => [
                'sometimes',
                'required',
                'string',
                'max:32',
                Rule::unique(User::class, 'phone')->ignore($user->id)
            ],
            'email'        => ['nullable', 'email', 'max:191'], // unique নয়
            'role'         => ['nullable', 'string', Rule::in(self::ROLES)],
            'password'     => ['nullable', 'string', 'min:6'],
            'photo'        => ['nullable', 'image', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
        ]);

        // Hierarchy guard for UPDATE (target + optional role change)
        self::assertCanModify($request, $actor, $user, $data, creating: false);

        if (array_key_exists('name', $data))  $user->name  = $data['name'];
        if (array_key_exists('phone', $data)) $user->phone = $data['phone'];
        if (array_key_exists('email', $data)) $user->email = $data['email'] ?? null;

        if (array_key_exists('role', $data)) {
            // assertCanModify already validated we may assign this role
            $user->role  = $data['role'];
        }

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        } elseif (array_key_exists('password', $data) && $data['password'] === null) {
            // চাইলে পাসওয়ার্ড null করা যাবে (policy already allowed by rank)
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
    public function destroy(User $user, Request $request)
    {
        // Must be logged-in
        $actor = $request->user();
        if (!$actor) {
            abort(401, 'Unauthenticated.');
        }

        // Deleting others follows the same strict-rank rule
        self::assertCanModify($request, $actor, $user, [], creating: false);

        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }
        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }
}
