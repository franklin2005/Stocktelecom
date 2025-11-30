<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\Staff\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function __construct(
        protected StaffService $staffService
    ) {}

    /**
     * Redireccionar a la pestaña de personal logístico por defecto.
     */
    public function index(Request $request): RedirectResponse
    {
        $params = $request->query();

        if (! isset($params['tab'])) {
            $params['tab'] = 'logistics';
        }

        return redirect()->route('admin.personnel', $params);
    }

    /**
     * Crear un nuevo usuario de administrador o logística (staff user).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('createStaff', [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'role'     => ['required', Rule::in(['admin', 'logistics', 'super_admin'])],
        ], [
            'password.required'  => 'La contraseña es obligatoria.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min'       => 'La contraseña debe tener al menos :min caracteres.',
            'password.min.string'=> 'La contraseña debe tener al menos :min caracteres.',
        ]);

        $staff = $this->staffService->createStaff($validated, $request->user());

        return redirect()
            ->route('admin.personnel', ['tab' => $this->staffService->tabForRole($staff->role)])
            ->with('status', 'Usuario creado correctamente.');
    }

    /**
     * Actualizar un usuario de administrador o logística.
     */
    public function update(Request $request, User $staff): RedirectResponse
    {
        $validated = $request->validateWithBag('updateStaff', [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'password' => ['nullable', 'confirmed', 'min:8'],
            'role'     => ['required', Rule::in(['admin', 'logistics', 'super_admin'])],
        ], [
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min'       => 'La contraseña debe tener al menos :min caracteres.',
            'password.min.string'=> 'La contraseña debe tener al menos :min caracteres.',
        ]);

        $updatedStaff = $this->staffService->updateStaff($staff, $validated, $request->user());

        return redirect()
            ->route('admin.personnel', ['tab' => $this->staffService->tabForRole($updatedStaff->role)])
            ->with('status', 'Usuario actualizado correctamente.');
    }

    /**
     * Eliminar un usuario administrador o logística.
     */
    public function destroy(Request $request, User $staff): RedirectResponse
    {
        $role = $staff->role;

        $this->staffService->deleteStaff($staff, $request->user());

        return redirect()
            ->route('admin.personnel', ['tab' => $this->staffService->tabForRole($role)])
            ->with('status', 'Usuario eliminado correctamente.');
    }
}
