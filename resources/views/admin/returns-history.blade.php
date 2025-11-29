@extends('layouts.app')

@php
    $statusOptions = [
        ''          => 'Todos',
        'pending'   => 'Pendiente',
        'accepted'  => 'Aceptada',
        'rejected'  => 'Rechazada',
        'cancelled' => 'Cancelada',
    ];

    $statusClasses = [
        'pending'   => 'badge-warning-soft',
        'accepted'  => 'badge-success-soft',
        'rejected'  => 'badge-danger-soft',
        'cancelled' => 'badge-danger-soft',
    ];

    // Alias por si la BD trae "canceled"
    $statusAliases = [
        'canceled' => 'cancelled',
    ];
@endphp

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-arrow-left-right me-2"></i>Histórico de devoluciones</h1>
        </div>
    </div>

    <form method="GET" class="st-card p-3 mb-4">
        <div class="row g-2 g-lg-3 align-items-end">
            <div class="col-6 col-lg-3">
                <label class="form-label">Desde</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control">
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label">Hasta</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control">
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label">Estado</label>
                <select name="status" class="form-select">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedStatus === $value || ($value === '' && empty($selectedStatus)))>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-lg-3 d-grid d-lg-flex gap-2">
                <button type="submit" class="btn btn-st"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                <a href="{{ route('admin.returns.history') }}" class="btn btn-danger-st"><i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar</a>
            </div>
        </div>
    </form>

    <div class="st-card p-3">
        @if ($returns->isEmpty())
            <p class="st-muted mb-0">No se encontraron devoluciones con los filtros seleccionados.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Orden</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Técnico origen</th>
                            <th>Destino</th>
                            <th>Detalle</th>
                            <th>Solicitada por</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($returns as $return)
                            @php
                                // Normaliza el estado para buscar en los mapas
                                $rawStatus   = strtolower((string) $return->status);
                                $statusKey   = $statusAliases[$rawStatus] ?? $rawStatus;

                                $statusClass = $statusClasses[$statusKey] ?? 'badge-soft';
                                $statusLabel = $statusOptions[$statusKey] ?? ucfirst($rawStatus);
                            @endphp
                            <tr>
                                <td>{{ $return->order_number }}</td>
                                <td>{{ $return->created_at->format('d/m/Y H:i') }}</td>

                                {{-- usa el label mapeado en español --}}
                                <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>

                                <td>{{ $return->fromLocation->name ?? '—' }}</td>
                                <td>{{ $return->toLocation->name ?? '—' }}</td>
                                <td>
                                    <ul class="mb-0 ps-3">
                                        @foreach ($return->items as $item)
                                            <li>
                                                {{ ucfirst($item->material->type) }}
                                                @if ($item->material->model)
                                                    - {{ $item->material->model }}
                                                @endif
                                                @if ($item->material_serial_id && $item->serial)
                                                    (Serie: {{ $item->serial->serial_number }})
                                                @else
                                                    (Cantidad: {{ $item->quantity }})
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td>{{ $return->initiator->name ?? 'Sistema' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $returns->links() }}
            </div>
        @endif
    </div>
@endsection
