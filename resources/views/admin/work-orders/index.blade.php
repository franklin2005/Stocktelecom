
@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Ordenes de trabajo</h2>
            <p class="text-muted mb-0">Consulta y filtra las ordenes creadas por el personal técnico.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.work-orders.index') }}" class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label">Estado</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="open" @selected($status === 'open')>Abierta</option>
                        <option value="confirmed" @selected($status === 'confirmed')>Confirmada</option>
                        <option value="cancelled" @selected($status === 'cancelled')>Cancelada</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Técnico</label>
                    <select name="technician_id" class="form-select">
                        <option value="0">Todos</option>
                        @foreach ($technicians as $technician)
                            <option value="{{ $technician->id }}" @selected($technicianId === $technician->id)>
                                {{ $technician->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-5 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill flex-md-grow-0">Filtrar</button>
                    <a href="{{ route('admin.work-orders.index') }}" class="btn btn-outline-secondary flex-fill flex-md-grow-0">
                        Limpiar
                    </a>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($workOrders->isEmpty())
                <p class="text-muted mb-0">No se encontraron ordenes con los criterios seleccionados.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
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
                                    $statusLabel = [
                                        'open' => ['label' => 'Abierta', 'class' => 'bg-warning text-dark'],
                                        'confirmed' => ['label' => 'Confirmada', 'class' => 'bg-success'],
                                        'cancelled' => ['label' => 'Cancelada', 'class' => 'bg-danger'],
                                    ][$order->status] ?? ['label' => ucfirst($order->status), 'class' => 'bg-secondary'];
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

                {{ $workOrders->links() }}
            @endif
        </div>
    </div>
@endsection
