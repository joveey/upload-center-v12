<?php

namespace App\Http\Controllers;

use App\Models\UploadLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UploadLogController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $search = trim((string) $request->input('q', ''));
        $selectedUserId = $request->input('user_id');
        $perPage = 20;

        $isAdmin = $this->userHasRole($user, 'superuser')
            || $this->userHasRole($user, 'admin')
            || optional($user->division)->is_super_user;

        $query = UploadLog::with(['mappingIndex', 'division', 'user'])
            ->orderByDesc('created_at');

        if (! $isAdmin) {
            $query->where('user_id', $user->id);
        } elseif ($selectedUserId) {
            $query->where('user_id', $selectedUserId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('file_name', 'like', $like)
                    ->orWhere('action', 'like', $like)
                    ->orWhere('upload_mode', 'like', $like)
                    ->orWhereHas('mappingIndex', function ($q2) use ($like) {
                        $q2->where('description', 'like', $like)
                            ->orWhere('code', 'like', $like);
                    });
            });
        }

        $logs = $query->paginate($perPage)->withQueryString();

        return view('logs.index', [
            'logs' => $logs,
            'search' => $search,
            'isAdmin' => $isAdmin,
            'selectedUserId' => $selectedUserId,
            'users' => $isAdmin ? User::orderBy('name')->get(['id', 'name', 'email']) : collect([$user]),
        ]);
    }
}
