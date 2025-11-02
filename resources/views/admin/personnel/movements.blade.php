@extends('layouts.app')

@php
    $roleLabels = [
        'technician'  => 'Técnico',
        'logistics'   => 'Logística',
        'admin'       => 'Administrador',
        'super_admin' => 'Super Administrador',
    ];

    $userRoleLabel = $roleLabels[$viewedUser->role] ?? ucfirst($viewedUser->role);
    $locationName  = $location?->name;

    // Mapeo de estilos de badge por tipo de movimiento (ajústalo si tienes más tipos)
    $movementBadge = [
        'transfer_in'   => 'badge-accent',         // verde agua suave
        'transfer_out'  => 'badge-warning-soft',   // amarillo suave
        'assign'        => 'badge-info-soft',      // azul claro suave
        'unassign'      => 'badge-danger-soft',    // rojo suave
        'work_order'    => 'badge-soft',           // azul suave genérico
        'adjustment'    => 'badge-soft',
    ];
@endphp

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1">Historial de movimientos</h1>
            <p class="st-muted mb-0">
                {{ $viewedUser->name }} — {{ $userRoleLabel }} — {{ $viewedUser->email }}
                @if ($locationName)
                    <br><small class="st-muted">Ubicación: {{ $locationName }}</small>
                @endif
            </p>
        </div>
        <a href="{{ route('admin.personnel', ['tab' => $backTab]) }}" class="btn btn-outline-secondary">
            Volver a personal
        </a>
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
                <button type="submit" class="btn btn-st">Filtrar</button>
                <a href="{{ route('admin.personnel.movements', $viewedUser) }}" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card st-card shadow-sm">
        <div class="card-body">
            @if ($movements->isEmpty())
                <p class="st-muted mb-0">No se encontraron movimientos asociados a este usuario.</p>
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
                                <th>Registrado por</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($movements as $movement)
                                @php
                                    $typeKey = $movement->movement_type ?? 'otros';
                                    $badgeClass = $movementBadge[$typeKey] ?? 'badge-soft';
                                @endphp
                                <tr>
                                    <td>{{ $movement->performed_at?->format('d/m/Y H:i') ?? $movement->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <span class="badge {{ $badgeClass }} text-uppercase">
                                            {{ str_replace('_', ' ', $typeKey) }}
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
                                    <td>{{ $movement->performer->name ?? 'Sistema' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($movements->hasPages())
                    <div class="mt-3">
                        {{ $movements->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
@endsection
