<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Models\MappingIndex;
use App\Models\UploadLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $division = $user->division;
        $mappings = collect();
        $uploadStats = null;

        if ($user && $user->division_id) {
            if ($division->isSuperUser()) {
                // SuperUser bisa lihat semua
                $mappings = MappingIndex::with('columns')->orderBy('description')->get();
                $uploadStats = $this->getUploadStatsForSuperUser();
            } else {
                // Divisi biasa hanya lihat mapping mereka
                $mappings = MappingIndex::with('columns')
                    ->where('division_id', $user->division_id)
                    ->orderBy('description')
                    ->get();
            }
        }

        return view('dashboard', [
            'mappings' => $mappings,
            'uploadStats' => $uploadStats,
        ]);
    }

    private function getUploadStatsForSuperUser()
    {
        $fourWeeksAgo = Carbon::now()->subWeeks(4)->startOfWeek();
        
        $stats = UploadLog::select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('WEEK(created_at, 1) as week'),
                'division_id',
                DB::raw('COUNT(*) as total_uploads'),
                DB::raw('SUM(rows_imported) as total_rows')
            )
            ->where('created_at', '>=', $fourWeeksAgo)
            ->where('status', 'success')
            ->with('division')
            ->groupBy('year', 'week', 'division_id')
            ->orderBy('year', 'asc')
            ->orderBy('week', 'asc')
            ->get();

        $divisions = $stats->pluck('division.name')->unique()->values();
        
        $weeks = [];
        for ($i = 3; $i >= 0; $i--) {
            $weekStart = Carbon::now()->subWeeks($i)->startOfWeek();
            $weeks[] = [
                'label' => $weekStart->format('d M'),
                'week' => $weekStart->week,
                'year' => $weekStart->year,
            ];
        }

        $chartData = [
            'labels' => collect($weeks)->pluck('label')->toArray(),
            'datasets' => []
        ];

        foreach ($divisions as $divisionName) {
            $divisionData = [];
            
            foreach ($weeks as $week) {
                $found = $stats->first(function ($stat) use ($week, $divisionName) {
                    return $stat->week == $week['week'] 
                        && $stat->year == $week['year']
                        && $stat->division->name == $divisionName;
                });
                
                $divisionData[] = $found ? $found->total_uploads : 0;
            }

            $chartData['datasets'][] = [
                'label' => $divisionName,
                'data' => $divisionData
            ];
        }

        return $chartData;
    }
}