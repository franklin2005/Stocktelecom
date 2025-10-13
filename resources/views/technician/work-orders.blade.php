@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="mb-1">Ordenes de trabajo</h2>
            <p class="text-muted mb-0">Gestiona tus ordenes abiertas y consulta el historial de intervenciones.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Ocurrio un problema:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($openOrder)
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
                    <div>
                        <h5 class="card-title mb-1">
                            Orden #{{ $openOrder->order_number }}
                            <span class="badge bg-warning text-dark ms-2">Abierta</span>
                        </h5>
                        <p class="text-muted mb-0">
                            Creada el {{ $openOrder->created_at?->format('d/m/Y H:i') }} |
                            Tecnico: {{ $openOrder->technician_name }}
                        </p>
                    </div>
                    <div class="mt-3 mt-md-0 d-flex gap-2">
                        <form method="POST" action="{{ route('technician.work-orders.confirm', $openOrder) }}">
                            @csrf
                            <button type="submit" class="btn btn-success"
                                onclick="return confirm('¿Confirmar esta orden? No se podra modificar despues.');">
                                Confirmar orden
                            </button>
                        </form>
                        <form method="POST" action="{{ route('technician.work-orders.cancel', $openOrder) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger"
                                onclick="return confirm('¿Cancelar esta orden?');">
                                Cancelar orden
                            </button>
                        </form>
                    </div>
                </div>

                @if ($openOrder->notes)
                    <div class="alert alert-info">
                        <strong>Notas:</strong> {{ $openOrder->notes }}
                    </div>
                @endif

                <div class="row g-4">
                    <div class="col-12 col-xl-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3">Agregar material (cantidad)</h6>
                            @if ($nonSerializedInventory->isEmpty())
                                <p class="text-muted mb-0">No tienes materiales por cantidad disponibles.</p>
                            @else
                                <form class="row g-2 align-items-end"
                                    action="{{ route('technician.work-orders.items.quantity', $openOrder) }}" method="POST">
                                    @csrf
                                    <div class="col-7">
                                        <label class="form-label small text-muted">Material</label>
                                        <select name="material_id" class="form-select">
                                            @foreach ($nonSerializedInventory as $item)
                                                <option value="{{ $item->material_id }}">
                                                    {{ ucfirst($item->material->type) }}
                                                    @if ($item->material->model)
                                                        - {{ $item->material->model }}
                                                    @endif
                                                    ({{ $item->quantity }} disponibles)
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-3">
                                        <label class="form-label small text-muted">Cantidad</label>
                                        <input type="number" name="quantity" min="1" value="1" class="form-control">
                                    </div>
                                    <div class="col-2">
                                        <button type="submit" class="btn btn-primary w-100">Añadir</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3">Agregar material (seriales)</h6>
                            @if ($availableSerials->isEmpty())
                                <p class="text-muted mb-0">No hay seriales disponibles para añadir.</p>
                            @else
                                <form action="{{ route('technician.work-orders.items.serial', $openOrder) }}" method="POST">
                                    @csrf
                                    <div class="accordion" id="openOrderSerials">
                                        @foreach ($availableSerials as $materialId => $serialGroup)
                                            @php
                                                $material = $serialGroup->first()->material;
                                                $accordionId = 'serial-material-' . $materialId;
                                                $inventoryCount = $serializedInventory[$materialId]->quantity ?? $serialGroup->count();
                                            @endphp
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="heading-{{ $accordionId }}">
                                                    <button class="accordion-button collapsed" type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#collapse-{{ $accordionId }}"
                                                        aria-expanded="false"
                                                        aria-controls="collapse-{{ $accordionId }}">
                                                        {{ ucfirst($material->type ?? 'Material') }}
                                                        @if ($material?->model)
                                                            - {{ $material->model }}
                                                        @endif
                                                        <span class="badge bg-secondary ms-2">
                                                            {{ $inventoryCount }} en stock
                                                        </span>
                                                    </button>
                                                </h2>
                                                <div id="collapse-{{ $accordionId }}" class="accordion-collapse collapse"
                                                    aria-labelledby="heading-{{ $accordionId }}"
                                                    data-bs-parent="#openOrderSerials">
                                                    <div class="accordion-body">
                                                        <ul class="list-unstyled mb-0">
                                                            @foreach ($serialGroup as $serial)
                                                                <li class="mb-2">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox"
                                                                            value="{{ $serial->id }}"
                                                                            id="serial-{{ $serial->id }}"
                                                                            name="serial_ids[]">
                                                                        <label class="form-check-label"
                                                                            for="serial-{{ $serial->id }}">
                                                                            Serie {{ $serial->serial_number }}
                                                                        </label>
                                                                    </div>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="d-flex justify-content-end mt-3">
                                        <button type="submit" class="btn btn-primary">
                                            Añadir seleccionados
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <h6 class="mb-3">Material añadido a la orden</h6>

                @php
                    $quantityItems = $openOrder->items->whereNull('material_serial_id');
                    $serialItems = $openOrder->items->whereNotNull('material_serial_id');
                @endphp

                @if ($openOrder->items->isEmpty())
                    <p class="text-muted mb-0">Todavía no has agregado materiales a esta orden.</p>
                @else
                    <div class="row g-4">
                        <div class="col-12 col-xl-6">
                            <div class="table-responsive">
                                <table class="table table-striped align-middle">
                                    <thead>
                                        <tr>
                                            <th>Material</th>
                                            <th class="text-end">Cantidad</th>
                                            <th class="text-end">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($quantityItems as $item)
                                            <tr>
                                                <td>
                                                    {{ ucfirst($item->material->type ?? 'Material') }}
                                                    @if ($item->material?->model)
                                                        - {{ $item->material->model }}
                                                    @endif
                                                </td>
                                                <td class="text-end">{{ $item->quantity }}</td>
                                                <td class="text-end">
                                                    <form method="POST"
                                                        action="{{ route('technician.work-orders.items.destroy', [$openOrder, $item]) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('¿Eliminar este material de la orden?');">
                                                            Quitar
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">Sin materiales por
                                                    cantidad.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-12 col-xl-6">
                            <div class="table-responsive">
                                <table class="table table-striped align-middle">
                                    <thead>
                                        <tr>
                                            <th>Material</th>
                                            <th>Serie</th>
                                            <th class="text-end">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($serialItems as $item)
                                            <tr>
                                                <td>
                                                    {{ ucfirst($item->material->type ?? 'Material') }}
                                                    @if ($item->material?->model)
                                                        - {{ $item->material->model }}
                                                    @endif
                                                </td>
                                                <td>{{ $item->serial?->serial_number }}</td>
                                                <td class="text-end">
                                                    <form method="POST"
                                                        action="{{ route('technician.work-orders.items.destroy', [$openOrder, $item]) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('¿Eliminar este material de la orden?');">
                                                            Quitar
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">Sin materiales
                                                    serializados.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3">Crear nueva orden</h5>
                <p class="text-muted">Puedes mantener una sola orden abierta a la vez.</p>
                <form method="POST" action="{{ route('technician.work-orders.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Número de orden</label>
                            <input type="text" name="order_number" value="{{ old('order_number') }}"
                                class="form-control" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Notas (opcional)</label>
                            <input type="text" name="notes" value="{{ old('notes') }}" class="form-control">
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Crear orden</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Histórico de ordenes</h5>
                <span class="text-muted small">Incluye ordenes abiertas, confirmadas y canceladas.</span>
            </div>

            @if ($workOrders->isEmpty())
                <p class="text-muted mb-0">Todavía no tienes ordenes de trabajo registradas.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Estado</th>
                                <th class="text-center">Materiales</th>
                                <th>Notas</th>
                                <th class="text-end">Creada</th>
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
                                    <td>
                                        <span class="badge {{ $statusLabel['class'] }}">{{ $statusLabel['label'] }}</span>
                                    </td>
                                    <td class="text-center">{{ $order->items_count }}</td>
                                    <td>
                                        @if ($order->notes)
                                            <small class="text-muted">{{ $order->notes }}</small>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $order->created_at?->format('d/m/Y H:i') }}</td>
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
