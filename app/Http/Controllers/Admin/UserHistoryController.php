<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserActionLog;
use Illuminate\View\View;

class UserHistoryController extends Controller
{
    /**
     * Display the user action history.
     */
    public function index(): View
    {
        $logsQuery = UserActionLog::with(['actor', 'target'])
            ->orderByDesc('created_at');

        if (request()->filled('from')) {
            $logsQuery->where('created_at', '>=', now()->parse(request('from'))->startOfDay());
        }

        if (request()->filled('to')) {
            $logsQuery->where('created_at', '<=', now()->parse(request('to'))->endOfDay());
        }

        $logs = $logsQuery->paginate(25)->withQueryString();

        return view('admin.user-history', [
            'logs' => $logs,
            'from' => request('from'),
            'to' => request('to'),
        ]);
    }
}
