<?php

namespace App\Services\Staff;

use App\Models\Inventory;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class StaffStockManager
{
    public function syncLogisticsLocation(User $staffUser, string $name): void
    {
        StockLocation::updateOrCreate(
            [
                'location_type' => 'user',
                'ref_id'        => $staffUser->id,
            ],
            [
                'name' => 'Stock de ' . $name,
            ]
        );
    }

    public function handleLogisticsLocationOnUpdate(User $staff, string $previousRole, string $newRole, string $name): void
    {
        if ($newRole === 'logistics') {
            $this->syncLogisticsLocation($staff, $name);
        } elseif ($previousRole === 'logistics') {
            $staff->stockLocation()->delete();
        }
    }

    public function ensureLogisticsHasNoStock(?StockLocation $location): void
    {
        if (! $location) {
            return;
        }

        $hasInventory = Inventory::query()
            ->where('location_id', $location->id)
            ->where('quantity', '>', 0)
            ->exists();

        $hasSerials = MaterialSerial::query()
            ->where('current_location_id', $location->id)
            ->exists();

        if ($hasInventory || $hasSerials) {
            throw ValidationException::withMessages([
                'general' => 'No se puede eliminar este usuario de logistica mientras tenga stock asignado.',
            ])->errorBag('deleteStaff')->redirectTo(
                route('admin.personnel', ['tab' => 'logistics'])
            );
        }
    }
}
