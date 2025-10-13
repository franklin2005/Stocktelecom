<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\View\View;

class WarehouseHistoryController extends Controller
{
    public function index(): View
    {
        $warehouse = StockLocation::warehouses()->first();

        $movementsQuery = StockMovement::query()
            ->with([
                'material',
                'serial',
                'fromLocation',
                'toLocation',
                'performer',
                'transfer.initiator',
            ])
            ->when($warehouse, function ($query) use ($warehouse) {
                $query->where(function ($inner) use ($warehouse) {
                    $inner->where('from_location_id', $warehouse->id)
                        ->orWhere('to_location_id', $warehouse->id);
                });
            })
            ->orderByDesc('performed_at')
            ->orderByDesc('id');

        if (request()->filled('from')) {
            $from = now()->parse(request('from'))->startOfDay();
            $movementsQuery->where('performed_at', '>=', $from);
        }

        if (request()->filled('to')) {
            $to = now()->parse(request('to'))->endOfDay();
            $movementsQuery->where('performed_at', '<=', $to);
        }

        $movements = $movementsQuery->paginate(20)->withQueryString();

        return view('admin.warehouse-history', [
            'movements' => $movements,
            'warehouse' => $warehouse,
            'from' => request('from'),
            'to' => request('to'),
        ]);
    }
}
