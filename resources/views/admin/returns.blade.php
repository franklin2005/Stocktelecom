@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1">Devoluciones desde técnico</h1>
            <p class="st-muted mb-0">Solicita a un técnico la devolución de materiales al almacén principal.</p>
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

    <div class="row g-4 mb-4">
        <div class="col-12 col-xxl-8">
            {{-- MATERIALES NO SERIALIZADOS --}}
            <div class="st-card p-3 mb-4">
                <h2 class="h5 mb-1">Materiales no serializados</h2>
                <p class="st-muted small mb-3">
                    Selecciona la cantidad que deseas solicitar en devolución desde el técnico.
                </p>

                @if (! $selectedTechnician)
                    <p class="st-muted mb-0">Selecciona un técnico para visualizar su inventario.</p>
                @elseif ($nonSerializedInventory->isEmpty())
                    <p class="st-muted mb-0">El técnico no tiene materiales no serializados disponibles.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th class="text-end">En stock</th>
                                    <th class="text-end">En solicitudes</th>
                                    <th class="text-end">Disponible</th>
                                    <th class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($nonSerializedInventory as $item)
                                    @php
                                        $reservedKey = 'quantity-' . $item->material_id;
                                        $pending = $pendingReservations['quantities'][$reservedKey] ?? 0;
                                        $cartReserved = $reservedQuantities[$reservedKey] ?? 0;
                                        $available = max(($item->quantity ?? 0) - $pending - $cartReserved, 0);
                                    @endphp

                                    {{-- Si no hay disponibilidad, saltar el material --}}
                                    @if ($available === 0)
                                        @continue
                                    @endif

                                    <tr>
                                        <td>
                                            {{ ucfirst($item->material->type) }}
                                            @if ($item->material->model)
                                                - {{ $item->material->model }}
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $item->quantity }}</td>
                                        <td class="text-end">{{ $pending }}</td>
                                        <td class="text-end">{{ $available }}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('admin.returns.cart.add') }}" class="d-inline-flex align-items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="intent" value="quantity">
                                                <input type="hidden" name="technician_id" value="{{ $selectedTechnician?->id }}">
                                                <input type="hidden" name="material_id" value="{{ $item->material_id }}">
                                                <input
                                                    type="number"
                                                    name="quantity"
                                                    class="form-control form-control-sm"
                                                    min="1"
                                                    max="{{ $available }}"
                                                    value="1"
                                                    style="width: 90px;"
                                                >
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    Añadir
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- MATERIALES SERIALIZADOS --}}
            <div class="st-card p-3 mb-4">
                <h2 class="h5 mb-1">Materiales serializados</h2>
                <p class="st-muted small mb-3">
                    Selecciona los números de serie que el técnico devolverá al almacén.
                </p>

                @if (! $selectedTechnician)
                    <p class="st-muted mb-0">Selecciona un técnico para visualizar sus seriales.</p>
                @elseif ($availableSerials->isEmpty())
                    <p class="st-muted mb-0">No hay números de serie disponibles para solicitar.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Serie</th>
                                    <th class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($availableSerials as $serial)
                                    @php
                                        $isReserved = isset($pendingReservations['serial_ids'][$serial->id]) || in_array($serial->id, $serialsInCart, true);
                                    @endphp
                                    <tr>
                                        <td>
                                            {{ ucfirst($serial->material->type) }}
                                            @if ($serial->material->model)
                                                - {{ $serial->material->model }}
                                            @endif
                                        </td>
                                        <td>{{ $serial->serial_number }}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('admin.returns.cart.add') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="intent" value="serial">
                                                <input type="hidden" name="technician_id" value="{{ $selectedTechnician?->id }}">
                                                <input type="hidden" name="serial_ids[]" value="{{ $serial->id }}">
                                                <button type="submit" class="btn btn-sm btn-outline-primary" {{ $isReserved ? 'disabled' : '' }}>
                                                    Añadir
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- LISTA DE DEVOLUCIÓN --}}
            <div class="st-card p-3">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h2 class="h5 mb-0">Lista de devolución</h2>
                    <form method="POST" action="{{ route('admin.returns.cart.clear') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger">Vaciar</button>
                    </form>
                </div>

                @if (empty($cartItems))
                    <p class="st-muted mb-0">Aún no has seleccionado materiales para la devolución.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th class="text-end">Detalle</th>
                                    <th class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cartItems as $cartItem)
                                    <tr>
                                        <td>
                                            {{ ucfirst($cartItem['material']->type) }}
                                            @if ($cartItem['material']->model)
                                                - {{ $cartItem['material']->model }}
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if ($cartItem['type'] === 'quantity')
                                                {{ $cartItem['quantity'] }} uds
                                            @else
                                                Serie: {{ $cartItem['serial']->serial_number }}
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('admin.returns.cart.remove', $cartItem['key']) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Quitar</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="st-muted small">Elementos: {{ $cartSummary['total_items'] }}</span>
                        <span class="fw-semibold">Total a solicitar: {{ $cartSummary['total_units'] }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- PANEL LATERAL --}}
        <div class="col-12 col-xxl-4">
            <div class="st-card p-3 mb-4">
                <h2 class="h5 mb-3">Seleccionar técnico</h2>

                <form method="GET" action="{{ route('admin.returns') }}" class="mb-3">
                    <div class="list-group">
                        @forelse ($technicians as $technician)
                            <label class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                                <input
                                    class="form-check-input me-1"
                                    type="radio"
                                    name="technician_id"
                                    value="{{ $technician->id }}"
                                    onchange="this.form.submit()"
                                    {{ $selectedTechnician && $technician->id === $selectedTechnician->id ? 'checked' : '' }}
                                >
                                <span>
                                    {{ $technician->name }}
                                    @if ($technician->tech_code)
                                        <small class="st-muted">({{ $technician->tech_code }})</small>
                                    @endif
                                </span>
                            </label>
                        @empty
                            <p class="st-muted small mb-0">No hay técnicos registrados.</p>
                        @endforelse
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.returns.send') }}" id="admin-return-form" data-total-units="{{ $cartSummary['total_units'] }}">
                    @csrf
                    <input type="hidden" name="technician_id" value="{{ $selectedTechnician?->id }}">
                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                        {{ empty($cartItems) || ! $selectedTechnician ? 'disabled' : '' }}
                    >
                        Enviar solicitud de devolución
                    </button>
                </form>
            </div>

            <div class="st-card p-3">
                <h2 class="h5 mb-3">Resumen</h2>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><strong>Técnico:</strong> {{ $selectedTechnician->name ?? '—' }}</li>
                    <li class="mb-2"><strong>Almacén destino:</strong> {{ $warehouse->name }}</li>
                    <li class="mb-2"><strong>Elementos en lista:</strong> {{ $cartSummary['total_units'] }}</li>
                    <li class="mb-0"><strong>Pedidos pendientes:</strong> {{ count($pendingReservations['serial_ids']) + array_sum($pendingReservations['quantities']) }}</li>
                </ul>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('admin-return-form');
        if (!form) return;

        form.addEventListener('submit', function (event) {
            const totalUnits = Number(form.dataset.totalUnits || 0);
            const technicianName = @json(optional($selectedTechnician)->name ?? 'el técnico');
            const message = `Solicitarás la devolución de ${totalUnits} ${totalUnits === 1 ? 'elemento' : 'elementos'} a ${technicianName}. ¿Deseas continuar?`;

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
</script>
@endpush
