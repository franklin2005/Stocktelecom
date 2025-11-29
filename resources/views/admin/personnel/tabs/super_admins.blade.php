@php
    $canManageSuperAdmins = $permissions['canManageSuperAdmins'] ?? false;
    $canViewMovements = $permissions['canViewMovements'] ?? false;
@endphp

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card st-card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Superadministradores</h5>

                @if ($superAdmins->isEmpty())
                    <p class="st-muted mb-0">No hay superadministradores registrados.</p>
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
                                @foreach ($superAdmins as $superAdmin)
                                    <tr>
                                        <td>{{ $superAdmin->name }}</td>
                                        <td>{{ $superAdmin->email }}</td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                @if ($canViewMovements && $canManageSuperAdmins)
                                                    <a href="{{ route('admin.personnel.movements', $superAdmin) }}" class="btn btn-sm btn-soft-st">
                                                        <i class="bi bi-eye me-1"></i>Ver movimientos
                                                    </a>
                                                @endif
                                                @if ($canManageSuperAdmins)
                                                    <a href="{{ route('admin.personnel', ['tab' => 'super_admins', 'edit_super_admin' => $superAdmin->id]) }}" class="btn btn-sm btn-st">
                                                        <i class="bi bi-pencil-square me-1"></i>Editar
                                                    </a>
                                                    <form method="POST" class="d-inline" action="{{ route('admin.staff.destroy', ['staff' => $superAdmin->id, 'tab' => 'super_admins']) }}" onsubmit="return confirm('¿Seguro que deseas eliminar a este superadministrador?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-danger-st">
                                                            <i class="bi bi-trash me-1"></i>Eliminar
                                                        </button>
                                                    </form>
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
        @if ($canManageSuperAdmins)
            <div class="card st-card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Crear superadministrador</h5>
                    <form method="POST" action="{{ route('admin.staff.store', ['tab' => 'super_admins']) }}">
                        @csrf
                        <input type="hidden" name="role" value="super_admin">
                        <div class="mb-3">
                            <label for="super_admin_name" class="form-label">Nombre completo</label>
                            <input type="text" id="super_admin_name" name="name" class="form-control @error('name', 'createStaff') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name', 'createStaff')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="super_admin_email" class="form-label">Correo electrónico</label>
                            <input type="email" id="super_admin_email" name="email" class="form-control @error('email', 'createStaff') is-invalid @enderror" value="{{ old('email') }}" required>
                            @error('email', 'createStaff')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="super_admin_password" class="form-label">Contraseña</label>
                            <input type="password" id="super_admin_password" name="password" class="form-control @error('password', 'createStaff') is-invalid @enderror" required>
                            @error('password', 'createStaff')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="super_admin_password_confirmation" class="form-label">Confirmar contraseña</label>
                            <input type="password" id="super_admin_password_confirmation" name="password_confirmation" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-st w-100"><i class="bi bi-plus-circle me-1"></i>Crear superadministrador</button>
                    </form>
                </div>
            </div>

            @if ($editingSuperAdmin)
                <div class="card st-card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Editar superadministrador</h5>
                            <a href="{{ route('admin.personnel', ['tab' => 'super_admins']) }}" class="btn btn-sm btn-warning-st"><i class="bi bi-x-circle me-1"></i>Cancelar</a>
                        </div>
                        <form method="POST" action="{{ route('admin.staff.update', ['staff' => $editingSuperAdmin->id, 'tab' => 'super_admins']) }}">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label for="edit_super_admin_name" class="form-label">Nombre completo</label>
                                <input type="text" id="edit_super_admin_name" name="name" class="form-control @error('name', 'updateStaff') is-invalid @enderror" value="{{ old('name', $editingSuperAdmin->name) }}" required>
                                @error('name', 'updateStaff')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="edit_super_admin_email" class="form-label">Correo electrónico</label>
                                <input type="email" id="edit_super_admin_email" name="email" class="form-control @error('email', 'updateStaff') is-invalid @enderror" value="{{ old('email', $editingSuperAdmin->email) }}" required>
                                @error('email', 'updateStaff')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="edit_super_admin_password" class="form-label">Contraseña (opcional)</label>
                                <input type="password" id="edit_super_admin_password" name="password" class="form-control @error('password', 'updateStaff') is-invalid @enderror" placeholder="Déjalo vacío para mantener la actual">
                                @error('password', 'updateStaff')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="edit_super_admin_password_confirmation" class="form-label">Confirmar contraseña</label>
                                <input type="password" id="edit_super_admin_password_confirmation" name="password_confirmation" class="form-control" placeholder="Requerido solo si cambias la contraseña">
                            </div>
                            <div class="mb-3">
                                <label for="edit_super_admin_role" class="form-label">Rol</label>
                                <select id="edit_super_admin_role" name="role" class="form-select @error('role', 'updateStaff') is-invalid @enderror">
                                    <option value="super_admin" @selected(old('role', $editingSuperAdmin->role) === 'super_admin')>Superadministrador</option>
                                    <option value="admin" @selected(old('role', $editingSuperAdmin->role) === 'admin')>Administrador</option>
                                </select>
                                @error('role', 'updateStaff')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-success-st w-100"><i class="bi bi-check-circle me-1"></i>Actualizar superadministrador</button>
                        </form>
                    </div>
                </div>
            @endif
        @else
            <div class="alert alert-warning">
                Solo un superadministrador puede gestionar superadministradores.
            </div>
        @endif
    </div>
</div>
