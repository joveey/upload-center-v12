<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        // Get all divisions untuk dropdown, exclude superuser divisions
        $divisions = Division::where('is_super_user', false)
            ->orderBy('name')
            ->get();
        
        return view('auth.register', compact('divisions'));
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'division_id' => ['required', 'exists:divisions,id'], 
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Prevent registration with superuser division
        $division = Division::findOrFail($request->division_id);
        if ($division->is_super_user) {
            return back()->withErrors([
                'division_id' => 'Tidak dapat mendaftar dengan divisi superuser. Silakan pilih divisi lain.'
            ])->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'division_id' => $request->division_id,
            'password' => Hash::make($request->password),
        ]);

        // Assign role default
        $user->assignRole('division-user');

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}