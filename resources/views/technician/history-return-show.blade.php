@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h4 mb-1"><i class="bi bi-arrow-left-right me-2"></i>Detalle de devolución</h1>
            <p class="st-muted mb-0">
                Orden {{ $transfer->order_number ?? $transfer->id }} | {{ $transfer->created_at?->format('d/m/Y H:i') }}
            </p>
        </div>
        @php
            $currentRole = auth()->user()->role ?? null;
            $backUrl = in_array($currentRole, ['admin', 'super_admin', 'logistics'], true)
                ? route('admin.technicians.returns.history', $technician)
                : url()->previous();
        @endphp
        <a href="{{ $backUrl }}" class="btn btn-soft-st">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    @php
        $statusMap = [
            'accepted' => ['label' => 'Aceptada', 'class' => 'badge-success-soft'],
            'pending' => ['label' => 'Pendiente', 'class' => 'badge-warning-soft'],
            'rejected' => ['label' => 'Rechazada', 'class' => 'badge-danger-soft'],
            'cancelled' => ['label' => 'Cancelada', 'class' => 'badge-danger-soft'],
        ];
        $status = $statusMap[$transfer->status] ?? ['label' => ucfirst($transfer->status), 'class' => 'badge-soft'];
    @endphp

    <div class="st-card p-3 mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <strong>Estado:</strong>
                <div><span class="badge {{ $status['class'] }}">{{ $status['label'] }}</span></div>
            </div>
            <div class="col-md-4">
                <strong>Origen:</strong>
                <div>{{ $transfer->fromLocation->name ?? '—' }}</div>
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
                <strong>Solicitada por:</strong>
                <div>{{ $transfer->initiator?->name ?? 'Sistema' }}</div>
            </div>
            <div class="col-md-4">
                <strong>Fecha:</strong>
                <div>{{ $transfer->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
            </div>
        </div>

        <hr class="my-3">
        <h6 class="mb-2">CONTENIDO DE LA DEVOLUCIÓN</h6>
        @forelse ($transfer->items as $item)
            <div class="mb-3 pb-3 border-bottom">
                <div><strong>Material:</strong> {{ ucfirst($item->material->type ?? 'N/D') }}@if ($item->material?->model) - {{ $item->material->model }}@endif</div>
                <div><strong>Serie:</strong> {{ $item->serial?->serial_number ?? '—' }}</div>
                <div><strong>Cantidad:</strong> {{ $item->quantity }}</div>
                <div><strong>Desde:</strong> {{ $transfer->fromLocation->name ?? '—' }}</div>
                <div><strong>Hacia:</strong> {{ $transfer->toLocation->name ?? '—' }}</div>
                <div><strong>Registrado:</strong> {{ $item->created_at?->format('d/m/Y H:i') ?? $transfer->created_at?->format('d/m/Y H:i') }}</div>
            </div>
        @empty
            <p class="st-muted mb-0">Sin líneas registradas para esta devolución.</p>
        @endforelse
    </div>
@endsection
