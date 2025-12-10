@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h4 mb-1"><i class="bi bi-box-arrow-right me-2"></i>Detalle de transferencia</h1>
            <p class="st-muted mb-0">
                Transferencia #{{ $transfer->id }} | {{ $performedAt?->format('d/m/Y H:i') }}
            </p>
        </div>
        <a href="{{ route('admin.warehouse-movements') }}" class="btn btn-soft-st">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    <div class="st-card p-3 mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <strong>Origen:</strong>
                <div>{{ $transfer->fromLocation->name ?? 'Almacén' }}</div>
            </div>
            <div class="col-md-4">
                <strong>Destino:</strong>
                <div>{{ $transfer->toLocation->name ?? '—' }}</div>
            </div>
            <div class="col-md-4">
                <strong>Total unidades:</strong>
                <div>{{ $totalUnits }}</div>
            </div>
            <div class="col-md-4">
                <strong>Fecha:</strong>
                <div>{{ $performedAt?->format('d/m/Y H:i') ?? '—' }}</div>
            </div>
            <div class="col-md-4">
                <strong>Iniciada por:</strong>
                <div>{{ $transfer->initiator?->name ?? '—' }}</div>
            </div>
            <div class="col-md-4">
                <strong>Registrada por:</strong>
                <div>{{ $movements->first()?->performer?->name ?? 'Sistema' }}</div>
            </div>
            <div class="col-md-4">
                <strong>Estado:</strong>
                @php
                    $statusLabels = [
                        'pending' => 'Pendiente',
                        'accepted' => 'Aceptada',
                        'rejected' => 'Rechazada',
                    ];
                    $statusBadge = [
                        'pending' => 'badge-soft',
                        'accepted' => 'badge-accent',
                        'rejected' => 'badge-danger-soft',
                    ];
                    $statusKey = $transfer->status ?? 'pending';
                @endphp
                <div>
                    <span class="badge {{ $statusBadge[$statusKey] ?? 'badge-soft' }} text-uppercase">
                        {{ $statusLabels[$statusKey] ?? ucfirst($statusKey) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="st-card p-3">
        <h5 class="mb-3">Líneas de la transferencia</h5>
        @forelse ($movements as $movement)
            <div class="mb-3 pb-3 border-bottom">
                <div><strong>Material:</strong> {{ ucfirst($movement->material->type ?? 'N/D') }}@if ($movement->material?->model) - {{ $movement->material->model }}@endif</div>
                <div><strong>Serie:</strong> {{ $movement->serial?->serial_number ?? '—' }}</div>
                <div><strong>Cantidad:</strong> {{ $movement->quantity }}</div>
                <div><strong>Desde:</strong> {{ $movement->fromLocation->name ?? '—' }}</div>
                <div><strong>Hacia:</strong> {{ $movement->toLocation->name ?? '—' }}</div>
                <div><strong>Fecha:</strong> {{ $movement->performed_at?->format('d/m/Y H:i') ?? $movement->created_at->format('d/m/Y H:i') }}</div>
                <div><strong>Registrado por:</strong> {{ $movement->performer->name ?? 'Sistema' }}</div>
            </div>
        @empty
            <p class="st-muted mb-0">Sin movimientos para esta transferencia.</p>
        @endforelse
    </div>
@endsection
