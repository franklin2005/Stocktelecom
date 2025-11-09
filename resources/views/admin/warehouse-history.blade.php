@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1">Histórico de movimientos del almacén</h1>
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
                <button type="submit" class="btn btn-st">Filtrar</button>
                <a href="{{ route('admin.warehouse-movements') }}" class="btn btn-outline-secondary">Limpiar</a>
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
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        @php
                            // ------- Traducciones de movement_type (tipo de movimiento) -------
                            $movementType = $movement->movement_type; // p.ej. transfer_in, transfer_out, adjustment, deletion...
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

                            // Clases para badge según el tipo de movimiento
                            $badgeClass = match($movementType) {
                                'transfer', 'transfer_in', 'transfer_out' => 'badge-accent',
                                'adjustment', 'manual_adjustment'         => 'badge-soft',
                                'deletion', 'removal', 'write_off'        => 'badge-danger-soft',
                                default                                    => 'badge-soft',
                            };

                            // ------- Traducciones de reference_type (tipo de referencia) -------
                            $refType = $movement->reference_type; // valores reales: transfer, work_order, manual_adjustment
                            $refTypeLabels = [
                                'transfer'          => 'Transferencia',
                                'work_order'        => 'Orden de trabajo',
                                'manual_adjustment' => 'Ajuste manual',
                            ];
                            $refLabel = $refType ? ($refTypeLabels[$refType] ?? ucfirst(str_replace('_', ' ', $refType))) : null;

                            // Quien envía (solo aplica a transferencias)
                            $senderName = null;
                            if ($refType === 'transfer') {
                                $senderName = $movement->transfer?->initiator?->name;
                            }
                        @endphp
                        <tr>
                            <td>{{ $movement->performed_at?->format('d/m/Y H:i') ?? $movement->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <span class="badge {{ $badgeClass }} text-uppercase">
                                    {{ $typeLabel }}
                                </span>
                            </td>
                            <td>
                                {{ ucfirst($movement->material->type ?? 'N/D') }}
                                @if ($movement->material?->model)
                                    - {{ $movement->material->model }}
                                @endif
                            </td>
                            <td>{{ $movement->serial?->serial_number ?? '—' }}</td>
                            <td>{{ $movement->fromLocation->name ?? '—' }}</td>
                            <td>{{ $movement->toLocation->name ?? '—' }}</td>
                            <td>{{ $movement->quantity }}</td>
                            <td>
                                @if ($refType && $movement->reference_id)
                                    <span class="badge badge-soft">
                                        {{ $refLabel }} #{{ $movement->reference_id }}
                                    </span>
                                @else
                                    <span class="st-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $senderName ?? '—' }}</td>
                            <td>{{ $movement->performer->name ?? 'Sistema' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center st-muted py-4">
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
