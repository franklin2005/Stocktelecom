@extends('layouts.app')

@php
    $roleLabels = [
        'technician' => 'Tecnico',
        'logistics' => 'Logistica',
        'admin' => 'Administrador',
        'super_admin' => 'Super Administrador',
    ];

    $userRoleLabel = $roleLabels[$viewedUser->role] ?? ucfirst($viewedUser->role);
    $locationName = $location?->name;
@endphp

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="mb-1">Historial de movimientos</h2>
            <p class="text-muted mb-0">
                {{ $viewedUser->name }}  {{ $userRoleLabel }}  {{ $viewedUser->email }}
                @if ($locationName)
                    <br><small class="text-muted">Ubicacion: {{ $locationName }}</small>
                @endif
            </p>
        </div>
        <a href="{{ route('admin.personnel', ['tab' => $backTab]) }}" class="btn btn-outline-secondary">
            Volver a personal
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($movements->isEmpty())
                <p class="text-muted mb-0">
                    No se encontraron movimientos asociados a este usuario.
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
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
                                <tr>
                                    <td>{{ $movement->performed_at?->format('d/m/Y H:i') ?? $movement->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <span class="badge bg-secondary text-uppercase">
                                            {{ str_replace('_', ' ', $movement->movement_type) }}
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
                                            {{ $movement->reference_type }} #{{ $movement->reference_id }}
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

