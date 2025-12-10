<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Transfer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class WarehouseHistoryController extends Controller
{
    public function index(): View
    {
        $warehouse = StockLocation::warehouses()->first();

        $fromDate = request()->filled('from') ? now()->parse(request('from'))->startOfDay() : null;
        $toDate = request()->filled('to') ? now()->parse(request('to'))->endOfDay() : null;

        // Base query para movimientos que afectan al almacén (no transferencias agrupadas)
        $baseMovementQuery = StockMovement::query()
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
            });

        // Movimientos no-transferencia (ajustes, bajas, etc.), excluyendo entradas de transferencias
        $nonTransferMovements = (clone $baseMovementQuery)
            ->where(function ($query) {
                $query->whereNull('reference_type')
                    ->orWhere('reference_type', '!=', 'transfer');
            })
            ->where('movement_type', '!=', 'transfer_in')
            ->when($fromDate, fn ($q) => $q->where('performed_at', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('performed_at', '<=', $toDate))
            ->orderByDesc('performed_at')
            ->orderByDesc('id')
            ->get()
            ->map(function ($movement) {
                $movement->record_type = 'movement';
                return $movement;
            });

        // Transferencias de salida desde el almacén agrupadas por transferencia
        $transferMovements = (clone $baseMovementQuery)
            ->where('reference_type', 'transfer')
            ->where('movement_type', 'transfer_out')
            ->when($warehouse, fn ($q) => $q->where('from_location_id', $warehouse->id))
            ->when($fromDate, fn ($q) => $q->where('performed_at', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('performed_at', '<=', $toDate))
            ->get();

        $transferIds = $transferMovements->pluck('reference_id')->filter()->unique()->values();
        $transfers = Transfer::with(['fromLocation', 'toLocation', 'initiator'])
            ->whereIn('id', $transferIds)
            ->get()
            ->keyBy('id');

        $transferSummaries = $transferMovements
            ->groupBy('reference_id')
            ->map(function ($group, $transferId) use ($transfers) {
                $transfer = $transfers->get($transferId);
                $ordered = $group->sortBy('performed_at');
                $first = $ordered->first();

                return (object) [
                    'record_type' => 'transfer',
                    'transfer' => $transfer,
                    'transfer_id' => $transferId,
                    'performed_at' => $ordered->first()?->performed_at ?? $ordered->first()?->created_at ?? now(),
                    'quantity' => $group->sum('quantity'),
                    'fromLocation' => $transfer?->fromLocation,
                    'toLocation' => $transfer?->toLocation,
                    'initiator' => $transfer?->initiator,
                    'performer' => $first?->performer,
                    'movements' => $group,
                ];
            })
            ->values();

        // Mezcla transferencias resumidas + movimientos sueltos y pagina manualmente
        $combined = $transferSummaries
            ->concat($nonTransferMovements)
            ->sortByDesc(fn ($item) => $item->performed_at ?? $item->created_at)
            ->values();

        $perPage = 20;
        $currentPage = request()->integer('page', 1);
        $pagedItems = $combined->forPage($currentPage, $perPage)->values();
        $movements = new LengthAwarePaginator(
            $pagedItems,
            $combined->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        // Nota: transferencias se agrupan por referencia para evitar filas duplicadas; los movimientos sueltos mantienen el detalle.

        return view('admin.warehouse-history', [
            'movements' => $movements,
            'warehouse' => $warehouse,
            'from' => request('from'),
            'to' => request('to'),
        ]);
    }

    /**
     * Detalle de una transferencia de almacén (agrupada).
     */
    public function showTransfer(Transfer $transfer): View
    {
        $warehouse = StockLocation::warehouses()->first();

        if ($warehouse && $transfer->from_location_id !== $warehouse->id) {
            abort(404);
        }

        $movements = StockMovement::query()
            ->with(['material', 'serial', 'fromLocation', 'toLocation', 'performer'])
            ->where('reference_type', 'transfer')
            ->where('reference_id', $transfer->id)
            ->where('movement_type', 'transfer_out')
            ->orderByDesc('performed_at')
            ->orderByDesc('id')
            ->get();

        $totalUnits = $movements->sum('quantity');
        $date = $movements->sortBy('performed_at')->first()?->performed_at ?? $transfer->created_at;

        return view('admin.warehouse-history-transfer-show', [
            'transfer' => $transfer->load(['fromLocation', 'toLocation', 'initiator']),
            'movements' => $movements,
            'totalUnits' => $totalUnits,
            'performedAt' => $date,
        ]);
    }
}
