<?php

namespace App\Services\Admin\AdminTransfer;

use App\Models\Material;
use App\Models\StockLocation;
use Illuminate\Http\Request;

class AdminTransferCartService
{
    private const CART_SESSION_KEY = 'admin_transfer_cart';

    public function __construct(private readonly AdminTransferSerialService $serialService)
    {
    }

    public function getCart(Request $request): array
    {
        return $request->session()->get(self::CART_SESSION_KEY, []);
    }

    public function saveCart(Request $request, array $cart): void
    {
        $request->session()->put(self::CART_SESSION_KEY, $cart);
    }

    public function clearCartSession(Request $request): void
    {
        $request->session()->forget(self::CART_SESSION_KEY);
    }

    public function prepareCart(Request $request, StockLocation $warehouse, $inventory, $serials, int $userId): array
    {
        $cart = $this->getCart($request);

        if (empty($cart)) {
            return [
                'items' => [],
                'summary' => ['total_items' => 0, 'total_units' => 0],
                'reserved_quantities' => [],
                'serials_in_cart' => [],
                'warnings' => [],
            ];
        }

        $inventoryLookup = collect($inventory)->keyBy('material_id');
        $serialLookup = collect($serials)->keyBy('id');

        $materialIds = [];
        $serialIds = [];

        foreach ($cart as $entry) {
            if (! is_array($entry) || ! isset($entry['type'], $entry['material_id'])) {
                continue;
            }

            $materialIds[] = (int) $entry['material_id'];

            if ($entry['type'] === 'serial' && isset($entry['serial_id'])) {
                $serialIds[] = (int) $entry['serial_id'];
            }
        }

        $materials = Material::query()->whereIn('id', array_unique($materialIds))->get()->keyBy('id');

        $items = [];
        $summary = ['total_items' => 0, 'total_units' => 0];
        $reservedQuantities = [];
        $serialsInCart = [];
        $dirty = false;
        $warnings = [];
        $serialsToRelease = [];

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
                $available = $inventoryLookup->get($materialId)?->quantity ?? 0;

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
                    continue;
                }

                $reservedQuantities['quantity-' . $materialId] = $quantity;
                $items[] = [
                    'key' => $key,
                    'type' => 'quantity',
                    'material_id' => $materialId,
                    'material' => $material,
                    'quantity' => $quantity,
                ];

                $summary['total_items']++;
                $summary['total_units'] += $quantity;
                continue;
            }

            if ($entry['type'] === 'serial' && isset($entry['serial_id'])) {
                $serialId = (int) $entry['serial_id'];
                $serial = $serialLookup->get($serialId);

                if (! $serial) {
                    unset($cart[$key]);
                    $dirty = true;
                    $warnings[] = 'Un numero de serie seleccionado ya no existe y se retiro de la lista.';
                    continue;
                }

                if ($serial->current_location_id !== $warehouse->id) {
                    if ((int) $serial->reserved_by_user_id === $userId) {
                        $serialsToRelease[] = $serial->id;
                    }

                    unset($cart[$key]);
                    $dirty = true;
                    $warnings[] = 'El numero de serie ' . $serial->serial_number . ' ya no esta en el almacen y se retiro de la lista.';
                    continue;
                }

                if ((int) $serial->reserved_by_user_id !== $userId || $serial->status !== 'reserved') {
                    if ((int) $serial->reserved_by_user_id === $userId) {
                        $serialsToRelease[] = $serial->id;
                    }

                    if ($serial->reserved_by_user_id && (int) $serial->reserved_by_user_id !== $userId) {
                        $warnings[] = 'El numero de serie ' . $serial->serial_number . ' fue reservado por otro usuario y se retiro de la lista.';
                    } else {
                        $warnings[] = 'El numero de serie ' . $serial->serial_number . ' ya no esta disponible y se retiro de la lista.';
                    }

                    unset($cart[$key]);
                    $dirty = true;
                    continue;
                }

                $serialsInCart[] = $serialId;
                $items[] = [
                    'key' => $key,
                    'type' => 'serial',
                    'material_id' => $materialId,
                    'serial_id' => $serialId,
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

        if (! empty($serialsToRelease)) {
            $this->serialService->releaseSerialReservations(array_values(array_unique($serialsToRelease)), $userId);
        }

        if ($dirty) {
            $this->saveCart($request, $cart);
        }

        return [
            'items' => array_values($items),
            'summary' => $summary,
            'reserved_quantities' => $reservedQuantities,
            'serials_in_cart' => $serialsInCart,
            'warnings' => $warnings,
        ];
    }
}
