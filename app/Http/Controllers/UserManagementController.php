<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    /**
     * Show user management screen for admins.
     */
    public function index(): View
    {
        $divisions = Division::orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('divisions', 'roles'));
    }

    /**
     * Create a new user on behalf of admin.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'division_id' => ['required', 'exists:divisions,id'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $division = Division::findOrFail($request->division_id);
        $roleName = $request->role;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'division_id' => $division->id,
            'password' => Hash::make($request->password),
        ]);

        $guard = config('auth.defaults.guard', 'web');
        Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => $guard,
        ]);
        $user->assignRole($roleName);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User baru berhasil dibuat oleh admin.');
    }

    /**
     * Update user info (role & division).
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'division_id' => ['required', 'exists:divisions,id'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->division_id = $request->division_id;
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->save();

        $guard = config('auth.defaults.guard', 'web');
        Role::firstOrCreate([
            'name' => $request->role,
            'guard_name' => $guard,
        ]);
        $user->syncRoles([$request->role]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User berhasil diperbarui.');
    }

    /**
     * Delete a user.
     */
    public function destroy(User $user): RedirectResponse
    {
        // Optional guard: prevent self-delete
        if (auth()->id() === $user->id) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User berhasil dihapus.');
    }

    /**
     * List users with search/pagination for superuser.
     */
    public function list(Request $request): View
    {
        $search = $request->query('q');
        $divisions = Division::orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        $users = User::with(['division', 'roles'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.list', compact('users', 'divisions', 'roles', 'search'));
    }
}
