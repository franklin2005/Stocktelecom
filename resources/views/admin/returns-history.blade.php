@extends('layouts.app')

@php
    $statusOptions = [
        '' => 'Todos',
        'pending' => 'Pendiente',
        'accepted' => 'Aceptada',
        'rejected' => 'Rechazada',
        'cancelled' => 'Cancelada',
    ];

    $statusClasses = [
        'pending' => 'badge-warning-soft',
        'accepted' => 'badge-success-soft',
        'rejected' => 'badge-danger-soft',
        'cancelled' => 'badge-danger-soft',
    ];
@endphp

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1">Histórico de devoluciones</h1>
            <p class="st-muted mb-0">Revisa todas las solicitudes de devolución registradas desde los técnicos hacia el almacén.</p>
        </div>
    </div>

    <form method="GET" class="st-card p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Desde</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Hasta</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Técnico</label>
                <select name="technician_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($technicians as $technician)
                        <option value="{{ $technician->id }}" @selected($selectedTechnicianId === $technician->id)>
                            {{ $technician->name }}
                            @if ($technician->tech_code)
                                ({{ $technician->tech_code }})
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select name="status" class="form-select">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedStatus === $value || ($value === '' && empty($selectedStatus)))>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-st flex-grow-1">Filtrar</button>
                <a href="{{ route('admin.returns.history') }}" class="btn btn-outline-secondary">Limpiar</a>
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
                                $statusClass = $statusClasses[$return->status] ?? 'badge-soft';
                            @endphp
                            <tr>
                                <td>{{ $return->order_number }}</td>
                                <td>{{ $return->created_at->format('d/m/Y H:i') }}</td>
                                <td><span class="badge {{ $statusClass }}">{{ ucfirst($return->status) }}</span></td>
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
