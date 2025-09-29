<?php

namespace App\Http\Controllers;

use App\Models\MappingIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the user's dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index(): View
    {
        $user = Auth::user();
        $mappings = collect(); // Default to an empty collection

        // Pastikan pengguna memiliki divisi sebelum query
        if ($user && $user->division_id) {
            // Ambil hanya mapping yang dimiliki oleh divisi pengguna
            $mappings = MappingIndex::where('division_id', $user->division_id)
                ->orderBy('name')
                ->get();
        }

        return view('dashboard', [
            'mappings' => $mappings,
        ]);
    }
}