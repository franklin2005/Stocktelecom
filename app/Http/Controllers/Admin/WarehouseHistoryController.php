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

        $movements = StockMovement::query()
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
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.warehouse-history', [
            'movements' => $movements,
            'warehouse' => $warehouse,
        ]);
    }
}
