<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DivisionController extends Controller
{
    /**
     * Show list of divisions and create form.
     */
    public function index(): View
    {
        $divisions = Division::orderBy('name')->get();
        return view('divisions.index', compact('divisions'));
    }

    /**
     * Store a new division.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:divisions,name'],
        ]);

        Division::create($validated);

        return redirect()
            ->route('divisions.index')
            ->with('success', 'Divisi berhasil dibuat.');
    }

    /**
     * Delete a division (optional: prevent if still used).
     */
    public function destroy(Division $division): RedirectResponse
    {
        $userCount = User::where('division_id', $division->id)->count();
        if ($userCount > 0) {
            return redirect()
                ->route('divisions.index')
                ->with('error', 'Divisi tidak bisa dihapus karena masih dipakai oleh pengguna.');
        }

        $division->delete();

        return redirect()
            ->route('divisions.index')
            ->with('success', 'Divisi berhasil dihapus.');
    }

    /**
     * Update a division name.
     */
    public function update(Request $request, Division $division): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:divisions,name,' . $division->id],
        ]);

        $division->update($validated);

        return redirect()
            ->route('divisions.index')
            ->with('success', 'Divisi berhasil diperbarui.');
    }
}
