@php
    $canManageLogistics = $permissions['canManageLogistics'] ?? false;
    $canViewMovements = $permissions['canViewMovements'] ?? false;
    $isSuperAdmin = (auth()->user()->role ?? null) === 'super_admin';
@endphp

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Personal de logistica</h5>

                @if ($logistics->isEmpty())
                    <p class="text-muted mb-0">No hay usuarios de logistica registrados.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Correo</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($logistics as $logistic)
                                    <tr>
                                        @php
                                            $stockLocation = $logistic->stockLocation;
                                            $logisticsQuantity = $stockLocation?->inventories->sum('quantity') ?? 0;
                                            $logisticsSerials = $stockLocation?->materialSerials->count() ?? 0;
                                            $logisticsHasStock = ($logisticsQuantity > 0) || ($logisticsSerials > 0);
                                            $logisticsStockSummary = $logisticsQuantity + $logisticsSerials;
                                        @endphp
                                        <td>
                                            {{ $logistic->name }}
                                            @if ($logisticsStockSummary > 0)
                                                <span class="badge bg-info ms-2">Stock: {{ $logisticsStockSummary }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $logistic->email }}</td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                @if ($canViewMovements)
                                                    <a href="{{ route('admin.personnel.movements', $logistic) }}" class="btn btn-sm btn-outline-secondary">
                                                        Ver movimientos
                                                    </a>
                                                @endif
                                                @if ($canManageLogistics)
                                                    <a href="{{ route('admin.personnel', ['tab' => 'logistics', 'edit_logistics' => $logistic->id]) }}" class="btn btn-sm btn-outline-primary">
                                                        Editar
                                                    </a>
                                                    <form method="POST" class="d-inline" action="{{ route('admin.staff.destroy', ['staff' => $logistic->id, 'tab' => 'logistics']) }}" onsubmit="return confirm('Seguro que deseas eliminar este usuario de logistica?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" {{ $logisticsHasStock ? 'disabled' : '' }} @if ($logisticsHasStock) title="Vacía el stock antes de eliminar este usuario de logistica." @endif>
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                    @if ($logisticsHasStock)
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
        @if ($canManageLogistics)
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Crear usuario de logistica</h5>
                    <form method="POST" action="{{ route('admin.staff.store', ['tab' => 'logistics']) }}">
                        @csrf
                        <input type="hidden" name="role" value="logistics">
                        <div class="mb-3">
                            <label for="logistics_name" class="form-label">Nombre completo</label>
                            <input type="text" id="logistics_name" name="name" class="form-control @error('name', 'createStaff') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name', 'createStaff')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="logistics_email" class="form-label">Correo electronico</label>
                            <input type="email" id="logistics_email" name="email" class="form-control @error('email', 'createStaff') is-invalid @enderror" value="{{ old('email') }}" required>
                            @error('email', 'createStaff')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="logistics_password" class="form-label">Contrasena</label>
                            <input type="password" id="logistics_password" name="password" class="form-control @error('password', 'createStaff') is-invalid @enderror" required>
                            @error('password', 'createStaff')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="logistics_password_confirmation" class="form-label">Confirmar contrasena</label>
                            <input type="password" id="logistics_password_confirmation" name="password_confirmation" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Crear usuario</button>
                    </form>
                </div>
            </div>

            @if ($editingLogistics)
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Editar usuario de logistica</h5>
                            <a href="{{ route('admin.personnel', ['tab' => 'logistics']) }}" class="btn btn-sm btn-outline-secondary">Cancelar</a>
                        </div>
                        <form method="POST" action="{{ route('admin.staff.update', ['staff' => $editingLogistics->id, 'tab' => 'logistics']) }}">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label for="edit_logistics_name" class="form-label">Nombre completo</label>
                                <input type="text" id="edit_logistics_name" name="name" class="form-control @error('name', 'updateStaff') is-invalid @enderror" value="{{ old('name', $editingLogistics->name) }}" required>
                                @error('name', 'updateStaff')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="edit_logistics_email" class="form-label">Correo electronico</label>
                                <input type="email" id="edit_logistics_email" name="email" class="form-control @error('email', 'updateStaff') is-invalid @enderror" value="{{ old('email', $editingLogistics->email) }}" required>
                                @error('email', 'updateStaff')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="edit_logistics_password" class="form-label">Contrasena (opcional)</label>
                                <input type="password" id="edit_logistics_password" name="password" class="form-control @error('password', 'updateStaff') is-invalid @enderror" placeholder="Deja vacio para mantener la actual">
                                @error('password', 'updateStaff')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="edit_logistics_password_confirmation" class="form-label">Confirmar contrasena</label>
                                <input type="password" id="edit_logistics_password_confirmation" name="password_confirmation" class="form-control" placeholder="Requerido solo si cambias la contrasena">
                            </div>
                            @if ($isSuperAdmin)
                                <div class="mb-3">
                                    <label for="edit_logistics_role" class="form-label">Rol</label>
                                    <select id="edit_logistics_role" name="role" class="form-select @error('role', 'updateStaff') is-invalid @enderror">
                                        <option value="logistics" @selected(old('role', $editingLogistics->role) === 'logistics')>Logistica</option>
                                        <option value="admin" @selected(old('role', $editingLogistics->role) === 'admin')>Administrador</option>
                                    </select>
                                    @error('role', 'updateStaff')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @else
                                <input type="hidden" name="role" value="logistics">
                            @endif
                            <button type="submit" class="btn btn-success w-100">Actualizar usuario</button>
                        </form>
                    </div>
                </div>
            @endif
        @else
            <div class="alert alert-warning">
                No cuentas con permisos para gestionar personal de logistica.
            </div>
        @endif
    </div>
</div>

