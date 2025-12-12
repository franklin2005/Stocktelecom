@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h4 mb-1"><i class="bi bi-arrow-left-right me-2"></i>Detalle de devolución</h1>
            <p class="st-muted mb-0">
                Orden {{ $returnTransfer->order_number ?? $returnTransfer->id }} | {{ $returnTransfer->created_at?->format('d/m/Y H:i') }}
            </p>
        </div>
        <a href="{{ route('admin.returns.history') }}" class="btn btn-soft-st">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    @php
        $statusKey = strtolower((string) $returnTransfer->status);
        $statusAliases = ['canceled' => 'cancelled'];
        $statusKey = $statusAliases[$statusKey] ?? $statusKey;
        $statusLabels = [
            'pending' => 'PENDIENTE',
            'accepted' => 'ACEPTADA',
            'rejected' => 'RECHAZADA',
            'cancelled' => 'CANCELADA',
        ];
        $statusClasses = [
            'pending' => 'badge-warning-soft',
            'accepted' => 'badge-success-soft',
            'rejected' => 'badge-danger-soft',
            'cancelled' => 'badge-danger-soft',
        ];
    @endphp

    <div class="st-card p-3 mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <strong>Estado:</strong>
                <div>
                    <span class="badge {{ $statusClasses[$statusKey] ?? 'badge-soft' }}">
                        {{ $statusLabels[$statusKey] ?? ucfirst($statusKey) }}
                    </span>
                </div>
            </div>
            <div class="col-md-4">
                <strong>Origen:</strong>
                <div>{{ $returnTransfer->fromLocation->name ?? '—' }}</div>
            </div>
            <div class="col-md-4">
                <strong>Destino:</strong>
                <div>{{ $returnTransfer->toLocation->name ?? '—' }}</div>
            </div>
            <div class="col-md-4">
                <strong>Total unidades:</strong>
                <div>{{ $totalUnits }}</div>
            </div>
            <div class="col-md-4">
                <strong>Solicitada por:</strong>
                <div>{{ $returnTransfer->initiator?->name ?? 'Sistema' }}</div>
            </div>
            <div class="col-md-4">
                <strong>Fecha:</strong>
                <div>{{ $returnTransfer->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
            </div>
        </div>
    </div>

    <div class="st-card p-3">
        <h5 class="mb-3">Líneas de la devolución</h5>
        @forelse ($returnTransfer->items as $item)
            <div class="mb-3 pb-3 border-bottom">
                <div><strong>Material:</strong> {{ ucfirst($item->material->type ?? 'N/D') }}@if ($item->material?->model) - {{ $item->material->model }}@endif</div>
                <div><strong>Serie:</strong> {{ $item->serial?->serial_number ?? '—' }}</div>
                <div><strong>Cantidad:</strong> {{ $item->quantity }}</div>
                <div><strong>Desde:</strong> {{ $returnTransfer->fromLocation->name ?? '—' }}</div>
                <div><strong>Hacia:</strong> {{ $returnTransfer->toLocation->name ?? '—' }}</div>
                <div><strong>Registrado:</strong> {{ $item->created_at?->format('d/m/Y H:i') ?? $returnTransfer->created_at?->format('d/m/Y H:i') }}</div>
            </div>
        @empty
            <p class="st-muted mb-0">Sin líneas registradas para esta devolución.</p>
        @endforelse
    </div>
@endsection
