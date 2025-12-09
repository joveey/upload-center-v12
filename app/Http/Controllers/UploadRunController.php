<?php

namespace App\Http\Controllers;

use App\Models\UploadRun;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadRunController extends Controller
{
    public function recent(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min(20, $limit));

        $query = UploadRun::query()
            ->with('mappingIndex:id,description,code')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($request->filled('mapping_id')) {
            $query->where('mapping_index_id', $request->query('mapping_id'));
        }

        $runs = $query->get()->map(function ($run) {
            return [
                'id' => $run->id,
                'mapping_id' => $run->mapping_index_id,
                'format' => $run->mappingIndex->description ?? $run->mappingIndex->code ?? '-',
                'period_date' => $run->period_date,
                'status' => $run->status,
                'progress_percent' => $run->progress_percent,
                'message' => Str::limit(trim((string) $run->message), 220),
                'created_at' => optional($run->created_at)->toDateTimeString(),
                'upload_mode' => $run->upload_mode,
            ];
        });

        return response()->json(['data' => $runs]);
    }

    public function clear(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 403);

        // Hapus yang sudah selesai, dan juga processing yang sudah lama (default >15 menit)
        $stuckMinutes = (int) $request->query('stuck_minutes', 15);
        $cutoff = now()->subMinutes(max(1, $stuckMinutes));

        UploadRun::where('user_id', $user->id)
            ->where(function ($q) use ($cutoff) {
                $q->whereIn('status', ['success', 'failed'])
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->where('status', 'processing')
                         ->where('updated_at', '<', $cutoff);
                  });
            })
            ->delete();

        return response()->json(['success' => true]);
    }
}
