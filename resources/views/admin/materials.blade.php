@extends('layouts.app')

@section('content')
    @php
        $categoryLabels = [
            'equipment' => 'Equipo',
            'acometida' => 'Acometida',
            'roseta' => 'Roseta',
            'other' => 'Otro',
        ];
    @endphp

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1">Gestión de materiales</h1>
            <p class="st-muted mb-0">Consulta el inventario y realiza ingresos, bajas y asignaciones según permisos.</p>
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
            <strong>Revisa los campos:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card st-card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Inventario en almacén</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Categoría</th>
                                    <th>Tipo</th>
                                    <th>Modelo</th>
                                    <th>Serializado</th>
                                    <th class="text-end">Stock almacén</th>
                                    <th class="text-end">Series disponibles</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($materials as $material)
                                    @php
                                        $warehouseInventory = $material->inventories->first();
                                        $warehouseQuantity = $warehouseInventory?->quantity ?? 0;
                                        $availableSerials = $material->serials ?? collect();
                                    @endphp
                                    <tr>
                                        <td>{{ $categoryLabels[$material->category] ?? ucfirst($material->category) }}</td>
                                        <td>{{ ucfirst($material->type) }}</td>
                                        <td>{{ $material->model ?? '-' }}</td>
                                        <td>
                                            @if ($material->is_serialized)
                                                <span class="badge badge-accent">Sí</span>
                                            @else
                                                <span class="badge badge-soft">No</span>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $warehouseQuantity }}</td>
                                        <td class="text-end">
                                            @if ($material->is_serialized)
                                                {{ $availableSerials->count() }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            @if ($canManageWarehouse)
                {{-- Ingresar stock (no serializado) --}}
                <div class="card st-card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Ingresar stock (no serializado)</h5>
                        <form method="POST" action="{{ route('admin.materials.add-stock') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="material_id_quantity" class="form-label">Material</label>
                                <select id="material_id_quantity" name="material_id" class="form-select" required>
                                    <option value="">Selecciona un material</option>
                                    @foreach ($materials->where('is_serialized', false)->where('is_active', true) as $material)
                                        <option value="{{ $material->id }}" {{ old('material_id') == $material->id ? 'selected' : '' }}>
                                            {{ ucfirst($material->type) }}{{ $material->model ? ' - '.$material->model : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="quantity" class="form-label">Cantidad a ingresar</label>
                                <input type="number" min="1" class="form-control" id="quantity" name="quantity" value="{{ old('quantity') }}">
                            </div>
                            <button type="submit" class="btn btn-st w-100">Registrar ingreso</button>
                        </form>
                    </div>
                </div>

                {{-- Ingresar stock (serializado) --}}
                <div class="card st-card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Ingresar stock (serializado)</h5>
                        <form method="POST" action="{{ route('admin.materials.add-stock') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="material_id_serial" class="form-label">Material</label>
                                <select id="material_id_serial" name="material_id" class="form-select" required>
                                    <option value="">Selecciona un material</option>
                                    @foreach ($materials->where('is_serialized', true)->where('is_active', true) as $material)
                                        <option value="{{ $material->id }}" {{ old('material_id') == $material->id ? 'selected' : '' }}>
                                            {{ ucfirst($material->type) }}{{ $material->model ? ' - '.$material->model : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="serial_numbers" class="form-label">Números de serie</label>
                                <textarea class="form-control" id="serial_numbers" name="serial_numbers" rows="4" placeholder="Uno por línea">{{ old('serial_numbers') }}</textarea>
                                <small class="st-muted">Introduce un número de serie por línea.</small>
                            </div>
                            <button type="submit" class="btn btn-st w-100">Registrar series</button>
                        </form>
                    </div>
                </div>

                {{-- Eliminar stock (no serializado) --}}
                <div class="card st-card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Eliminar stock (no serializado)</h5>
                        <form method="POST" action="{{ route('admin.materials.remove-stock') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="remove_material_id_quantity" class="form-label">Material</label>
                                <select id="remove_material_id_quantity" name="material_id" class="form-select" required>
                                    <option value="">Selecciona un material</option>
                                    @foreach ($materials->where('is_serialized', false)->where('is_active', true) as $material)
                                        @php $quantity = $material->inventories->first()?->quantity ?? 0; @endphp
                                        @if ($quantity > 0)
                                            <option value="{{ $material->id }}">
                                                {{ ucfirst($material->type) }}{{ $material->model ? ' - '.$material->model : '' }} (Stock: {{ $quantity }})
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="remove_quantity" class="form-label">Cantidad a retirar</label>
                                <input type="number" min="1" class="form-control" id="remove_quantity" name="quantity">
                            </div>
                            <button type="submit" class="btn btn-danger-st w-100">Eliminar del almacén</button>
                        </form>
                    </div>
                </div>

                {{-- Eliminar stock (serializado) --}}
                <div class="card st-card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Eliminar stock (serializado)</h5>
                        <form method="POST" action="{{ route('admin.materials.remove-stock') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="remove_material_id_serial" class="form-label">Material</label>
                                <select id="remove_material_id_serial" name="material_id" class="form-select" required>
                                    <option value="">Selecciona un material</option>
                                    @foreach ($materials->where('is_serialized', true)->where('is_active', true) as $material)
                                        @if (($material->serials ?? collect())->isNotEmpty())
                                            <option value="{{ $material->id }}">
                                                {{ ucfirst($material->type) }}{{ $material->model ? ' - '.$material->model : '' }} (Series disponibles: {{ $material->serials->count() }})
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="remove_serial_ids" class="form-label">Series a retirar</label>
                                <select id="remove_serial_ids" name="serial_ids[]" class="form-select" multiple size="6" required>
                                    @foreach ($materials->where('is_serialized', true)->where('is_active', true) as $material)
                                        @if (($material->serials ?? collect())->isNotEmpty())
                                            <optgroup label="{{ ucfirst($material->type) }}{{ $material->model ? ' - '.$material->model : '' }}">
                                                @foreach ($material->serials as $serial)
                                                    <option value="{{ $serial->id }}">
                                                        {{ $serial->serial_number }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach
                                </select>
                                <small class="st-muted">Selecciona cada número de serie que deseas dar de baja.</small>
                            </div>
                            <button type="submit" class="btn btn-danger-st w-100">Eliminar series</button>
                        </form>
                    </div>
                </div>

                {{-- Asignar a técnico (no serializado) --}}
                <div class="card st-card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Asignar a técnico (no serializado)</h5>
                        <form method="POST" action="{{ route('admin.materials.assign') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="assign_technician_id" class="form-label">Técnico</label>
                                <select id="assign_technician_id" name="technician_id" class="form-select" required>
                                    <option value="">Selecciona un técnico</option>
                                    @foreach ($technicians as $technician)
                                        <option value="{{ $technician->id }}" {{ old('technician_id') == $technician->id ? 'selected' : '' }}>
                                            {{ $technician->name }} ({{ $technician->tech_code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="assign_material_id" class="form-label">Material</label>
                                <select id="assign_material_id" name="material_id" class="form-select" required>
                                    <option value="">Selecciona un material</option>
                                    @foreach ($materials->where('is_serialized', false)->where('is_active', true) as $material)
                                        <option value="{{ $material->id }}" {{ old('material_id') == $material->id ? 'selected' : '' }}>
                                            {{ ucfirst($material->type) }}{{ $material->model ? ' - '.$material->model : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="assign_quantity" class="form-label">Cantidad</label>
                                <input type="number" min="1" class="form-control" id="assign_quantity" name="quantity" value="{{ old('quantity') }}">
                            </div>
                            <button type="submit" class="btn btn-success-st w-100">Generar solicitud</button>
                        </form>
                    </div>
                </div>

                {{-- Asignar a técnico (serializado) --}}
                <div class="card st-card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Asignar a técnico (serializado)</h5>
                        <form method="POST" action="{{ route('admin.materials.assign') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="assign_serial_technician_id" class="form-label">Técnico</label>
                                <select id="assign_serial_technician_id" name="technician_id" class="form-select" required>
                                    <option value="">Selecciona un técnico</option>
                                    @foreach ($technicians as $technician)
                                        <option value="{{ $technician->id }}" {{ old('technician_id') == $technician->id ? 'selected' : '' }}>
                                            {{ $technician->name }} ({{ $technician->tech_code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="assign_serial_material_id" class="form-label">Material</label>
                                <select id="assign_serial_material_id" name="material_id" class="form-select" required>
                                    <option value="">Selecciona un material</option>
                                    @foreach ($materials->where('is_serialized', true)->where('is_active', true) as $material)
                                        <option value="{{ $material->id }}" {{ old('material_id') == $material->id ? 'selected' : '' }}>
                                            {{ ucfirst($material->type) }}{{ $material->model ? ' - '.$material->model : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="assign_serial_ids" class="form-label">Series disponibles</label>
                                <select id="assign_serial_ids" name="serial_ids[]" class="form-select" multiple size="6" required>
                                    @foreach ($materials->where('is_serialized', true)->where('is_active', true) as $material)
                                        @if (($material->serials ?? collect())->isNotEmpty())
                                            <optgroup label="{{ ucfirst($material->type) }}{{ $material->model ? ' - '.$material->model : '' }}">
                                                @foreach ($material->serials as $serial)
                                                    <option value="{{ $serial->id }}" @selected(collect(old('serial_ids', []))->contains($serial->id))>
                                                        {{ $serial->serial_number }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach
                                </select>
                                <small class="st-muted">Selecciona solo series del material elegido.</small>
                            </div>
                            <button type="submit" class="btn btn-success-st w-100">Generar solicitud</button>
                        </form>
                    </div>
                </div>
            @else
                <div class="card st-card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Acciones restringidas</h5>
                        <p class="st-muted mb-0">
                            Solo el personal de logística y el superadministrador pueden registrar ingresos, salidas o asignaciones de materiales.
                            Puedes consultar el stock y revisar el histórico, pero sin capacidad de mover inventario desde este módulo.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
