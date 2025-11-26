<?php

namespace App\Services\Admin\AdminReturns;

use App\Models\Material;
use App\Models\StockLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AdminReturnsCartService
{
    private const CART_SESSION_KEY = 'admin_return_cart';

    public function getCart(Request $request): array
    {
        return $request->session()->get(self::CART_SESSION_KEY, []);
    }

    public function saveCart(Request $request, array $cart): void
    {
        $request->session()->put(self::CART_SESSION_KEY, $cart);
    }

    public function clearCart(Request $request): void
    {
        $request->session()->forget(self::CART_SESSION_KEY);
    }

    public function prepareCart(
        Request $request,
        ?StockLocation $technicianLocation,
        Collection $inventory,
        Collection $serials,
        array $pendingReservations
    ): array {
        $cart = $this->getCart($request);
        $items = [];
        $summary = [
            'total_items' => 0,
            'total_units' => 0,
        ];
        $reservedQuantities = [];
        $serialsInCart = [];
        $dirty = false;

        foreach ($cart as $key => $entry) {
            if (! is_array($entry) || ! isset($entry['type'], $entry['material_id'])) {
                unset($cart[$key]);
                $dirty = true;
                continue;
            }

            if ($technicianLocation && (int) ($entry['technician_id'] ?? 0) !== $technicianLocation->ref_id) {
                unset($cart[$key]);
                $dirty = true;
                continue;
            }

            $materialId = (int) $entry['material_id'];
            $material = Material::find($materialId);

            if (! $material) {
                unset($cart[$key]);
                $dirty = true;
                continue;
            }

            if ($entry['type'] === 'quantity') {
                $quantity = (int) ($entry['quantity'] ?? 0);

                if ($quantity < 1) {
                    unset($cart[$key]);
                    $dirty = true;
                    continue;
                }

                $reservedKey = 'quantity-' . $materialId;
                $reservedQuantities[$reservedKey] = $quantity;

                $items[] = [
                    'key' => $key,
                    'type' => 'quantity',
                    'material' => $material,
                    'quantity' => $quantity,
                ];

                $summary['total_items']++;
                $summary['total_units'] += $quantity;
                continue;
            }

            if ($entry['type'] === 'serial' && isset($entry['serial_id'])) {
                $serialId = (int) $entry['serial_id'];
                $serial = $serials->firstWhere('id', $serialId);

                if (! $serial || (int) $serial->material_id !== $materialId) {
                    unset($cart[$key]);
                    $dirty = true;
                    continue;
                }

                $serialsInCart[] = $serialId;

                $items[] = [
                    'key' => $key,
                    'type' => 'serial',
                    'material' => $material,
                    'serial' => $serial,
                ];

                $summary['total_items']++;
                $summary['total_units']++;
                continue;
            }

            unset($cart[$key]);
            $dirty = true;
        }

        if ($dirty) {
            $this->saveCart($request, $cart);
        }

        return [
            'items' => $items,
            'summary' => $summary,
            'reserved_quantities' => $reservedQuantities,
            'serials_in_cart' => $serialsInCart,
        ];
    }

    public function normalizeCartItems(array $cart): array
    {
        $items = [];
        foreach ($cart as $entry) {
            if (! is_array($entry) || ! isset($entry['type'], $entry['material_id'])) {
                continue;
            }

            if ($entry['type'] === 'quantity') {
                $quantity = (int) ($entry['quantity'] ?? 0);

                if ($quantity < 1) {
                    continue;
                }

                $items[] = [
                    'type' => 'quantity',
                    'material_id' => (int) $entry['material_id'],
                    'quantity' => $quantity,
                ];
                continue;
            }

            if ($entry['type'] === 'serial' && isset($entry['serial_id'])) {
                $items[] = [
                    'type' => 'serial',
                    'material_id' => (int) $entry['material_id'],
                    'serial_id' => (int) $entry['serial_id'],
                ];
            }
        }

        return $items;
    }
}
