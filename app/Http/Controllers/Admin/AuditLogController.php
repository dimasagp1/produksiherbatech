<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $event = $request->input('event', '');
        $userId = $request->input('user_id', '');
        $model = $request->input('auditable_type', '');

        $query = AuditLog::query()
            ->orderByDesc('id');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('auditable_type', 'like', "%{$search}%")
                    ->orWhere('auditable_id', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%");
            });
        }

        if (! empty($event)) {
            $query->where('event', $event);
        }

        if (! empty($userId)) {
            $query->where('user_id', $userId);
        }

        if (! empty($model)) {
            $query->where('auditable_type', 'like', "%{$model}%");
        }

        $logs = $query->paginate(20)->withQueryString();

        // Tambahkan nama user dari user_id jika ada
        $userIds = $logs->pluck('user_id')->filter()->unique();
        $usersMap = User::whereIn('id', $userIds)->pluck('name', 'id');

        $users = User::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Admin/AuditLog/Index', [
            'logs' => $logs,
            'users' => $users,
            'usersMap' => $usersMap,
            'filters' => [
                'search' => $search,
                'event' => $event,
                'user_id' => $userId,
                'auditable_type' => $model,
            ],
        ]);
    }
}
