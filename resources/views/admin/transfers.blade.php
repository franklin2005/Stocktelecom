@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-arrow-repeat me-2"></i>Transferencias desde almacén</h1>
            <p class="st-muted mb-0">Selecciona materiales del almacén principal y genera solicitudes para los técnicos.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    @if (session('cart_warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ session('cart_warning') }}
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
            {{-- No serializados --}}
            <div class="st-card p-3 mb-4">
                <h2 class="h5 mb-1">Materiales no serializados</h2>
                <p class="st-muted small mb-3">Selecciona la cantidad que deseas enviar a un técnico.</p>

                @if ($nonSerializedInventory->isEmpty())
                    <p class="st-muted mb-0">No hay materiales no serializados disponibles en el almacén.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th class="text-end">Stock</th>
                                    <th class="text-end">Reservado</th>
                                    <th class="text-end">Disponible</th>
                                    <th class="text-end">Acción</th>
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
                                            <form method="POST" action="{{ route('admin.transfers.cart.add') }}" class="d-inline-flex align-items-center gap-2">
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
                                                <button type="submit" class="btn btn-sm btn-success-st" {{ $available === 0 ? 'disabled' : '' }}>
                                                    <i class="bi bi-plus-circle me-1"></i>Añadir
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
                <h2 class="h5 mb-1">Materiales serializados</h2>
                <p class="st-muted small mb-3">Marca los números de serie que deseas enviar.</p>

                @if ($availableSerials->isEmpty())
                    <p class="st-muted mb-0">No hay números de serie disponibles en el almacén.</p>
                @else
                    @php
                        $serialsByMaterial = $availableSerials->groupBy('material_id');
                        $serializedLookup = $serializedInventory;
                    @endphp
                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text">Nº serie</span>
                        <input type="text" class="form-control" id="serialSearchAdmin" placeholder="Buscar serie…" autocomplete="off">
                    </div>
                    <form method="POST" action="{{ route('admin.transfers.cart.add') }}">
                        @csrf
                        <input type="hidden" name="intent" value="serial">

                        <div class="accordion" id="warehouseSerials">
                            @foreach ($serialsByMaterial as $materialId => $serialGroup)
                                @php
                                    $material = $serialGroup->first()->material;
                                    $inCartCount = $serialGroup->whereIn('id', $serialsInCart)->count();
                                    $availableCount = $serialGroup->count() - $inCartCount;
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
                                    <div id="collapse-{{ $accordionId }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ $accordionId }}" data-bs-parent="#warehouseSerials">
                                        <div class="accordion-body">
                                            <ul class="list-unstyled mb-0" data-serial-list>
                                                @foreach ($serialGroup as $serial)
                                                    @php $inCart = in_array($serial->id, $serialsInCart, true); @endphp
                                                    <li class="mb-2" data-serial-text="{{ strtolower($serial->serial_number) }}">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" value="{{ $serial->id }}" id="serial-{{ $serial->id }}" name="serial_ids[]" {{ $inCart ? 'disabled' : '' }}>
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
                                            <p class="st-muted small mb-0 d-none" data-no-results>Sin resultados para este material.</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-sm btn-st">
                                <i class="bi bi-plus-circle me-1"></i>Añadir seleccionados
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- Carrito --}}
            <div class="st-card p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Lista de transferencia</h2>
                    <form method="POST" action="{{ route('admin.transfers.cart.clear') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-danger-st" {{ empty($cartItems) ? 'disabled' : '' }}>
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Vaciar lista
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
                                    <th class="text-end">Acción</th>
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
                                            <form method="POST" action="{{ route('admin.transfers.cart.remove', $item['key']) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-warning-st">
                                                    <i class="bi bi-x-circle me-1"></i>Quitar
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
        </div>

        {{-- Lateral: selección de técnico y resumen --}}
        <div class="col-12 col-xxl-4">
            <div class="st-card p-3 mb-4">
                <h2 class="h5 mb-3">Seleccionar técnico</h2>

                <form method="GET" action="{{ route('admin.transfers') }}" class="mb-3">
                    <div class="input-group input-group-sm">
                        <input type="text" name="recipient_search" class="form-control" value="{{ $recipientSearch }}" placeholder="Nombre del técnico">
                        <button class="btn btn-soft-st" type="submit"><i class="bi bi-search me-1"></i>Buscar</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.transfers.send') }}" id="admin-transfer-form" data-total-units="{{ $cartSummary['total_units'] }}">
                    @csrf
                    <div class="mb-3">
                        @if ($technicians->isEmpty())
                            <p class="st-muted small mb-0">No se encontraron técnicos con ese criterio.</p>
                        @else
                            <div class="list-group">
                                @foreach ($technicians as $technician)
                                    <label class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                                        <input
                                            class="form-check-input me-1"
                                            type="radio"
                                            name="technician_id"
                                            value="{{ $technician->id }}"
                                            data-recipient-name="{{ $technician->name }}"
                                            data-recipient-email="{{ $technician->email }}"
                                            data-recipient-code="{{ $technician->tech_code }}"
                                            required
                                        >
                                        <span>
                                            {{ $technician->name }}
                                            @if ($technician->tech_code)
                                                <small class="st-muted">({{ $technician->tech_code }})</small>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <button
                        type="submit"
                        class="btn btn-st w-100"
                        {{ empty($cartItems) || $technicians->isEmpty() ? 'disabled' : '' }}
                    >
                        <i class="bi bi-check-circle me-1"></i>Enviar transferencia
                    </button>
                </form>
            </div>

            <div class="st-card p-3">
                <h2 class="h5 mb-3">Resumen rápido</h2>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><strong>Almacén:</strong> {{ $warehouse->name }}</li>
                    <li class="mb-2"><strong>Materiales no serializados:</strong> {{ $nonSerializedInventory->sum('quantity') }} uds</li>
                    <li class="mb-2"><strong>Series disponibles:</strong> {{ $availableSerials->count() }}</li>
                    <li><strong>En lista:</strong> {{ $cartSummary['total_units'] }} unidades</li>
                </ul>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
  const input = document.getElementById('serialSearchAdmin');
  const accordion = document.getElementById('warehouseSerials');
  if (!input || !accordion) return;

  function filter(term) {
    const lists = accordion.querySelectorAll('[data-serial-list]');
    lists.forEach(listEl => {
      const items = Array.from(listEl.querySelectorAll('[data-serial-text]'));
      let visible = 0;
      items.forEach(li => {
        const haystack = (li.getAttribute('data-serial-text') || '').trim();
        const match = !term || haystack.includes(term);
        li.classList.toggle('d-none', !match);
        if (match) visible++;
      });
      const emptyMsg = listEl.parentElement.querySelector('[data-no-results]');
      const noResults = visible === 0;
      listEl.classList.toggle('d-none', noResults);
      if (emptyMsg) emptyMsg.classList.toggle('d-none', !noResults);
    });
  }

  input.addEventListener('input', () => {
    filter(input.value.trim().toLowerCase());
  });
})();
</script>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('admin-transfer-form');
    if (!form) return;

    form.addEventListener('submit', function (event) {
        const selected = form.querySelector('input[name="technician_id"]:checked');
        if (!selected) return;

        const totalUnits = Number(form.dataset.totalUnits || 0);
        const name = selected.dataset.recipientName || 'técnico';
        const code = selected.dataset.recipientCode ? ` (${selected.dataset.recipientCode})` : '';
        const email = selected.dataset.recipientEmail ? ` - ${selected.dataset.recipientEmail}` : '';

        const message =
`Estás a punto de transferir ${totalUnits} ${totalUnits === 1 ? 'elemento' : 'elementos'} a ${name}${code}${email}.
¿Deseas continuar?`;

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});
</script>
@endpush
