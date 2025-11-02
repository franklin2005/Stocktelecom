@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1">Histórico de movimientos del almacén</h1>
            <p class="st-muted mb-0">
                Registros de ajustes, transferencias y bajas aplicadas al almacén{{ $warehouse ? ' '.$warehouse->name : '' }}.
            </p>
        </div>
        <a href="{{ route('admin.materials') }}" class="btn btn-outline-secondary">Volver a materiales</a>
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
                            $type = $movement->movement_type; // p.ej. 'transfer', 'adjustment', 'deletion'...
                            $badgeClass = match($type) {
                                'transfer'   => 'badge-accent',
                                'adjustment' => 'badge-soft',
                                'deletion', 'removal', 'write_off' => 'badge-danger-soft',
                                default      => 'badge-soft',
                            };
                        @endphp
                        <tr>
                            <td>{{ $movement->performed_at?->format('d/m/Y H:i') ?? $movement->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <span class="badge {{ $badgeClass }} text-uppercase">
                                    {{ str_replace('_', ' ', $type) }}
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
                                @if ($movement->reference_type && $movement->reference_id)
                                    <span class="badge badge-soft">
                                        {{ $movement->reference_type }} #{{ $movement->reference_id }}
                                    </span>
                                @else
                                    <span class="st-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $senderName = null;
                                    if ($movement->reference_type === 'transfer') {
                                        $senderName = $movement->transfer?->initiator?->name;
                                    }
                                @endphp
                                {{ $senderName ?? '—' }}
                            </td>
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
