<?php

namespace App\Services\Admin\AdminTransfer;

use App\Models\MaterialSerial;
use App\Models\StockLocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminTransferSerialService
{
    /**
     * @param  array<int, int>  $serialIds
     */
    public function reserveSerials(StockLocation $warehouse, array $serialIds, int $userId): Collection
    {
        $uniqueIds = array_values(array_unique($serialIds));

        if (empty($uniqueIds)) {
            return collect();
        }

        $now = now();

        return DB::transaction(function () use ($warehouse, $uniqueIds, $userId, $now) {
            $serials = MaterialSerial::query()
                ->whereIn('id', $uniqueIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($serials->count() !== count($uniqueIds)) {
                throw new RuntimeException('Alguno de los numeros de serie seleccionados ya no esta disponible.');
            }

            foreach ($serials as $serial) {
                if ($serial->current_location_id !== $warehouse->id) {
                    throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya no esta disponible.');
                }

                if ($serial->reserved_by_user_id && (int) $serial->reserved_by_user_id !== $userId) {
                    throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya esta reservado por otro usuario.');
                }

                $validStatus = $serial->status === 'available'
                    || ($serial->status === 'reserved' && (int) $serial->reserved_by_user_id === $userId);

                if (! $validStatus) {
                    throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya no esta disponible.');
                }
            }

            foreach ($serials as $serial) {
                if ($serial->status === 'reserved' && (int) $serial->reserved_by_user_id === $userId) {
                    continue;
                }

                $serial->update([
                    'status' => 'reserved',
                    'reserved_by_user_id' => $userId,
                    'reserved_at' => $now,
                ]);

                $serial->status = 'reserved';
                $serial->reserved_by_user_id = $userId;
                $serial->reserved_at = $now;
            }

            return $serials;
        });
    }

    /**
     * @param  array<int, int>  $serialIds
     */
    public function releaseSerialReservations(array $serialIds, int $userId): void
    {
        $uniqueIds = array_values(array_unique($serialIds));

        if (empty($uniqueIds)) {
            return;
        }

        DB::transaction(function () use ($uniqueIds, $userId) {
            $serials = MaterialSerial::query()
                ->whereIn('id', $uniqueIds)
                ->lockForUpdate()
                ->get();

            foreach ($serials as $serial) {
                if ((int) $serial->reserved_by_user_id !== $userId) {
                    continue;
                }

                $serial->update([
                    'status' => $serial->status === 'reserved' ? 'available' : $serial->status,
                    'reserved_by_user_id' => null,
                    'reserved_at' => null,
                ]);
            }
        });
    }
}
