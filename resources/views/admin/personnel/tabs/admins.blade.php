@php
    $canManageAdmins = $permissions['canManageAdmins'] ?? false;
    $canViewMovements = $permissions['canViewMovements'] ?? false;
    $isSuperAdmin = (auth()->user()->role ?? null) === 'super_admin';
@endphp

<div class="row g-4">
    <div class="col-lg-9">
        <div class="card st-card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Administradores</h5>

                @if ($admins->isEmpty())
                    <p class="st-muted mb-0">No hay administradores registrados.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Correo electrónico</th>
                                    <th class="justify-content"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($admins as $admin)
                                    <tr>
                                        <td>{{ $admin->name }}</td>
                                        <td>{{ $admin->email }}</td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content gap-2">
                                                @if ($canViewMovements && $isSuperAdmin)
                                                    <a href="{{ route('admin.personnel.movements', $admin) }}" class="btn btn-sm btn-soft-st">
                                                        <i class="bi bi-eye me-1"></i>Movimientos
                                                    </a>
                                                @endif
                                                @if ($canManageAdmins)
                                                    <a href="{{ route('admin.personnel', ['tab' => 'admins', 'edit_admin' => $admin->id]) }}" class="btn btn-sm btn-st">
                                                        <i class="bi bi-pencil-square me-1"></i>Editar
                                                    </a>
                                                    <form method="POST" class="d-inline" action="{{ route('admin.staff.destroy', ['staff' => $admin->id, 'tab' => 'admins']) }}" onsubmit="return confirm('¿Seguro que deseas eliminar a este administrador?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-danger-st">
                                                            <i class="bi bi-trash me-1"></i>Eliminar
                                                        </button>
                                                    </form>
                                                @elseif (! $canViewMovements || ! $isSuperAdmin)
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

    <div class="col-lg-3">
        @if ($canManageAdmins)
            <div class="card st-card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Crear administrador</h5>
                    <form method="POST" action="{{ route('admin.staff.store', ['tab' => 'admins']) }}">
                        @csrf
                        <input type="hidden" name="role" value="admin">
                        <div class="mb-3">
                            <label for="admin_name" class="form-label">Nombre completo</label>
                            <input type="text" id="admin_name" name="name" class="form-control @error('name', 'createStaff') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name', 'createStaff')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="admin_email" class="form-label">Correo electrónico</label>
                            <input type="email" id="admin_email" name="email" class="form-control @error('email', 'createStaff') is-invalid @enderror" value="{{ old('email') }}" required>
                            @error('email', 'createStaff')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="admin_password" class="form-label">Contraseña</label>
                            <input type="password" id="admin_password" name="password" class="form-control @error('password', 'createStaff') is-invalid @enderror" required>
                            @error('password', 'createStaff')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="admin_password_confirmation" class="form-label">Confirmar contraseña</label>
                            <input type="password" id="admin_password_confirmation" name="password_confirmation" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-st w-100"><i class="bi bi-plus-circle me-1"></i>Crear administrador</button>
                    </form>
                </div>
            </div>

            @if ($editingAdmin)
                <div class="card st-card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Editar administrador</h5>
                            <a href="{{ route('admin.personnel', ['tab' => 'admins']) }}" class="btn btn-sm btn-danger-st"><i class="bi bi-x-circle me-1"></i>Cancelar</a>
                        </div>
                        <form method="POST" action="{{ route('admin.staff.update', ['staff' => $editingAdmin->id, 'tab' => 'admins']) }}">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label for="edit_admin_name" class="form-label">Nombre completo</label>
                                <input type="text" id="edit_admin_name" name="name" class="form-control @error('name', 'updateStaff') is-invalid @enderror" value="{{ old('name', $editingAdmin->name) }}" required>
                                @error('name', 'updateStaff')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="edit_admin_email" class="form-label">Correo electrónico</label>
                                <input type="email" id="edit_admin_email" name="email" class="form-control @error('email', 'updateStaff') is-invalid @enderror" value="{{ old('email', $editingAdmin->email) }}" required>
                                @error('email', 'updateStaff')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="edit_admin_password" class="form-label">Contraseña (opcional)</label>
                                <input type="password" id="edit_admin_password" name="password" class="form-control @error('password', 'updateStaff') is-invalid @enderror" placeholder="Déjalo vacío para mantener la actual">
                                @error('password', 'updateStaff')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="edit_admin_password_confirmation" class="form-label">Confirmar contraseña</label>
                                <input type="password" id="edit_admin_password_confirmation" name="password_confirmation" class="form-control" placeholder="Requerido solo si cambias la contraseña">
                            </div>
                            <div class="mb-3">
                                <label for="edit_admin_role" class="form-label">Rol</label>
                                <select id="edit_admin_role" name="role" class="form-select @error('role', 'updateStaff') is-invalid @enderror">
                                    <option value="admin" @selected(old('role', $editingAdmin->role) === 'admin')>Administrador</option>
                                    <option value="logistics" @selected(old('role', $editingAdmin->role) === 'logistics')>Logística</option>
                                    <option value="super_admin" @selected(old('role', $editingAdmin->role) === 'super_admin')>Superadministrador</option>
                                </select>
                                @error('role', 'updateStaff')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-success-st w-100"><i class="bi bi-check-circle me-1"></i>Actualizar administrador</button>
                        </form>
                    </div>
                </div>
            @endif
        @else
            <div class="alert alert-warning">
                Solo un superadministrador puede gestionar administradores.
            </div>
        @endif
    </div>
</div>
