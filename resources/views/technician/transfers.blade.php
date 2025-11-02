@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1">Transferencias de materiales</h1>
            <p class="st-muted mb-0">Gestiona las salidas y recepciones desde tu inventario personal.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('technician.returns.history') }}" class="btn btn-outline-secondary btn-sm">
                Histórico de devoluciones
            </a>
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

    @php
        $disableSend = empty($cartItems) || $recipientTechnicians->isEmpty();
    @endphp

    <div class="row g-4 mb-4">
        <div class="col-12 col-xxl-8">
            {{-- No serializados --}}
            <div class="st-card p-3 mb-4">
                <h5 class="card-title mb-3">Materiales no serializados</h5>
                <p class="st-muted small mb-3">Selecciona la cantidad a transferir desde tu stock personal.</p>

                @if ($nonSerializedInventory->isEmpty())
                    <p class="st-muted mb-0">No tienes materiales no serializados disponibles.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th class="text-end">En stock</th>
                                    <th class="text-end">Reservado</th>
                                    <th class="text-end">Disponible</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($nonSerializedInventory as $item)
                                    @php
                                        $reservedKey = 'quantity-' . $item->material_id;
                                        $reserved = $reservedQuantities[$reservedKey] ?? 0;
                                        $available = max(($item->quantity ?? 0) - $reserved, 0);
                                    @endphp
                                    <tr>
                                        <td>
                                            {{ ucfirst($item->material->type) }}
                                            @if ($item->material->model)
                                                - {{ $item->material->model }}
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $item->quantity }}</td>
                                        <td class="text-end">{{ $reserved }}</td>
                                        <td class="text-end">{{ $available }}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('technician.transfers.cart.add') }}" class="d-inline-flex align-items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="intent" value="quantity">
                                                <input type="hidden" name="material_id" value="{{ $item->material_id }}">
                                                <input
                                                    type="number"
                                                    name="quantity"
                                                    class="form-control form-control-sm"
                                                    min="1"
                                                    max="{{ $available }}"
                                                    value="1"
                                                    style="width: 90px;"
                                                    {{ $available === 0 ? 'disabled' : '' }}
                                                >
                                                <button type="submit" class="btn btn-sm btn-outline-primary" {{ $available === 0 ? 'disabled' : '' }}>
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

            {{-- Serializados --}}
            <div class="st-card p-3 mb-4">
                <h5 class="card-title mb-3">Materiales serializados</h5>
                <p class="st-muted small mb-3">Marca los números de serie que deseas transferir.</p>

                @if ($availableSerials->isEmpty())
                    <p class="st-muted mb-0">No cuentas con números de serie disponibles en tu stock.</p>
                @else
                    @php
                        $serialsByMaterial = $availableSerials->groupBy('material_id');
                        $serializedLookup = $serializedInventory->keyBy('material_id');
                    @endphp
                    <form method="POST" action="{{ route('technician.transfers.cart.add') }}">
                        @csrf
                        <input type="hidden" name="intent" value="serial">

                        <div class="accordion" id="serialAccordion">
                            @foreach ($serialsByMaterial as $materialId => $serialGroup)
                                @php
                                    $material = $serialGroup->first()->material;
                                    $inCartCount = collect($serialGroup)->whereIn('id', $serialsInCart)->count();
                                    $inventoryCount = $serializedLookup[$materialId]->quantity ?? $serialGroup->count();
                                    $accordionId = 'serial-material-' . $materialId;
                                @endphp
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading-{{ $accordionId }}">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $accordionId }}" aria-expanded="false" aria-controls="collapse-{{ $accordionId }}">
                                            {{ ucfirst($material->type ?? 'Material') }}
                                            @if ($material?->model)
                                                - {{ $material->model }}
                                            @endif
                                            <span class="badge badge-soft ms-2">{{ $inventoryCount }} en stock</span>
                                            @if ($inCartCount > 0)
                                                <span class="badge badge-accent ms-2">{{ $inCartCount }} en lista</span>
                                            @endif
                                        </button>
                                    </h2>
                                    <div id="collapse-{{ $accordionId }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ $accordionId }}" data-bs-parent="#serialAccordion">
                                        <div class="accordion-body">
                                            <ul class="list-unstyled mb-0">
                                                @foreach ($serialGroup as $serial)
                                                    @php $inCart = in_array($serial->id, $serialsInCart, true); @endphp
                                                    <li class="mb-2">
                                                        <div class="form-check">
                                                            <input
                                                                class="form-check-input"
                                                                type="checkbox"
                                                                value="{{ $serial->id }}"
                                                                id="serial-{{ $serial->id }}"
                                                                name="serial_ids[]"
                                                                {{ $inCart ? 'disabled' : '' }}
                                                            >
                                                            <label class="form-check-label" for="serial-{{ $serial->id }}">
                                                                Serie {{ $serial->serial_number }}
                                                                @if ($inCart)
                                                                    <span class="badge badge-accent ms-2">En lista</span>
                                                                @endif
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
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                Añadir seleccionados
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- Carrito --}}
            <div class="st-card p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Lista de transferencia</h5>
                    <form method="POST" action="{{ route('technician.transfers.cart.clear') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger" {{ empty($cartItems) ? 'disabled' : '' }}>
                            Vaciar lista
                        </button>
                    </form>
                </div>

                @if (empty($cartItems))
                    <p class="st-muted mb-0">No has añadido materiales a la transferencia.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Detalle</th>
                                    <th class="text-end">Unidades</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cartItems as $item)
                                    <tr>
                                        <td>
                                            {{ ucfirst($item['material']->type ?? 'Material') }}
                                            @if ($item['material']?->model)
                                                - {{ $item['material']->model }}
                                            @endif
                                            @if ($item['type'] === 'serial' && isset($item['serial']))
                                                <br><small class="st-muted">Serie: {{ $item['serial']->serial_number }}</small>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            {{ $item['type'] === 'quantity' ? $item['quantity'] : 1 }}
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('technician.transfers.cart.remove', $item['key']) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                    Quitar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="st-muted small">Elementos: {{ $cartSummary['total_items'] }}</span>
                        <span class="fw-semibold">Total a transferir: {{ $cartSummary['total_units'] }}</span>
                    </div>
                @endif
            </div>

            <div class="st-card p-3 mt-4">
                <h5 class="card-title mb-3">Devoluciones pendientes</h5>

                @if ($pendingReturns->isEmpty())
                    <p class="st-muted mb-0">No tienes solicitudes de devolución pendientes.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Orden</th>
                                    <th>Destino</th>
                                    <th>Materiales</th>
                                    <th>Fecha</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pendingReturns as $transfer)
                                    <tr>
                                        <td><strong>{{ $transfer->order_number }}</strong></td>
                                        <td>{{ $transfer->toLocation->name ?? 'Almacén' }}</td>
                                        <td>
                                            <ul class="mb-0 ps-3">
                                                @foreach ($transfer->items as $item)
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
                                        <td>{{ $transfer->created_at->format('d/m/Y H:i') }}</td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <form method="POST" action="{{ route('technician.returns.accept', $transfer) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success-st btn-sm">Aceptar</button>
                                                </form>
                                                <form method="POST" action="{{ route('technician.returns.reject', $transfer) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Rechazar</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Lateral --}}
        <div class="col-12 col-xxl-4">
            <div class="st-card p-3 mb-4">
                <h5 class="card-title mb-3">Buscar destinatario</h5>

                <form method="GET" action="{{ route('technician.transfers') }}" class="mb-3">
                    <div class="input-group input-group-sm">
                        <input type="text" name="recipient_search" class="form-control" value="{{ $recipientSearch }}" placeholder="Nombre del técnico">
                        <button class="btn btn-outline-secondary" type="submit">Buscar</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('technician.transfers.send') }}" id="transfer-send-form" data-total-units="{{ $cartSummary['total_units'] }}">
                    @csrf
                    <div class="mb-3">
                        <h6 class="st-muted text-uppercase small mb-2">Selecciona un técnico</h6>
                        @if ($recipientTechnicians->isEmpty())
                            <p class="st-muted small mb-0">No se encontraron técnicos con ese criterio.</p>
                        @else
                            <div class="list-group">
                                @foreach ($recipientTechnicians as $recipient)
                                    <label class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                                        <input
                                            class="form-check-input me-1"
                                            type="radio"
                                            name="technician_id"
                                            value="{{ $recipient->id }}"
                                            data-recipient-name="{{ $recipient->name }}"
                                            data-recipient-email="{{ $recipient->email }}"
                                            data-recipient-code="{{ $recipient->tech_code }}"
                                            required
                                        >
                                        <span>
                                            {{ $recipient->name }}
                                            @if ($recipient->tech_code)
                                                <small class="st-muted">({{ $recipient->tech_code }})</small>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <button type="submit" class="btn btn-st w-100" {{ $disableSend ? 'disabled' : '' }}>
                        Transferir {{ $cartSummary['total_units'] }} {{ $cartSummary['total_units'] == 1 ? 'elemento' : 'elementos' }}
                    </button>
                </form>
            </div>

            <div class="st-card p-3">
                <h5 class="card-title mb-3">Resumen rápido</h5>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><strong>Ubicación:</strong> {{ $location->name }}</li>
                    <li class="mb-2"><strong>Materiales no serializados:</strong> {{ $nonSerializedInventory->sum('quantity') }} uds</li>
                    <li class="mb-2"><strong>Series disponibles:</strong> {{ $availableSerials->count() }}</li>
                    <li><strong>En lista:</strong> {{ $cartSummary['total_units'] }} unidades</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Recibidas / Historial --}}
    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="st-card p-3">
                <h5 class="card-title mb-3">Transferencias recibidas (pendientes)</h5>

                @if ($pendingTransfers->isEmpty())
                    <p class="st-muted mb-0">No tienes transferencias pendientes.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Orden</th>
                                    <th>Origen</th>
                                    <th>Materiales</th>
                                    <th>Fecha</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pendingTransfers as $transfer)
                                    <tr>
                                        <td><strong>{{ $transfer->order_number }}</strong></td>
                                        <td>{{ $transfer->fromLocation->name ?? 'Sin origen' }}</td>
                                        <td>
                                            <ul class="mb-0 ps-3">
                                                @foreach ($transfer->items as $item)
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
                                        <td>{{ $transfer->created_at->format('d/m/Y H:i') }}</td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <form method="POST" action="{{ route('technician.transfers.accept', $transfer) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success-st btn-sm">Aceptar</button>
                                                </form>
                                                <form method="POST" action="{{ route('technician.transfers.reject', $transfer) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Rechazar</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="st-card p-3 mb-4">
                <h5 class="card-title mb-3">Historial de transferencias</h5>
                @if ($recentTransferHistory->isEmpty())
                    <p class="st-muted mb-0">Sin transferencias procesadas recientemente.</p>
                @else
                    <div class="list-group">
                        @foreach ($recentTransferHistory as $transfer)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold">{{ $transfer->order_number }}</span>
                                    <span class="badge badge-primary-soft">Transferencia</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="st-muted">
                                        {{ $transfer->updated_at?->format('d/m/Y H:i') ?? $transfer->created_at->format('d/m/Y H:i') }}
                                    </small>
                                    <span class="badge {{ $transfer->status === 'accepted' ? 'badge-success-soft' : 'badge-danger-soft' }}">
                                        {{ $transfer->status === 'accepted' ? 'Aceptada' : 'Rechazada' }}
                                    </span>
                                </div>
                                <ul class="mb-0 mt-2 ps-3 small">
                                    @foreach ($transfer->items as $item)
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
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="st-card p-3">
                <h5 class="card-title mb-3">Historial de devoluciones</h5>
                @if ($recentReturnHistory->isEmpty())
                    <p class="st-muted mb-0">Sin devoluciones procesadas recientemente.</p>
                @else
                    <div class="list-group">
                        @foreach ($recentReturnHistory as $transfer)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold">{{ $transfer->order_number }}</span>
                                    <span class="badge badge-info-soft">Devolución</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="st-muted">
                                        {{ $transfer->updated_at?->format('d/m/Y H:i') ?? $transfer->created_at->format('d/m/Y H:i') }}
                                    </small>
                                    <span class="badge {{ $transfer->status === 'accepted' ? 'badge-success-soft' : 'badge-danger-soft' }}">
                                        {{ $transfer->status === 'accepted' ? 'Aceptada' : 'Rechazada' }}
                                    </span>
                                </div>
                                <ul class="mb-0 mt-2 ps-3 small">
                                    @foreach ($transfer->items as $item)
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
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('transfer-send-form');
            if (!form) return;

            form.addEventListener('submit', function (event) {
                const selected = form.querySelector('input[name="technician_id"]:checked');
                if (!selected) return;

                const totalUnits = Number(form.dataset.totalUnits || 0);
                const name = selected.dataset.recipientName || 'técnico';
                const code = selected.dataset.recipientCode ? ` (${selected.dataset.recipientCode})` : '';
                const email = selected.dataset.recipientEmail ? ` - ${selected.dataset.recipientEmail}` : '';

                const message =
                    `Estás a punto de transferir ${totalUnits} ${totalUnits === 1 ? 'elemento' : 'elementos'} a ${name}${code}${email}.\n\n¿Deseas continuar?`;

                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });
    </script>
@endpush
