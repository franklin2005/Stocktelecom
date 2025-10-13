<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockLocation;
use App\Models\User;
use App\Models\UserActionLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    /**
     * Redirects to the personnel dashboard preserving filters.
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
     * Create a new administrator or logistics user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('createStaff', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'logistics', 'super_admin'])],
        ]);

        $actorId = auth()->id();
        $roleLabel = $this->roleLabel($validated['role']);
        $currentUserRole = $request->user()->role;

        if (in_array($validated['role'], ['admin', 'super_admin'], true) && $currentUserRole !== 'super_admin') {
            abort(403);
        }

        DB::transaction(function () use ($validated, $actorId, $roleLabel) {
            $staffUser = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'tech_code' => null,
            ]);

            if ($validated['role'] === 'logistics') {
                StockLocation::updateOrCreate(
                    [
                        'location_type' => 'user',
                        'ref_id' => $staffUser->id,
                    ],
                    [
                        'name' => 'Stock de ' . $staffUser->name,
                    ]
                );
            }

            UserActionLog::create([
                'actor_id' => $actorId,
                'target_id' => $staffUser->id,
                'action' => 'created',
                'details' => 'Creacion de usuario ' . $roleLabel . ': ' . $staffUser->name . ' (' . $staffUser->email . ')',
            ]);
        });

        return redirect()
            ->route('admin.personnel', ['tab' => $this->tabForRole($validated['role'])])
            ->with('status', 'Usuario creado correctamente.');
    }

    /**
     * Update an administrator or logistics user.
     */
    public function update(Request $request, User $staff): RedirectResponse
    {
        if (! in_array($staff->role, ['admin', 'logistics', 'super_admin'], true)) {
            abort(404);
        }

        $previousRole = $staff->role;
        $actorId = auth()->id();
        $currentUserRole = $request->user()->role;
        $original = $staff->only(['name', 'email', 'role']);
        $fieldsChanged = [];

        $validated = $request->validateWithBag('updateStaff', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'password' => ['nullable', 'confirmed', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'logistics', 'super_admin'])],
        ]);

        if (in_array($staff->role, ['admin', 'super_admin'], true) && $currentUserRole !== 'super_admin') {
            abort(403);
        }

        if (in_array($validated['role'], ['admin', 'super_admin'], true) && $currentUserRole !== 'super_admin') {
            abort(403);
        }

        if ($staff->role === 'admin' && $validated['role'] !== 'admin') {
            $otherAdmins = User::admins()
                ->where('id', '!=', $staff->id)
                ->count();

            if ($otherAdmins === 0) {
                throw ValidationException::withMessages([
                    'role' => 'Debe quedar al menos un administrador activo en el sistema.',
                ])->errorBag('updateStaff')->redirectTo(
                    route('admin.personnel', ['tab' => 'admins', 'edit_admin' => $staff->id])
                );
            }
        }

        if ($staff->role === 'super_admin' && $validated['role'] !== 'super_admin') {
            $otherSuperAdmins = User::superAdmins()
                ->where('id', '!=', $staff->id)
                ->count();

            if ($otherSuperAdmins === 0) {
                throw ValidationException::withMessages([
                    'role' => 'Debe quedar al menos un super administrador activo en el sistema.',
                ])->errorBag('updateStaff')->redirectTo(
                    route('admin.personnel', ['tab' => 'super_admins', 'edit_super_admin' => $staff->id])
                );
            }
        }

        if ($original['name'] !== $validated['name']) {
            $fieldsChanged[] = 'nombre';
        }

        if ($original['email'] !== $validated['email']) {
            $fieldsChanged[] = 'correo';
        }

        if ($original['role'] !== $validated['role']) {
            $fieldsChanged[] = 'rol';
        }

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
            $fieldsChanged[] = 'contrasena';
        }

        $newRoleLabel = $this->roleLabel($validated['role']);

        DB::transaction(function () use ($staff, $payload, $previousRole, $actorId, $fieldsChanged, $newRoleLabel) {
            $staff->update($payload);

            if ($payload['role'] === 'logistics') {
                StockLocation::updateOrCreate(
                    [
                        'location_type' => 'user',
                        'ref_id' => $staff->id,
                    ],
                    [
                        'name' => 'Stock de ' . $payload['name'],
                    ]
                );
            } elseif ($previousRole === 'logistics') {
                $staff->stockLocation()->delete();
            }

            UserActionLog::create([
                'actor_id' => $actorId,
                'target_id' => $staff->id,
                'action' => 'updated',
                'details' => $fieldsChanged
                    ? 'Actualizacion de usuario (' . $newRoleLabel . '). Campos modificados: ' . implode(', ', $fieldsChanged)
                    : 'Actualizacion de usuario sin cambios en los datos principales.',
            ]);
        });

        return redirect()
            ->route('admin.personnel', ['tab' => $this->tabForRole($payload['role'])])
            ->with('status', 'Usuario actualizado correctamente.');
    }

    /**
     * Delete an administrator or logistics user.
     */
    public function destroy(User $staff): RedirectResponse
    {
        if (! in_array($staff->role, ['admin', 'logistics', 'super_admin'], true)) {
            abort(404);
        }

        if (in_array($staff->role, ['admin', 'super_admin'], true) && $request->user()->role !== 'super_admin') {
            abort(403);
        }

        if (auth()->id() === $staff->id) {
            throw ValidationException::withMessages([
                'general' => 'No puedes eliminar tu propio usuario.',
            ])->errorBag('deleteStaff')->redirectTo(
                route('admin.personnel', ['tab' => $this->tabForRole($staff->role)])
            );
        }

        if ($staff->role === 'admin') {
            $otherAdmins = User::admins()
                ->where('id', '!=', $staff->id)
                ->count();

            if ($otherAdmins === 0) {
                throw ValidationException::withMessages([
                    'general' => 'Debe quedar al menos un administrador activo en el sistema.',
                ])->errorBag('deleteStaff')->redirectTo(
                    route('admin.personnel', ['tab' => 'admins'])
                );
            }
        }

        if ($staff->role === 'super_admin') {
            $otherSuperAdmins = User::superAdmins()
                ->where('id', '!=', $staff->id)
                ->count();

            if ($otherSuperAdmins === 0) {
                throw ValidationException::withMessages([
                    'general' => 'Debe quedar al menos un super administrador activo en el sistema.',
                ])->errorBag('deleteStaff')->redirectTo(
                    route('admin.personnel', ['tab' => 'super_admins'])
                );
            }
        }

        $actorId = auth()->id();
        $staffName = $staff->name;
        $staffEmail = $staff->email;
        $roleLabel = $this->roleLabel($staff->role);
        $redirectTab = $this->tabForRole($staff->role);

        DB::transaction(function () use ($staff, $actorId, $staffName, $staffEmail, $roleLabel) {
            UserActionLog::create([
                'actor_id' => $actorId,
                'target_id' => $staff->id,
                'action' => 'deleted',
                'details' => 'Eliminacion de usuario ' . $roleLabel . ': ' . $staffName . ' (' . $staffEmail . ')',
            ]);

            if ($staff->role === 'logistics') {
                $staff->stockLocation()->delete();
            }

            $staff->delete();
        });

        return redirect()
            ->route('admin.personnel', ['tab' => $redirectTab])
            ->with('status', 'Usuario eliminado correctamente.');
    }

    /**
     * Helper to transform role to readable label.
     */
    protected function roleLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'administrador',
            'logistics' => 'logistica',
            'super_admin' => 'super administrador',
            default => $role,
        };
    }

    /**
     * Map role to personnel tab.
     */
    protected function tabForRole(string $role): string
    {
        return match ($role) {
            'technician' => 'technicians',
            'logistics' => 'logistics',
            'admin' => 'admins',
            'super_admin' => 'super_admins',
            default => 'logistics',
        };
    }
}

