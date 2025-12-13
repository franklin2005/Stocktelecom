<?php

namespace App\Services\Admin\Staff;

use App\Models\Inventory;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class StaffStockManager
{   // sincronizar o crear ubicacion de stock para usuario de logistica
    public function syncLogisticsLocation(User $staffUser, string $name): void
    {   // actualizar o crear ubicacion de stock
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
    // manejar ubicacion de stock al actualizar rol de usuario
    public function handleLogisticsLocationOnUpdate(User $staff, string $previousRole, string $newRole, string $name): void
    {
        if ($newRole === 'logistics') {
            $this->syncLogisticsLocation($staff, $name);
        } elseif ($previousRole === 'logistics') {
            $staff->stockLocation()->delete();
        }
    }
    // asegurar que el personal de logistica no tenga stock asignado
    public function ensureLogisticsHasNoStock(?StockLocation $location): void
    {
        if (! $location) {
            return;
        }
        // verificar si hay inventario o seriales asignados
        $hasInventory = Inventory::query()
            ->where('location_id', $location->id)
            ->where('quantity', '>', 0)
            ->exists();
        // verificar seriales
        $hasSerials = MaterialSerial::query()
            ->where('current_location_id', $location->id)
            ->exists();
        // si hay inventario o seriales, lanzar error
        if ($hasInventory || $hasSerials) {
            throw ValidationException::withMessages([
                'general' => 'No se puede eliminar este usuario de logistica mientras tenga stock asignado.',
            ])->errorBag('deleteStaff')->redirectTo(
                route('admin.personnel', ['tab' => 'logistics'])
            );
        }
    }
}
