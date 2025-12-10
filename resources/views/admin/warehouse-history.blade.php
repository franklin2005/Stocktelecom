@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-arrow-repeat me-2"></i>Histórico de movimientos del almacén</h1>
            <p class="st-muted mb-0">
                Registros de ajustes, transferencias y bajas aplicadas al almacén{{ $warehouse ? ' '.$warehouse->name : '' }}.
            </p>
        </div>
    </div>

    <form method="GET" class="st-card p-3 mb-4">
        <h5 class="mb-3">Filtrar resultados</h5>
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Desde</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Hasta</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-st"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                <a href="{{ route('admin.warehouse-movements') }}" class="btn btn-danger-st"><i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar</a>
            </div>
        </div>
    </form>

    <div class="st-card p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Material</th>
                        <th>Serie</th>
                        <th>Desde</th>
                        <th>Hacia</th>
                        <th>Cantidad</th>
                        <th>Referencia</th>
                        <th>Enviado por</th>
                        <th>Registrado por</th>
                        <th class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        @php
                            $isTransfer = $movement->record_type === 'transfer';
                            $movementType = $isTransfer ? 'transfer_out' : $movement->movement_type;
                            $movementTypeLabels = [
                                'transfer_in'       => 'Transferencia (entrada)',
                                'transfer_out'      => 'Transferencia (salida)',
                                'transfer'          => 'Transferencia',
                                'adjustment'        => 'Ajuste',
                                'manual_adjustment' => 'Ajuste manual',
                                'deletion'          => 'Baja',
                                'removal'           => 'Baja',
                                'write_off'         => 'Baja',
                            ];
                            $typeLabel = $movementTypeLabels[$movementType] ?? ucfirst(str_replace('_', ' ', $movementType));

                            $badgeClass = match($movementType) {
                                'transfer', 'transfer_in', 'transfer_out' => 'badge-accent',
                                'adjustment', 'manual_adjustment'         => 'badge-soft',
                                'deletion', 'removal', 'write_off'        => 'badge-danger-soft',
                                default                                    => 'badge-soft',
                            };

                            if ($isTransfer) {
                                $refType = 'transfer';
                                $refLabel = 'Transferencia';
                                $senderName = $movement->initiator?->name;
                                $performedAt = $movement->performed_at ?? $movement->transfer?->created_at;
                                $fromLocation = $movement->fromLocation?->name ?? $movement->transfer?->fromLocation?->name;
                                $toLocation = $movement->toLocation?->name ?? $movement->transfer?->toLocation?->name;
                                $quantity = $movement->quantity;
                                $materialText = 'Varios materiales';
                                $serialText = '—';
                                $registeredBy = $movement->performer?->name ?? 'Sistema';
                            } else {
                                $refType = $movement->reference_type;
                                $refLabel = $refType ? ucfirst(str_replace('_', ' ', $refType)) : null;
                                $senderName = $refType === 'transfer' ? $movement->transfer?->initiator?->name : null;
                                $performedAt = $movement->performed_at ?? $movement->created_at;
                                $fromLocation = $movement->fromLocation->name ?? '—';
                                $toLocation = $movement->toLocation->name ?? '—';
                                $quantity = $movement->quantity;
                                $materialText = ucfirst($movement->material->type ?? 'N/D') . ($movement->material?->model ? ' - '.$movement->material->model : '');
                                $serialText = $movement->serial?->serial_number ?? '—';
                                $registeredBy = $movement->performer->name ?? 'Sistema';
                            }
                        @endphp
                        <tr>
                            <td>{{ $performedAt?->format('d/m/Y H:i') }}</td>
                            <td>
                                <span class="badge {{ $badgeClass }} text-uppercase">
                                    {{ $typeLabel }}
                                </span>
                            </td>
                            <td>
                                {{ $materialText }}
                            </td>
                            <td>{{ $serialText }}</td>
                            <td>{{ $fromLocation }}</td>
                            <td>{{ $toLocation }}</td>
                            <td>{{ $quantity }}</td>
                            <td>
                                @if ($isTransfer)
                                    <span class="badge badge-soft">
                                        Transferencia #{{ $movement->transfer_id }}
                                    </span>
                                @elseif ($refType && $movement->reference_id)
                                    <span class="badge badge-soft">
                                        {{ $refLabel }} #{{ $movement->reference_id }}
                                    </span>
                                @else
                                    <span class="st-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $senderName ?? '—' }}</td>
                            <td>{{ $registeredBy }}</td>
                            <td class="text-end">
                                @if ($isTransfer && $movement->transfer_id)
                                    <a href="{{ route('admin.warehouse-movements.transfer.show', $movement->transfer_id) }}" class="btn btn-sm btn-soft-st">
                                        <i class="bi bi-eye me-1"></i>Ver detalle
                                    </a>
                                @else
                                    <span class="st-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center st-muted py-4">
                                Aún no hay movimientos registrados para el almacén.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($movements->hasPages())
            <div class="mt-3">
                {{ $movements->links() }}
            </div>
        @endif
    </div>
@endsection
