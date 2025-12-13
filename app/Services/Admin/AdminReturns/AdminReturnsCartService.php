<?php

namespace App\Services\Admin\AdminReturns;

use App\Models\Material;
use App\Models\StockLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AdminReturnsCartService
{   // clave de sesion para el carrito de devoluciones
    private const CART_SESSION_KEY = 'admin_return_cart';
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
    public function clearCart(Request $request): void
    {
        $request->session()->forget(self::CART_SESSION_KEY);
    }
    // preparar el carrito para su uso
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
        // validar cada entrada del carrito
        foreach ($cart as $key => $entry) {
            if (! is_array($entry) || ! isset($entry['type'], $entry['material_id'])) {
                unset($cart[$key]);
                $dirty = true;
                continue;
            }
            // si hay ubicacion de tecnico, validar que coincida
            if ($technicianLocation && (int) ($entry['technician_id'] ?? 0) !== $technicianLocation->ref_id) {
                unset($cart[$key]);
                $dirty = true;
                continue;
            }
            // obtener material
            $materialId = (int) $entry['material_id'];
            $material = Material::find($materialId);
            // validar existencia del material
            if (! $material) {
                unset($cart[$key]);
                $dirty = true;
                continue;
            }
            // procesar segun tipo de entrada
            if ($entry['type'] === 'quantity') {
                $quantity = (int) ($entry['quantity'] ?? 0);
                // validar cantidad positiva
                if ($quantity < 1) {
                    unset($cart[$key]);
                    $dirty = true;
                    continue;
                }
                
                $reservedKey = 'quantity-' . $materialId; // clave para cantidades reservadas
                $reservedQuantities[$reservedKey] = $quantity;// registrar cantidad reservada
                // agregar item al resultado
                $items[] = [
                    'key' => $key,
                    'type' => 'quantity',
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
                $serial = $serials->firstWhere('id', $serialId);
                // validar existencia del serial
                if (! $serial || (int) $serial->material_id !== $materialId) {
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
                    'material' => $material,
                    'serial' => $serial,
                ];
                // actualizar resumen
                $summary['total_items']++;
                $summary['total_units']++;
                continue;
            }
            // si no se pudo procesar, eliminar del carrito
            unset($cart[$key]);
            $dirty = true;
        }
        // si el carrito fue modificado, guardarlo
        if ($dirty) {
            $this->saveCart($request, $cart);
        }
        // retornar datos preparados
        return [
            'items' => $items,
            'summary' => $summary,
            'reserved_quantities' => $reservedQuantities,
            'serials_in_cart' => $serialsInCart,
        ];
    }
    // normalizar los items del carrito
    public function normalizeCartItems(array $cart): array
    {   // preparar items normalizados
        $items = [];
        foreach ($cart as $entry) {
            if (! is_array($entry) || ! isset($entry['type'], $entry['material_id'])) {
                continue;
            }
            // procesar segun tipo
            if ($entry['type'] === 'quantity') {
                $quantity = (int) ($entry['quantity'] ?? 0);
                // validar cantidad positiva
                if ($quantity < 1) {
                    continue;
                }
                // agregar item normalizado
                $items[] = [
                    'type' => 'quantity',
                    'material_id' => (int) $entry['material_id'],
                    'quantity' => $quantity,
                ];
                continue;
            }
            // procesar item de tipo serial
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
