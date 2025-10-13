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
        $logs = UserActionLog::with(['actor', 'target'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.user-history', [
            'logs' => $logs,
        ]);
    }
}
