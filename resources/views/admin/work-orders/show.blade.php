@extends('layouts.app')

@section('content')
    @php
        $statusMap = [
            'open'      => ['label' => 'Abierta',   'class' => 'badge-warning-soft'],
            'confirmed' => ['label' => 'Confirmada','class' => 'badge-success-soft'],
            'cancelled' => ['label' => 'Cancelada', 'class' => 'badge-danger-soft'],
        ];
        $statusLabel = $statusMap[$workOrder->status] ?? ['label' => ucfirst($workOrder->status), 'class' => 'badge-soft'];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Orden #{{ $workOrder->order_number }}</h1>
            <p class="st-muted mb-0">
                Técnico: {{ $workOrder->technician_name }} ({{ $workOrder->technician_code }}) ·
                Creada el {{ $workOrder->created_at?->format('d/m/Y H:i') }}
            </p>
        </div>
        <span class="badge {{ $statusLabel['class'] }} fs-6 px-3">{{ $statusLabel['label'] }}</span>
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
        $quantityItems = $workOrder->items->whereNull('material_serial_id');
        $serialItems   = $workOrder->items->whereNotNull('material_serial_id');
        $canEdit       = in_array(auth()->user()->role, ['admin', 'super_admin'], true);
    @endphp

    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card st-card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Detalle de la orden</h5>
                    <dl class="row mb-0">
                        <dt class="col-5 col-sm-4 st-muted">Estado</dt>
                        <dd class="col-7 col-sm-8">{{ $statusLabel['label'] }}</dd>

                        <dt class="col-5 col-sm-4 st-muted">Creada</dt>
                        <dd class="col-7 col-sm-8">{{ $workOrder->created_at?->format('d/m/Y H:i') }}</dd>

                        <dt class="col-5 col-sm-4 st-muted">Actualizada</dt>
                        <dd class="col-7 col-sm-8">{{ $workOrder->updated_at?->format('d/m/Y H:i') }}</dd>

                        <dt class="col-5 col-sm-4 st-muted">Materiales</dt>
                        <dd class="col-7 col-sm-8">{{ $workOrder->items->count() }}</dd>

                        <dt class="col-5 col-sm-4 st-muted">Notas</dt>
                        <dd class="col-7 col-sm-8">
                            @if ($workOrder->notes)
                                {{ $workOrder->notes }}<br>
                                <small class="st-muted">Registrado por {{ $workOrder->notes_author_name ?? 'Desconocido' }}</small>
                            @else
                                <span class="st-muted">—</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card st-card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Actualizar información</h5>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('admin.work-orders.update', $workOrder) }}">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label for="status" class="form-label">Estado</label>
                                <select id="status" name="status" class="form-select">
                                    @foreach ($statusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($workOrder->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <small class="st-muted d-block mt-1">Cambiar el estado no ajusta el inventario automáticamente.</small>
                            </div>
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notas</label>
                                <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $workOrder->notes) }}</textarea>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-st">Guardar cambios</button>
                            </div>
                        </form>
                    @else
                        <p class="st-muted mb-0">Solo los administradores pueden modificar esta orden.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card st-card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Materiales por cantidad</h5>
            @if ($quantityItems->isEmpty())
                <p class="st-muted mb-0">No se registraron materiales por cantidad.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th class="text-end">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($quantityItems as $item)
                                <tr>
                                    <td>
                                        {{ ucfirst($item->material->type ?? 'Material') }}
                                        @if ($item->material?->model)
                                            - {{ $item->material->model }}
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $item->quantity }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card st-card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">Materiales serializados</h5>
            @if ($serialItems->isEmpty())
                <p class="st-muted mb-0">No se registraron números de serie en esta orden.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Serie</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($serialItems as $item)
                                <tr>
                                    <td>
                                        {{ ucfirst($item->material->type ?? 'Material') }}
                                        @if ($item->material?->model)
                                            - {{ $item->material->model }}
                                        @endif
                                    </td>
                                    <td>{{ $item->serial?->serial_number }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
