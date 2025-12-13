<?php

namespace App\Services\Admin\AdminTransfer;

use App\Models\Material;
use App\Models\StockLocation;
use Illuminate\Http\Request;

class AdminTransferCartService
{   // clave de session para el carrito
    private const CART_SESSION_KEY = 'admin_transfer_cart';
    // servicio de seriales
    public function __construct(private readonly AdminTransferSerialService $serialService)
    {
    }
    // obtener el carrito de la session
    public function getCart(Request $request): array
    {   // retornar carrito o array vacio
        return $request->session()->get(self::CART_SESSION_KEY, []);
    }
    // guardar el carrito en la session
    public function saveCart(Request $request, array $cart): void
    {   
        $request->session()->put(self::CART_SESSION_KEY, $cart);
    }
    // limpiar el carrito de la session
    public function clearCartSession(Request $request): void
    {
        $request->session()->forget(self::CART_SESSION_KEY);
    }
    // preparar el carrito para su uso
    public function prepareCart(Request $request, StockLocation $warehouse, $inventory, $serials, int $userId): array
    {   
        $cart = $this->getCart($request);
        // si el carrito esta vacio, retornar estructura vacia
        if (empty($cart)) {
            return [
                'items' => [],
                'summary' => ['total_items' => 0, 'total_units' => 0],
                'reserved_quantities' => [],
                'serials_in_cart' => [],
                'warnings' => [],
            ];
        }
        // crear busquedas rapidas de inventario y seriales
        $inventoryLookup = collect($inventory)->keyBy('material_id');
        $serialLookup = collect($serials)->keyBy('id');
        // inicializar arrays para IDs
        $materialIds = [];
        $serialIds = [];
        // recopilar IDs de materiales y seriales en el carrito
        foreach ($cart as $entry) {
            if (! is_array($entry) || ! isset($entry['type'], $entry['material_id'])) {
                continue;
            }
            // agregar ID de material
            $materialIds[] = (int) $entry['material_id'];
            // agregar ID de serial si aplica
            if ($entry['type'] === 'serial' && isset($entry['serial_id'])) {
                $serialIds[] = (int) $entry['serial_id'];
            }
        }
        // obtener materiales desde la base de datos
        $materials = Material::query()->whereIn('id', array_unique($materialIds))->get()->keyBy('id');
        // inicializar variables de resultado
        $items = [];// items procesados
        $summary = ['total_items' => 0, 'total_units' => 0];// resumen del carrito
        $reservedQuantities = []; // cantidades reservadas por material
        $serialsInCart = []; // IDs de seriales en el carrito
        $dirty = false; // indica si el carrito fue modificado
        $warnings = []; // advertencias para el usuario
        $serialsToRelease = []; // seriales a liberar si ya no estan en el carrito
        // procesar cada entrada del carrito
        foreach ($cart as $key => $entry) {
            if (! is_array($entry) || ! isset($entry['type'], $entry['material_id'])) {
                unset($cart[$key]);
                $dirty = true;
                continue;
            }
            // obtener material
            $materialId = (int) $entry['material_id'];
            $material = $materials->get($materialId);
            // si el material no existe, eliminar del carrito
            if (! $material) {
                unset($cart[$key]);
                $dirty = true;
                continue;
            }
            // procesar segun el tipo de entrada
            if ($entry['type'] === 'quantity') {
                $quantity = (int) ($entry['quantity'] ?? 0);
                $available = $inventoryLookup->get($materialId)?->quantity ?? 0;
                // si no hay stock disponible, eliminar del carrito
                if ($quantity < 1 || $available < 1) {
                    unset($cart[$key]);
                    $dirty = true;
                    continue;
                }
                // ajustar cantidad si excede el stock disponible
                if ($quantity > $available) {
                    $quantity = $available;
                    $cart[$key]['quantity'] = $quantity;
                    $dirty = true;
                }
                // si la cantidad es menor a 1, eliminar del carrito
                if ($quantity < 1) {
                    unset($cart[$key]);
                    continue;
                }
                // registrar cantidad reservada
                $reservedQuantities['quantity-' . $materialId] = $quantity;
                $items[] = [
                    'key' => $key,
                    'type' => 'quantity',
                    'material_id' => $materialId,
                    'material' => $material,
                    'quantity' => $quantity,
                ];
                // actualizar resumen
                $summary['total_items']++;
                $summary['total_units'] += $quantity;
                continue;
            }
            // procesar entrada de tipo serial
            if ($entry['type'] === 'serial' && isset($entry['serial_id'])) {
                $serialId = (int) $entry['serial_id'];
                $serial = $serialLookup->get($serialId);
                // si el serial no existe, eliminar del carrito
                if (! $serial) {
                    unset($cart[$key]);
                    $dirty = true;
                    $warnings[] = 'Un numero de serie seleccionado ya no existe y se retiro de la lista.';
                    continue;
                }
                // verificar que el serial este en el almacen
                if ($serial->current_location_id !== $warehouse->id) {
                    if ((int) $serial->reserved_by_user_id === $userId) {
                        $serialsToRelease[] = $serial->id;
                    }
                    // eliminar del carrito y advertir
                    unset($cart[$key]);
                    $dirty = true;
                    $warnings[] = 'El numero de serie ' . $serial->serial_number . ' ya no esta en el almacen y se retiro de la lista.';
                    continue;
                }
                // verificar que el serial este reservado por el usuario
                if ((int) $serial->reserved_by_user_id !== $userId || $serial->status !== 'reserved') {
                    if ((int) $serial->reserved_by_user_id === $userId) {
                        $serialsToRelease[] = $serial->id;
                    }
                    // eliminar del carrito y advertir
                    if ($serial->reserved_by_user_id && (int) $serial->reserved_by_user_id !== $userId) {
                        $warnings[] = 'El numero de serie ' . $serial->serial_number . ' fue reservado por otro usuario y se retiro de la lista.';
                    } else {
                        $warnings[] = 'El numero de serie ' . $serial->serial_number . ' ya no esta disponible y se retiro de la lista.';
                    }
                    // eliminar del carrito
                    unset($cart[$key]);
                    $dirty = true;
                    continue;
                }
                // registrar cantidad reservada
                $serialsInCart[] = $serialId;
                $items[] = [    // agregar item al resultado
                    'key' => $key,
                    'type' => 'serial',
                    'material_id' => $materialId,
                    'serial_id' => $serialId,
                    'material' => $material,
                    'serial' => $serial,
                ];
                // actualizar resumen
                $summary['total_items']++;
                $summary['total_units']++;
                continue;
            }
            // si el tipo es invalido, eliminar del carrito
            unset($cart[$key]);
            $dirty = true;
        }
        // liberar seriales que ya no estan en el carrito
        if (! empty($serialsToRelease)) {
            $this->serialService->releaseSerialReservations(array_values(array_unique($serialsToRelease)), $userId);
        }
        // si el carrito fue modificado, guardarlo
        if ($dirty) {
            $this->saveCart($request, $cart);
        }
        // retornar resultado
        return [
            'items' => array_values($items),
            'summary' => $summary,
            'reserved_quantities' => $reservedQuantities,
            'serials_in_cart' => $serialsInCart,
            'warnings' => $warnings,
        ];
    }
}
