<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TechnicianOverviewController extends Controller
{
    /**
     * List technicians for logistics and administrators.
     */
    public function index(Request $request): View
    {
        $role = $request->user()->role ?? null;

        if (! in_array($role, ['admin', 'super_admin', 'logistics'], true)) {
            abort(403);
        }

        $technicians = User::technicians()
            ->with('stockLocation')
            ->orderBy('name')
            ->get();

        return view('logistics.technicians', [
            'technicians' => $technicians,
        ]);
    }

    /**
     * Show stock of a specific technician.
     */
    public function stock(Request $request, User $technician): View
    {
        $currentUser = $request->user();
        $role = $currentUser->role ?? null;

        if ($technician->role !== 'technician') {
            abort(404);
        }

        if ($currentUser->id !== $technician->id && ! in_array($role, ['admin', 'super_admin', 'logistics'], true)) {
            abort(403);
        }

        $location = $this->ensureTechnicianLocation($technician);

        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $location->id)
            ->orderBy('material_id')
            ->get();

        $serials = MaterialSerial::query()
            ->with('material')
            ->where('current_location_id', $location->id)
            ->where('status', 'assigned')
            ->orderBy('material_id')
            ->orderBy('serial_number')
            ->get()
            ->groupBy('material_id');

        $nonSerializedInventory = $inventory->filter(fn ($item) => $item->material && ! $item->material->is_serialized)->values();
        $serializedInventory = $inventory->filter(fn ($item) => $item->material && $item->material->is_serialized)->values()->keyBy('material_id');

        return view('logistics.technician-stock', [
            'technician' => $technician,
            'location' => $location,
            'nonSerializedInventory' => $nonSerializedInventory,
            'serializedInventory' => $serializedInventory,
            'serializedGroups' => $serials,
        ]);
    }

    /**
     * Ensure technician has a stock location.
     */
    protected function ensureTechnicianLocation(User $technician): StockLocation
    {
        $location = $technician->stockLocation()->first();

        if ($location) {
            return $location;
        }

        return StockLocation::create([
            'location_type' => 'user',
            'ref_id' => $technician->id,
            'name' => 'Stock de ' . $technician->name,
        ]);
    }
}
