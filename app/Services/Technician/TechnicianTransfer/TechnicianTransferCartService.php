<?php

namespace App\Services\Technician\TechnicianTransfer;

use App\Models\Inventory;
use App\Models\Material;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use Illuminate\Http\Request;

class TechnicianTransferCartService
{   //  clave de sesion para el carrito de transferencias
    private const CART_SESSION_KEY = 'technician_transfer_cart';

    public function getCart(Request $request): array
    {   // retornar el carrito de la session o array vacio
        return $request->session()->get(self::CART_SESSION_KEY, []);
    }

    public function saveCart(Request $request, array $cart): void
    {   // guardar el carrito en la session
        $request->session()->put(self::CART_SESSION_KEY, $cart);
    }

    public function clearCart(Request $request): void
    {   // limpiar el carrito de la session
        $request->session()->forget(self::CART_SESSION_KEY);
    }

    // preparar el carrito para su uso
    public function prepareCart(Request $request, StockLocation $location): array
    {
        $cart = $this->getCart($request);
        // si el carrito esta vacio, retornar estructura vacia
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
        //inicializar arrays para IDs
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
            if (($entry['type'] ?? '') === 'serial' && isset($entry['serial_id'])) {
                $serialIds[] = (int) $entry['serial_id'];
            }
        }
        // eliminar IDs duplicados
        $materialIds = array_unique($materialIds);
        // obtener materiales, inventarios y seriales desde la base de datos
        $materials = Material::query()
            ->whereIn('id', $materialIds)
            ->get()
            ->keyBy('id');
        // obtener inventarios para la ubicacion dada
        $inventories = Inventory::query()
            ->where('location_id', $location->id)
            ->whereIn('material_id', $materialIds)
            ->get()
            ->keyBy('material_id');
        // obtener seriales si hay IDs
        $serialModels = empty($serialIds)
            ? collect()
            : MaterialSerial::query()
                ->with('material')
                ->whereIn('id', $serialIds)
                ->get()
                ->keyBy('id');
        // inicializar estructuras de resultado
        $items = [];    // items procesados
        $summary = [    // resumen del carrito
            'total_items' => 0,
            'total_units' => 0,
        ];
        $reservedQuantities = [];   // cantidades reservadas por material
        $serialsInCart = [];    // IDs de seriales en el carrito
        $dirty = false; // indica si el carrito fue modificado
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
                $available = $inventories->get($materialId)?->quantity ?? 0;
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
                    $dirty = true;
                    continue;
                }
                // registrar cantidad reservada
                $reservedQuantities['quantity-' . $materialId] = $quantity;
                // agregar item al resultado
                $items[] = [
                    'key' => $key,
                    'type' => 'quantity',
                    'material_id' => $material->id,
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
                $serial = $serialModels->get($serialId);
                // si el serial no existe o no esta en la ubicacion o no esta asignado, eliminar del carrito
                if (! $serial || $serial->current_location_id !== $location->id || $serial->status !== 'assigned') {
                    unset($cart[$key]);
                    $dirty = true;
                    continue;
                }
                // registrar serial en el carrito
                $serialsInCart[] = $serialId;
                // agregar item al resultado
                $items[] = [
                    'key' => $key,
                    'type' => 'serial',
                    'material_id' => $material->id,
                    'material' => $material,
                    'serial_id' => $serial->id,
                    'serial' => $serial,
                ];
                // actualizar resumen
                $summary['total_items']++;
                $summary['total_units']++;
                continue;
            }
            // tipo no reconocido, eliminar del carrito
            unset($cart[$key]);
            $dirty = true;
        }
        // si el carrito fue modificado, guardarlo nuevamente en la session
        if ($dirty) {
            $this->saveCart($request, $cart);
        }
        // retornar estructura del carrito preparado
        return [
            'items' => array_values($items),
            'summary' => $summary,
            'reserved_quantities' => $reservedQuantities,
            'serials_in_cart' => $serialsInCart,
        ];
    }
}
