@extends('layouts.app')

@php
    $roleLabels = [
        'technician'  => 'Técnico',
        'logistics'   => 'Logística',
        'admin'       => 'Administrador',
        'super_admin' => 'Super Administrador',
    ];

    $userRoleLabel = $roleLabels[$viewedUser->role] ?? ucfirst($viewedUser->role);
    $locationName  = $location->name ?? null;

    $actionLabels = [
        'created' => 'Creación',
        'updated' => 'Actualización',
        'deleted' => 'Eliminación',
        'reset_password' => 'Restablecer contraseña',
    ];
    $actionClasses = [
        'created' => 'badge-success-soft',
        'updated' => 'badge-info-soft',
        'deleted' => 'badge-danger-soft',
        'reset_password' => 'badge-warning-soft',
    ];
@endphp

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-arrow-repeat me-2"></i>Historial de movimientos</h1>
            <p class="st-muted mb-0">
                {{ $viewedUser->name }} - {{ $userRoleLabel }} - {{ $viewedUser->email }}
                @if ($locationName)
                    <br><small class="st-muted">Ubicación: {{ $locationName }}</small>
                @endif
            </p>
        </div>
    </div>

    <form method="GET" class="card st-card shadow-sm mb-4">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-4">
                <label for="from" class="form-label">Desde</label>
                <input id="from" type="date" name="from" value="{{ request('from') }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label for="to" class="form-label">Hasta</label>
                <input id="to" type="date" name="to" value="{{ request('to') }}" class="form-control">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-st"><i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <a href="{{ route('admin.personnel.movements', $viewedUser) }}" class="btn btn-danger-st"><i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card st-card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Historial de acciones de usuario</h5>
            @if ($logs->isEmpty())
                <p class="st-muted mb-0">No se encontraron acciones registradas para este usuario.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Acción</th>
                                <th>Usuario afectado</th>
                                <th>Detalles</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                @php
                                    $actionKey = strtolower((string) $log->action);
                                    $actionText = $actionLabels[$actionKey] ?? ucfirst(str_replace('_', ' ', $actionKey));
                                    $actionClass = $actionClasses[$actionKey] ?? 'badge-soft';
                                @endphp
                                <tr>
                                    <td>{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                    <td><span class="badge {{ $actionClass }} text-uppercase">{{ $actionText }}</span></td>
                                    <td>{{ $log->target?->name ?? '—' }}</td>
                                    <td>{{ $log->details ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($logs->hasPages())
                    <div class="mt-3">
                        {{ $logs->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>

    @if ($stockMovements && $viewedUser->role !== 'admin')
        @php
            $movementBadge = [
                'transfer_in'   => 'badge-accent',
                'transfer_out'  => 'badge-warning-soft',
                'assign'        => 'badge-info-soft',
                'unassign'      => 'badge-danger-soft',
                'work_order'    => 'badge-soft',
                'adjustment'    => 'badge-soft',
                'manual_adjustment' => 'badge-soft',
            ];

            $movementLabels = [
                'transfer_in' => 'Transferencia (entrada)',
                'transfer_out' => 'Transferencia (salida)',
                'assign' => 'Asignación',
                'unassign' => 'Desasignación',
                'work_order' => 'Orden de trabajo',
                'adjustment' => 'Ajuste',
                'manual_adjustment' => 'Ajuste manual',
            ];
        @endphp
        <div class="card st-card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Movimientos de stock</h5>
                @if ($stockMovements->isEmpty())
                    <p class="st-muted mb-0">No se encontraron movimientos de stock para este usuario.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Material</th>
                                    <th>Serie</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Cantidad</th>
                                    <th>Referencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($stockMovements as $movement)
                                    @php
                                        $typeKey = $movement->movement_type ?? 'otros';
                                        $badgeClass = $movementBadge[$typeKey] ?? 'badge-soft';
                                    @endphp
                                    <tr>
                                        <td>{{ $movement->performed_at?->format('d/m/Y H:i') ?? $movement->created_at->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <span class="badge {{ $badgeClass }} text-uppercase">
                                                {{ $movementLabels[$typeKey] ?? ucfirst(str_replace('_', ' ', $typeKey)) }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ ucfirst($movement->material->type ?? 'N/D') }}
                                            @if ($movement->material?->model)
                                                - {{ $movement->material->model }}
                                            @endif
                                        </td>
                                        <td>{{ $movement->serial?->serial_number ?? '-' }}</td>
                                        <td>{{ $movement->fromLocation->name ?? '-' }}</td>
                                        <td>{{ $movement->toLocation->name ?? '-' }}</td>
                                        <td>{{ $movement->quantity }}</td>
                                        <td>
                                            @if ($movement->reference_type && $movement->reference_id)
                                                {{ ucfirst($movement->reference_type) }} #{{ $movement->reference_id }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($stockMovements->hasPages())
                        <div class="mt-3">
                            {{ $stockMovements->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif
@endsection
