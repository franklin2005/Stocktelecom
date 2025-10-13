<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use App\Models\User;
use App\Models\UserActionLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TechnicianController extends Controller
{
    /**
     * List all technicians.
     */
    public function index(Request $request): RedirectResponse
    {
        $technicians = User::technicians()
            ->with('stockLocation')
            ->orderBy('name')
            ->get();

        $editingTechnician = null;

        if ($request->filled('edit')) {
            $editingTechnician = $technicians->firstWhere('id', (int) $request->query('edit'));
        }

        $params = ['tab' => 'technicians'];

        if ($editingTechnician) {
            $params['edit_technician'] = $editingTechnician->id;
        }

        return redirect()->route('admin.personnel', $params);
    }

    /**
     * Create a new technician.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('createTechnician', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'tech_code' => ['nullable', 'string', 'max:50', Rule::unique('users', 'tech_code')],
        ]);

        $techCode = $validated['tech_code'] ? Str::upper($validated['tech_code']) : $this->generateTechCode();
        $actorId = auth()->id();

        DB::transaction(function () use ($validated, $techCode, $actorId) {
            $technician = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'technician',
                'tech_code' => $techCode,
            ]);

            StockLocation::create([
                'location_type' => 'user',
                'ref_id' => $technician->id,
                'name' => 'Stock de ' . $technician->name,
            ]);

            UserActionLog::create([
                'actor_id' => $actorId,
                'target_id' => $technician->id,
                'action' => 'created',
                'details' => 'Creacion de tecnico: ' . $technician->name . ' (' . $technician->email . ')',
            ]);
        });

        return redirect()
            ->route('admin.personnel', ['tab' => 'technicians'])
            ->with('status', 'Tecnico creado correctamente.');
    }

    /**
     * Update an existing technician.
     */
    public function update(Request $request, User $technician): RedirectResponse
    {
        if ($technician->role !== 'technician') {
            abort(404);
        }

        $actorId = auth()->id();
        $original = $technician->only(['name', 'email', 'tech_code']);
        $fieldsChanged = [];

        $validated = $request->validateWithBag('updateTechnician', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($technician->id)],
            'password' => ['nullable', 'confirmed', 'min:8'],
            'tech_code' => ['nullable', 'string', 'max:50', Rule::unique('users', 'tech_code')->ignore($technician->id)],
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'tech_code' => $validated['tech_code']
                ? Str::upper($validated['tech_code'])
                : $technician->tech_code,
        ];

        if ($original['name'] !== $payload['name']) {
            $fieldsChanged[] = 'nombre';
        }

        if ($original['email'] !== $payload['email']) {
            $fieldsChanged[] = 'correo';
        }

        if ($original['tech_code'] !== $payload['tech_code']) {
            $fieldsChanged[] = 'codigo tecnico';
        }

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
            $fieldsChanged[] = 'contrasena';
        }

        DB::transaction(function () use ($technician, $payload, $actorId, $fieldsChanged) {
            $technician->update($payload);

            StockLocation::updateOrCreate(
                [
                    'location_type' => 'user',
                    'ref_id' => $technician->id,
                ],
                [
                    'name' => 'Stock de ' . $payload['name'],
                ]
            );

            UserActionLog::create([
                'actor_id' => $actorId,
                'target_id' => $technician->id,
                'action' => 'updated',
                'details' => $fieldsChanged
                    ? 'Actualizacion de tecnico. Campos modificados: ' . implode(', ', $fieldsChanged)
                    : 'Actualizacion de tecnico sin cambios en los datos principales.',
            ]);
        });

        return redirect()
            ->route('admin.personnel', ['tab' => 'technicians'])
            ->with('status', 'Tecnico actualizado correctamente.');
    }

    /**
     * Remove a technician.
     */
    public function destroy(User $technician): RedirectResponse
    {
        if ($technician->role !== 'technician') {
            abort(404);
        }

        $location = $technician->stockLocation()->first();

        if ($location) {
            $hasInventory = Inventory::query()
                ->where('location_id', $location->id)
                ->where('quantity', '>', 0)
                ->exists();

            $hasSerials = MaterialSerial::query()
                ->where('current_location_id', $location->id)
                ->exists();

            if ($hasInventory || $hasSerials) {
                return redirect()
                    ->route('admin.personnel', ['tab' => 'technicians'])
                    ->withErrors([
                        'general' => 'No se puede eliminar al tecnico porque su stock aun contiene materiales. Retira todo el stock antes de eliminarlo.',
                    ], 'deleteStaff');
            }
        }

        $actorId = auth()->id();
        $technicianName = $technician->name;
        $technicianEmail = $technician->email;
        $techCode = $technician->tech_code;

        DB::transaction(function () use ($technician, $actorId, $technicianName, $technicianEmail, $techCode, $location) {
            UserActionLog::create([
                'actor_id' => $actorId,
                'target_id' => $technician->id,
                'action' => 'deleted',
                'details' => 'Eliminacion de tecnico: ' . $technicianName . ' (' . $technicianEmail . ')' . ($techCode ? ' [' . $techCode . ']' : ''),
            ]);

            if ($location) {
                Inventory::query()
                    ->where('location_id', $location->id)
                    ->delete();
            }

            $technician->delete();
        });

        return redirect()
            ->route('admin.personnel', ['tab' => 'technicians'])
            ->with('status', 'Tecnico eliminado correctamente.');
    }

    /**
     * Generate a unique technician code.
     */
    protected function generateTechCode(): string
    {
        do {
            $code = 'TECH-' . Str::upper(Str::random(6));
        } while (User::where('tech_code', $code)->exists());

        return $code;
    }
}

