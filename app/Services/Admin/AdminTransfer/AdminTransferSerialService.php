<?php

namespace App\Services\Admin\AdminTransfer;

use App\Models\MaterialSerial;
use App\Models\StockLocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminTransferSerialService
{// reservar seriales para transferencia
    public function reserveSerials(StockLocation $warehouse, array $serialIds, int $userId): Collection
    {// eliminar IDs duplicados
        $uniqueIds = array_values(array_unique($serialIds));
        // si no hay IDs, retornar coleccion vacia
        if (empty($uniqueIds)) {
            return collect();
        }
        // obtener fecha y hora actual
        $now = now();
        // ejecutar en transaccion
        return DB::transaction(function () use ($warehouse, $uniqueIds, $userId, $now) {
            $serials = MaterialSerial::query()
                ->whereIn('id', $uniqueIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            // validar que se recuperaron todos los seriales solicitados
            if ($serials->count() !== count($uniqueIds)) {
                throw new RuntimeException('Alguno de los numeros de serie seleccionados ya no esta disponible.');
            }
            // validar disponibilidad de cada serial
            foreach ($serials as $serial) {
                if ($serial->current_location_id !== $warehouse->id) {
                    throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya no esta disponible.');
                }
                // verificar si el serial ya esta reservado por otro usuario
                if ($serial->reserved_by_user_id && (int) $serial->reserved_by_user_id !== $userId) {
                    throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya esta reservado por otro usuario.');
                }
                // validar estado del serial disponible o reservado por el mismo usuario
                $validStatus = $serial->status === 'available'
                    || ($serial->status === 'reserved' && (int) $serial->reserved_by_user_id === $userId);
                // si el estado no es valido, lanzar excepcion
                if (! $validStatus) {
                    throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya no esta disponible.');
                }
            }   
            // reservar cada serial
            foreach ($serials as $serial) {
                if ($serial->status === 'reserved' && (int) $serial->reserved_by_user_id === $userId) {
                    continue;
                }
                // actualizar registro del serial
                $serial->update([
                    'status' => 'reserved',
                    'reserved_by_user_id' => $userId,
                    'reserved_at' => $now,
                ]);
                // actualizar propiedades del objeto en memoria
                $serial->status = 'reserved';
                $serial->reserved_by_user_id = $userId;
                $serial->reserved_at = $now;
            }

            return $serials;
        });
    }

   // liberar reservas de seriales
    public function releaseSerialReservations(array $serialIds, int $userId): void
    {   // eliminar IDs duplicados
        $uniqueIds = array_values(array_unique($serialIds));
        // si no hay IDs, salir
        if (empty($uniqueIds)) {
            return;
        }
        // ejecutar en transaccion
        DB::transaction(function () use ($uniqueIds, $userId) {
            $serials = MaterialSerial::query()
                ->whereIn('id', $uniqueIds)
                ->lockForUpdate()
                ->get();
            // liberar cada serial reservado por el usuario
            foreach ($serials as $serial) {
                if ((int) $serial->reserved_by_user_id !== $userId) {
                    continue;
                }
                // actualizar registro del serial
                $serial->update([
                    'status' => $serial->status === 'reserved' ? 'available' : $serial->status,// cambiar a disponible si estaba reservado
                    'reserved_by_user_id' => null,// liberar reserva
                    'reserved_at' => null,// liberar fecha de reserva
                ]);
            }
        });
    }
}
