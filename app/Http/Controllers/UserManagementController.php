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
        $users = User::with(['division', 'roles'])
            ->orderBy('name')
            ->paginate(12);

        return view('users.index', compact('divisions', 'users'));
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
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $division = Division::findOrFail($request->division_id);
        $roleName = $division->is_super_user ? 'super-admin' : 'division-user';

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'division_id' => $division->id,
            'password' => Hash::make($request->password),
        ]);

        Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => config('auth.defaults.guard', 'web'),
        ]);
        $user->syncRoles([$roleName]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User baru berhasil dibuat oleh admin.');
    }
}
