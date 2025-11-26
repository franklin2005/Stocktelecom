<?php

namespace App\Services\Technician\TechnicianTransfer;

use App\Models\Inventory;
use App\Models\Material;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use Illuminate\Http\Request;

class TechnicianTransferCartService
{
    private const CART_SESSION_KEY = 'technician_transfer_cart';

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

    /**
     * @return array{items: array<int, array>, summary: array<string, int>, reserved_quantities: array<string, int>, serials_in_cart: array<int>}
     */
    public function prepareCart(Request $request, StockLocation $location): array
    {
        $cart = $this->getCart($request);

        if (empty($cart)) {
            return [
                'items' => [],
                'summary' => [
                    'total_items' => 0,
                    'total_units' => 0,
                ],
                'reserved_quantities' => [],
                'serials_in_cart' => [],
            ];
        }

        $materialIds = [];
        $serialIds = [];

        foreach ($cart as $entry) {
            if (! is_array($entry) || ! isset($entry['type'], $entry['material_id'])) {
                continue;
            }

            $materialIds[] = (int) $entry['material_id'];

            if (($entry['type'] ?? '') === 'serial' && isset($entry['serial_id'])) {
                $serialIds[] = (int) $entry['serial_id'];
            }
        }

        $materialIds = array_unique($materialIds);

        $materials = Material::query()
            ->whereIn('id', $materialIds)
            ->get()
            ->keyBy('id');

        $inventories = Inventory::query()
            ->where('location_id', $location->id)
            ->whereIn('material_id', $materialIds)
            ->get()
            ->keyBy('material_id');

        $serialModels = empty($serialIds)
            ? collect()
            : MaterialSerial::query()
                ->with('material')
                ->whereIn('id', $serialIds)
                ->get()
                ->keyBy('id');

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

            $materialId = (int) $entry['material_id'];
            $material = $materials->get($materialId);

            if (! $material) {
                unset($cart[$key]);
                $dirty = true;
                continue;
            }

            if ($entry['type'] === 'quantity') {
                $quantity = (int) ($entry['quantity'] ?? 0);
                $available = $inventories->get($materialId)?->quantity ?? 0;

                if ($quantity < 1 || $available < 1) {
                    unset($cart[$key]);
                    $dirty = true;
                    continue;
                }

                if ($quantity > $available) {
                    $quantity = $available;
                    $cart[$key]['quantity'] = $quantity;
                    $dirty = true;
                }

                if ($quantity < 1) {
                    unset($cart[$key]);
                    $dirty = true;
                    continue;
                }

                $reservedQuantities['quantity-' . $materialId] = $quantity;

                $items[] = [
                    'key' => $key,
                    'type' => 'quantity',
                    'material_id' => $material->id,
                    'material' => $material,
                    'quantity' => $quantity,
                ];

                $summary['total_items']++;
                $summary['total_units'] += $quantity;
                continue;
            }

            if ($entry['type'] === 'serial' && isset($entry['serial_id'])) {
                $serialId = (int) $entry['serial_id'];
                $serial = $serialModels->get($serialId);

                if (! $serial || $serial->current_location_id !== $location->id || $serial->status !== 'assigned') {
                    unset($cart[$key]);
                    $dirty = true;
                    continue;
                }

                $serialsInCart[] = $serialId;

                $items[] = [
                    'key' => $key,
                    'type' => 'serial',
                    'material_id' => $material->id,
                    'material' => $material,
                    'serial_id' => $serial->id,
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
            'items' => array_values($items),
            'summary' => $summary,
            'reserved_quantities' => $reservedQuantities,
            'serials_in_cart' => $serialsInCart,
        ];
    }
}
