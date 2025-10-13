@php
    $canManageTechnicians = $permissions['canManageTechnicians'] ?? false;
    $canViewMovements = $permissions['canViewMovements'] ?? false;
@endphp

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Tecnicos registrados</h5>

                @if ($technicians->isEmpty())
                    <p class="text-muted mb-0">Aun no hay tecnicos registrados.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Correo</th>
                                    <th>Codigo tecnico</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($technicians as $technician)
                                    <tr>
                                        @php
                                            $stockLocation = $technician->stockLocation;
                                            $totalQuantity = $stockLocation?->inventories->sum('quantity') ?? 0;
                                            $totalSerials = $stockLocation?->materialSerials->count() ?? 0;
                                            $hasStock = ($totalQuantity > 0) || ($totalSerials > 0);
                                            $stockSummary = $totalQuantity + $totalSerials;
                                        @endphp
                                        <td>
                                            {{ $technician->name }}
                                            @if ($stockSummary > 0)
                                                <span class="badge bg-info ms-2">
                                                    Stock: {{ $stockSummary }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>{{ $technician->email }}</td>
                                        <td>
                                            <span class="badge bg-secondary">{{ $technician->tech_code ?? 'Pendiente' }}</span>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="{{ route('technicians.stock.overview', $technician) }}" class="btn btn-sm btn-outline-primary">
                                                    Stock
                                                </a>
                                                <a href="{{ route('technicians.transfers.history', $technician) }}" class="btn btn-sm btn-outline-secondary">
                                                    Transferencias
                                                </a>
                                                @if ($canManageTechnicians)
                                                    <a href="{{ route('admin.personnel', ['tab' => 'technicians', 'edit_technician' => $technician->id]) }}" class="btn btn-sm btn-outline-primary">
                                                        Editar
                                                    </a>
                                                    <form method="POST" class="d-inline" action="{{ route('admin.technicians.destroy', ['technician' => $technician->id, 'tab' => 'technicians']) }}" onsubmit="return confirm('Seguro que deseas eliminar este tecnico?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" {{ $hasStock ? 'disabled' : '' }} @if ($hasStock) title="Vacía el stock antes de eliminar este tecnico." @endif>
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                    @if ($hasStock)
                                                        <small class="text-danger d-block">Vacía el stock antes de eliminar.</small>
                                                    @endif
                                                @elseif (! $canViewMovements)
                                                    <span class="text-muted small">Sin permisos</span>
                                                @endif
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
    </div>

    <div class="col-12 col-xl-5">
        @if ($canManageTechnicians)
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Crear tecnico</h5>
                    <form method="POST" action="{{ route('admin.technicians.store', ['tab' => 'technicians']) }}">
                        @csrf
                        <div class="mb-3">
                            <label for="create_name" class="form-label">Nombre completo</label>
                            <input type="text" id="create_name" name="name" class="form-control @error('name', 'createTechnician') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name', 'createTechnician')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="create_email" class="form-label">Correo electronico</label>
                            <input type="email" id="create_email" name="email" class="form-control @error('email', 'createTechnician') is-invalid @enderror" value="{{ old('email') }}" required>
                            @error('email', 'createTechnician')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="create_password" class="form-label">Contrasena</label>
                            <input type="password" id="create_password" name="password" class="form-control @error('password', 'createTechnician') is-invalid @enderror" required>
                            @error('password', 'createTechnician')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="create_password_confirmation" class="form-label">Confirmar contrasena</label>
                            <input type="password" id="create_password_confirmation" name="password_confirmation" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="create_tech_code" class="form-label">Codigo tecnico (opcional)</label>
                            <input type="text" id="create_tech_code" name="tech_code" class="form-control @error('tech_code', 'createTechnician') is-invalid @enderror" value="{{ old('tech_code') }}" placeholder="Se generara automaticamente si lo dejas vacio">
                            @error('tech_code', 'createTechnician')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Crear tecnico</button>
                    </form>
                </div>
            </div>

            @if ($editingTechnician)
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Editar tecnico</h5>
                            <a href="{{ route('admin.personnel', ['tab' => 'technicians']) }}" class="btn btn-sm btn-outline-secondary">Cancelar</a>
                        </div>
                        <form method="POST" action="{{ route('admin.technicians.update', ['technician' => $editingTechnician->id, 'tab' => 'technicians']) }}">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Nombre completo</label>
                                <input type="text" id="edit_name" name="name" class="form-control @error('name', 'updateTechnician') is-invalid @enderror" value="{{ old('name', $editingTechnician->name) }}" required>
                                @error('name', 'updateTechnician')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="edit_email" class="form-label">Correo electronico</label>
                                <input type="email" id="edit_email" name="email" class="form-control @error('email', 'updateTechnician') is-invalid @enderror" value="{{ old('email', $editingTechnician->email) }}" required>
                                @error('email', 'updateTechnician')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="edit_password" class="form-label">Contrasena (opcional)</label>
                                <input type="password" id="edit_password" name="password" class="form-control @error('password', 'updateTechnician') is-invalid @enderror" placeholder="Deja vacio para mantener la actual">
                                @error('password', 'updateTechnician')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="edit_password_confirmation" class="form-label">Confirmar contrasena</label>
                                <input type="password" id="edit_password_confirmation" name="password_confirmation" class="form-control" placeholder="Requerido solo si cambias la contrasena">
                            </div>
                            <div class="mb-3">
                                <label for="edit_tech_code" class="form-label">Codigo tecnico</label>
                                <input type="text" id="edit_tech_code" name="tech_code" class="form-control @error('tech_code', 'updateTechnician') is-invalid @enderror" value="{{ old('tech_code', $editingTechnician->tech_code) }}">
                                @error('tech_code', 'updateTechnician')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-success w-100">Actualizar tecnico</button>
                        </form>
                    </div>
                </div>
            @endif
        @else
            <div class="alert alert-warning">
                No cuentas con permisos para gestionar tecnicos.
            </div>
        @endif
    </div>
</div>

