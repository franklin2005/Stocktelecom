<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Staff\StaffCreator;
use App\Services\Staff\StaffDeleter;
use App\Services\Staff\StaffHelper;
use App\Services\Staff\StaffUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function __construct(
        protected StaffCreator $creator,
        protected StaffUpdater $updater,
        protected StaffDeleter $deleter,
        protected StaffHelper $helper,
    ) {
    }

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

        $this->creator->create($validated, $request->user());

        return redirect()
            ->route('admin.personnel', ['tab' => $this->helper->tabForRole($validated['role'])])
            ->with('status', 'Usuario creado correctamente.');
    }

    /**
     * Update an administrator or logistics user.
     */
    public function update(Request $request, User $staff): RedirectResponse
    {
        $validated = $request->validateWithBag('updateStaff', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'password' => ['nullable', 'confirmed', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'logistics', 'super_admin'])],
        ]);

        $updatedStaff = $this->updater->update($staff, $validated, $request->user());

        return redirect()
            ->route('admin.personnel', ['tab' => $this->helper->tabForRole($updatedStaff->role)])
            ->with('status', 'Usuario actualizado correctamente.');
    }

    /**
     * Delete an administrator or logistics user.
     */
    public function destroy(Request $request, User $staff): RedirectResponse
    {
        $redirectTab = $this->helper->tabForRole($staff->role);

        $this->deleter->delete($staff, $request->user());

        return redirect()
            ->route('admin.personnel', ['tab' => $redirectTab])
            ->with('status', 'Usuario eliminado correctamente.');
    }
}
