<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if (! in_array($user->role, ['super_admin', 'logistics'], true)) {
            abort(403);
        }

        $status = $request->query('status');

        $returnsQuery = Transfer::query()
            ->with([
                'items.material',
                'items.serial',
                'fromLocation',
                'toLocation',
                'initiator',
            ])
            ->where('type', 'return');

        if ($request->filled('from')) {
            $from = now()->parse($request->query('from'))->startOfDay();
            $returnsQuery->where('created_at', '>=', $from);
        }

        if ($request->filled('to')) {
            $to = now()->parse($request->query('to'))->endOfDay();
            $returnsQuery->where('created_at', '<=', $to);
        }


        if ($status && in_array($status, ['pending', 'accepted', 'rejected', 'cancelled'], true)) {
            $returnsQuery->where('status', $status);
        }

        $returns = $returnsQuery
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.returns-history', [
            'returns' => $returns,
            'selectedStatus' => $status,
        ]);
    }
}
