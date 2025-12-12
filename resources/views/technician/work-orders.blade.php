@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-clipboard-check me-2"></i>Órdenes de trabajo</h1>
            <p class="st-muted mb-0">Gestiona tus órdenes abiertas y consulta el historial de intervenciones.</p>
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
            <strong>Ocurrió un problema:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($openOrder)
        <div class="st-card p-3 mb-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
                <div>
                    <h5 class="card-title mb-1">
                        Orden #{{ $openOrder->order_number }}
                        <span class="badge badge-warning-soft text-dark ms-2">Abierta</span>
                    </h5>
                    <p class="st-muted mb-0">
                        Creada el {{ $openOrder->created_at?->format('d/m/Y H:i') }} ·
                        Técnico: {{ $openOrder->technician_name }}
                    </p>
                </div>
                <div class="mt-3 mt-md-0 d-flex gap-2">
                    <form method="POST" action="{{ route('technician.work-orders.confirm', $openOrder) }}">
                        @csrf
                        <button type="submit" class="btn btn-success-st"
                                onclick="return confirm('¿Confirmar esta orden? No se podrá modificar después.');">
                            <i class="bi bi-check-circle me-1"></i>Confirmar
                        </button>
                    </form>
                    <form method="POST" action="{{ route('technician.work-orders.cancel', $openOrder) }}">
                        @csrf
                        <button type="submit" class="btn btn-danger-st"
                                onclick="return confirm('¿Cancelar esta orden?');">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                    </form>
                </div>
            </div>

            @if ($openOrder->notes)
                <div class="alert alert-info">
                    <strong>Notas:</strong> {{ $openOrder->notes }}<br>
                    <small>Registrado por {{ $openOrder->notes_author_name ?? 'Desconocido' }}</small>
                </div>
            @endif

            <div class="row g-4">
                {{-- Agregar por cantidad --}}
                <div class="col-12 col-xl-6">
                    <div class="border rounded p-3 h-100">
                        <h6 class="mb-3">Agregar material (cantidad)</h6>
                        @if ($nonSerializedInventory->isEmpty())
                            <p class="st-muted mb-0">No tienes materiales por cantidad disponibles.</p>
                        @else
                            <form class="row g-2 align-items-end"
                                  action="{{ route('technician.work-orders.items.quantity', $openOrder) }}"
                                  method="POST">
                                @csrf
                                <div class="col-7">
                                    <label class="form-label small st-muted">Material</label>
                                    <select name="material_id" class="form-select">
                                        @foreach ($nonSerializedInventory as $item)
                                            <option value="{{ $item->material_id }}">
                                                {{ ucfirst($item->material->type) }}
                                                @if ($item->material->model) - {{ $item->material->model }} @endif
                                                ({{ $item->quantity }} disponibles)
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-3">
                                    <label class="form-label small st-muted">Cantidad</label>
                                    <input type="number" name="quantity" min="1" value="1" class="form-control">
                                </div>
                                <div class="col-2">
                                    <button type="submit" class="btn btn-success-st w-100"><i class="bi bi-plus-circle me-1"></i>Añadir</button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Agregar por serial --}}
                <div class="col-12 col-xl-6">
                    <div class="border rounded p-3 h-100">
                        <h6 class="mb-3">Agregar material (seriales)</h6>
                        @if ($availableSerials->isEmpty())
                            <p class="st-muted mb-0">No hay seriales disponibles para añadir.</p>
                        @else
                            <form action="{{ route('technician.work-orders.items.serial', $openOrder) }}" method="POST">
                                @csrf
                                <div class="accordion" id="openOrderSerials">
                                    @foreach ($availableSerials as $materialId => $serialGroup)
                                        @php
                                            $material = $serialGroup->first()->material;
                                            $accordionId = 'serial-material-' . $materialId;
                                            $inventoryCount = $serialGroup->count();
                                        @endphp
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="heading-{{ $accordionId }}">
                                                <button class="accordion-button collapsed" type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#collapse-{{ $accordionId }}"
                                                        aria-expanded="false"
                                                        aria-controls="collapse-{{ $accordionId }}">
                                                    {{ ucfirst($material->type ?? 'Material') }}
                                                    @if ($material?->model) - {{ $material->model }} @endif
                                                    <span class="badge badge-soft ms-2">{{ $inventoryCount }} en stock</span>
                                                </button>
                                            </h2>
                                            <div id="collapse-{{ $accordionId }}" class="accordion-collapse collapse"
                                                 aria-labelledby="heading-{{ $accordionId }}" data-bs-parent="#openOrderSerials">
                                                <div class="accordion-body">
                                                    <ul class="list-unstyled mb-0">
                                                        @foreach ($serialGroup as $serial)
                                                            <li class="mb-2">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox"
                                                                           value="{{ $serial->id }}"
                                                                           id="serial-{{ $serial->id }}"
                                                                           name="serial_ids[]">
                                                                    <label class="form-check-label" for="serial-{{ $serial->id }}">
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
                                    <button type="submit" class="btn btn-success-st">
                                        <i class="bi bi-plus-circle me-1"></i>Añadir
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
                <p class="st-muted mb-0">Todavía no has agregado materiales a esta orden.</p>
            @else
                <div class="row g-4">
                    <div class="col-12 col-xl-6">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Material</th>
                                        <th class="text-end">Cantidad</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($quantityItems as $item)
                                        <tr>
                                            <td>
                                                {{ ucfirst($item->material->type ?? 'Material') }}
                                                @if ($item->material?->model) - {{ $item->material->model }} @endif
                                            </td>
                                            <td class="text-end">{{ $item->quantity }}</td>
                                            <td class="text-end">
                                                <form method="POST"
                                                      action="{{ route('technician.work-orders.items.destroy', [$openOrder, $item]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="btn btn-sm btn-danger-st"
                                                            onclick="return confirm('¿Eliminar este material de la orden?');">
                                                        <i class="bi bi-x-circle me-1"></i>Quitar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center st-muted">Sin materiales por cantidad.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-12 col-xl-6">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Material</th>
                                        <th>Serie</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($serialItems as $item)
                                        <tr>
                                            <td>
                                                {{ ucfirst($item->material->type ?? 'Material') }}
                                                @if ($item->material?->model) - {{ $item->material->model }} @endif
                                            </td>
                                            <td>{{ $item->serial?->serial_number }}</td>
                                            <td class="text-end">
                                                <form method="POST"
                                                      action="{{ route('technician.work-orders.items.destroy', [$openOrder, $item]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="btn btn-sm btn-danger-st"
                                                            onclick="return confirm('¿Eliminar este material de la orden?');">
                                                        <i class="bi bi-x-circle me-1"></i>Quitar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center st-muted">Sin materiales serializados.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @else
        <div class="st-card p-3 mb-4">
            <h5 class="card-title mb-3">Crear nueva orden</h5>
            <p class="st-muted">Puedes mantener una sola orden abierta a la vez.</p>
            <form method="POST" action="{{ route('technician.work-orders.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Número de orden</label>
                        <input type="text" name="order_number" value="{{ old('order_number') }}" class="form-control" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Notas (opcional)</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" class="form-control">
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-success-st"><i class="bi bi-check-circle me-1"></i>Crear orden</button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    <form method="GET" class="st-card p-3 mb-4">
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
                <button type="submit" class="btn btn-st"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                <a href="{{ route('technician.work-orders') }}" class="btn btn-danger-st"><i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar</a>
            </div>
        </div>
    </form>

    <div class="st-card p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0">Histórico de órdenes</h5>
            <span class="st-muted small">Incluye órdenes abiertas, confirmadas y canceladas.</span>
        </div>

        @if ($workOrders->isEmpty())
            <p class="st-muted mb-0">Todavía no tienes órdenes de trabajo registradas.</p>
        @else
            <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Estado</th>
                                    <th class="text-center">Materiales</th>
                                    <th class="text-center">Creada</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($workOrders as $order)
                                    @php
                                $statusLabel = [
                                    'open' => ['label' => 'ABIERTA', 'class' => 'badge-warning-soft text-dark'],
                                    'confirmed' => ['label' => 'CONFIRMADA', 'class' => 'badge-success-soft'],
                                    'cancelled' => ['label' => 'CANCELADA', 'class' => 'badge-danger-soft'],
                                ][$order->status] ?? ['label' => ucfirst($order->status), 'class' => 'badge-soft'];
                            @endphp
                            <tr style="cursor:pointer;" onclick="window.location='{{ route('technician.work-orders.show', $order) }}'">
                                <td>{{ $order->order_number }}</td>
                                <td>
                                    <span class="badge {{ $statusLabel['class'] }}">{{ $statusLabel['label'] }}</span>
                                </td>
                                <td class="text-center">{{ $order->items_count }}</td>
                                <td class="text-center">{{ $order->created_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $workOrders->links() }}
        @endif
    </div>
@endsection
