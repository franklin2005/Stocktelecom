@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="mb-1">Historico de movimientos del almacen</h2>
            <p class="text-muted mb-0">
                Registros de ajustes, transferencias y bajas aplicadas al almacen{{ $warehouse ? ' '.$warehouse->name : '' }}.
            </p>
        </div>
        <a href="{{ route('admin.materials') }}" class="btn btn-outline-primary">Volver a materiales</a>
    </div>

    <form method="GET" class="card shadow-sm mb-4">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Desde</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Hasta</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="{{ route('admin.warehouse-movements') }}" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle">
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
                                <td>
                                    @if ($movement->serial)
                                        {{ $movement->serial->serial_number }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $movement->fromLocation->name ?? '-' }}</td>
                                <td>{{ $movement->toLocation->name ?? '-' }}</td>
                                <td>{{ $movement->quantity }}</td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        {{ $movement->reference_type }} #{{ $movement->reference_id }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $senderName = null;
                                        if ($movement->reference_type === 'transfer') {
                                            $senderName = $movement->transfer?->initiator?->name;
                                        }
                                    @endphp
                                    {{ $senderName ?? '-' }}
                                </td>
                                <td>{{ $movement->performer->name ?? 'Sistema' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    Aun no hay movimientos registrados para el almacen.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($movements->hasPages())
            <div class="card-footer">
                {{ $movements->links() }}
            </div>
        @endif
    </div>
@endsection
