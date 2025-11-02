@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Órdenes de trabajo</h1>
            <p class="st-muted mb-0">Consulta y filtra las órdenes creadas por el personal técnico.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.work-orders.index') }}" class="card st-card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label for="status" class="form-label">Estado</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="open" @selected($status === 'open')>Abierta</option>
                        <option value="confirmed" @selected($status === 'confirmed')>Confirmada</option>
                        <option value="cancelled" @selected($status === 'cancelled')>Cancelada</option>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label for="technician_id" class="form-label">Técnico</label>
                    <select id="technician_id" name="technician_id" class="form-select">
                        <option value="0">Todos</option>
                        @foreach ($technicians as $technician)
                            <option value="{{ $technician->id }}" @selected($technicianId === $technician->id)>{{ $technician->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <label for="from" class="form-label">Desde</label>
                    <input id="from" type="date" name="from" value="{{ $from }}" class="form-control">
                </div>

                <div class="col-12 col-md-2">
                    <label for="to" class="form-label">Hasta</label>
                    <input id="to" type="date" name="to" value="{{ $to }}" class="form-control">
                </div>

                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-st flex-fill flex-md-grow-0">Filtrar</button>
                    <a href="{{ route('admin.work-orders.index') }}" class="btn btn-outline-secondary flex-fill flex-md-grow-0">Limpiar</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card st-card shadow-sm">
        <div class="card-body">
            @if ($workOrders->isEmpty())
                <p class="st-muted mb-0">No se encontraron órdenes con los criterios seleccionados.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Técnico</th>
                                <th>Estado</th>
                                <th class="text-center">Materiales</th>
                                <th class="text-end">Creada</th>
                                <th class="text-end">Actualizada</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($workOrders as $order)
                                @php
                                    // Map a tus badges suaves
                                    $statusMap = [
                                        'open'      => ['label' => 'Abierta',   'class' => 'badge-warning-soft'],
                                        'confirmed' => ['label' => 'Confirmada','class' => 'badge-success-soft'],
                                        'cancelled' => ['label' => 'Cancelada', 'class' => 'badge-danger-soft'],
                                    ];
                                    $statusLabel = $statusMap[$order->status] ?? ['label' => ucfirst($order->status), 'class' => 'badge-soft'];
                                @endphp
                                <tr>
                                    <td>{{ $order->order_number }}</td>
                                    <td>{{ $order->technician?->name ?? 'Sin asignar' }}</td>
                                    <td>
                                        <span class="badge {{ $statusLabel['class'] }}">{{ $statusLabel['label'] }}</span>
                                    </td>
                                    <td class="text-center">{{ $order->items_count }}</td>
                                    <td class="text-end">{{ $order->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="text-end">{{ $order->updated_at?->format('d/m/Y H:i') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.work-orders.show', $order) }}" class="btn btn-sm btn-outline-primary">
                                            Ver detalle
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $workOrders->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
