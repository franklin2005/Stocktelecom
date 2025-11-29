@php
    $canManageLogistics = $permissions['canManageLogistics'] ?? false;
    $canViewMovements = $permissions['canViewMovements'] ?? false;
    $isSuperAdmin = (auth()->user()->role ?? null) === 'super_admin';
@endphp

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card st-card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Personal de logística</h5>

                @if ($logistics->isEmpty())
                    <p class="st-muted mb-0">No hay usuarios de logística registrados.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Correo electrónico</th>
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
                                                <span class="badge badge-accent ms-2">Stock total: {{ $logisticsStockSummary }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $logistic->email }}</td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                @if ($canViewMovements)
                                                    <a href="{{ route('admin.personnel.movements', $logistic) }}" class="btn btn-sm btn-soft-st">
                                                        <i class="bi bi-eye me-1"></i>Ver movimientos
                                                    </a>
                                                @endif
                                                @if ($canManageLogistics)
                                                    <a href="{{ route('admin.personnel', ['tab' => 'logistics', 'edit_logistics' => $logistic->id]) }}" class="btn btn-sm btn-st">
                                                        <i class="bi bi-pencil-square me-1"></i>Editar
                                                    </a>
                                                    <form method="POST" class="d-inline" action="{{ route('admin.staff.destroy', ['staff' => $logistic->id, 'tab' => 'logistics']) }}" onsubmit="return confirm('¿Seguro que deseas eliminar a este usuario de logística?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-danger-st"
                                                            {{ $logisticsHasStock ? 'disabled' : '' }}
                                                            @if ($logisticsHasStock) title="Vacía el stock antes de eliminar a este usuario de logística." @endif
                                                        >
                                                            <i class="bi bi-trash me-1"></i>Eliminar
                                                        </button>
                                                    </form>
                                                    @if ($logisticsHasStock)
                                                        <small class="text-danger d-block">Vacía el stock antes de eliminar.</small>
                                                    @endif
                                                @elseif (! $canViewMovements)
                                                    <span class="st-muted small">Sin permisos</span>
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
            <div class="card st-card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Crear usuario de logística</h5>
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
                            <label for="logistics_email" class="form-label">Correo electrónico</label>
                            <input type="email" id="logistics_email" name="email" class="form-control @error('email', 'createStaff') is-invalid @enderror" value="{{ old('email') }}" required>
                            @error('email', 'createStaff')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="logistics_password" class="form-label">Contraseña</label>
                            <input type="password" id="logistics_password" name="password" class="form-control @error('password', 'createStaff') is-invalid @enderror" required>
                            @error('password', 'createStaff')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="logistics_password_confirmation" class="form-label">Confirmar contraseña</label>
                            <input type="password" id="logistics_password_confirmation" name="password_confirmation" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-st w-100"><i class="bi bi-plus-circle me-1"></i>Crear usuario de logística</button>
                    </form>
                </div>
            </div>

            @if ($editingLogistics)
                <div class="card st-card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Editar usuario de logística</h5>
                            <a href="{{ route('admin.personnel', ['tab' => 'logistics']) }}" class="btn btn-sm btn-warning-st"><i class="bi bi-x-circle me-1"></i>Cancelar</a>
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
                                <label for="edit_logistics_email" class="form-label">Correo electrónico</label>
                                <input type="email" id="edit_logistics_email" name="email" class="form-control @error('email', 'updateStaff') is-invalid @enderror" value="{{ old('email', $editingLogistics->email) }}" required>
                                @error('email', 'updateStaff')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="edit_logistics_password" class="form-label">Contraseña (opcional)</label>
                                <input type="password" id="edit_logistics_password" name="password" class="form-control @error('password', 'updateStaff') is-invalid @enderror" placeholder="Déjalo vacío para mantener la actual">
                                @error('password', 'updateStaff')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="edit_logistics_password_confirmation" class="form-label">Confirmar contraseña</label>
                                <input type="password" id="edit_logistics_password_confirmation" name="password_confirmation" class="form-control" placeholder="Requerido solo si cambias la contraseña">
                            </div>
                            @if ($isSuperAdmin)
                                <div class="mb-3">
                                    <label for="edit_logistics_role" class="form-label">Rol</label>
                                    <select id="edit_logistics_role" name="role" class="form-select @error('role', 'updateStaff') is-invalid @enderror">
                                        <option value="logistics" @selected(old('role', $editingLogistics->role) === 'logistics')>Logística</option>
                                        <option value="admin" @selected(old('role', $editingLogistics->role) === 'admin')>Administrador</option>
                                    </select>
                                    @error('role', 'updateStaff')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @else
                                <input type="hidden" name="role" value="logistics">
                            @endif
                            <button type="submit" class="btn btn-success-st w-100"><i class="bi bi-check-circle me-1"></i></i>Actualizar usuario</button>
                        </form>
                    </div>
                </div>
            @endif
        @else
            <div class="alert alert-warning">
                No cuentas con permisos para gestionar personal de logística.
            </div>
        @endif
    </div>
</div>
