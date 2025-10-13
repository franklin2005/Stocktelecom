<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonnelController extends Controller
{
    /**
     * Display personnel management tabs.
     */
    public function index(Request $request): View
    {
        $currentRole = $request->user()->role;
        $activeTab = $request->query('tab', 'technicians');

        if (! in_array($activeTab, ['technicians', 'logistics', 'admins', 'super_admins'], true)) {
            $activeTab = 'technicians';
        }

        $technicians = User::technicians()
            ->with([
                'stockLocation' => fn ($query) => $query->with(['inventories', 'materialSerials']),
            ])
            ->orderBy('name')
            ->get();

        $logistics = User::logistics()
            ->with([
                'stockLocation' => fn ($query) => $query->with(['inventories', 'materialSerials']),
            ])
            ->orderBy('name')
            ->get();

        $admins = User::admins()
            ->orderBy('name')
            ->get();

        $superAdmins = User::superAdmins()
            ->orderBy('name')
            ->get();

        $editingTechnician = null;
        $editingLogistics = null;
        $editingAdmin = null;
        $editingSuperAdmin = null;

        if ($request->filled('edit_technician')) {
            $editingTechnician = $technicians->firstWhere('id', (int) $request->query('edit_technician'));
        }

        if ($request->filled('edit_logistics')) {
            $editingLogistics = $logistics->firstWhere('id', (int) $request->query('edit_logistics'));
        }

        if ($request->filled('edit_admin')) {
            $editingAdmin = $admins->firstWhere('id', (int) $request->query('edit_admin'));
        }

        if ($request->filled('edit_super_admin')) {
            $editingSuperAdmin = $superAdmins->firstWhere('id', (int) $request->query('edit_super_admin'));
        }

        return view('admin.personnel', [
            'activeTab' => $activeTab,
            'technicians' => $technicians,
            'logistics' => $logistics,
            'admins' => $admins,
            'superAdmins' => $superAdmins,
            'editingTechnician' => $editingTechnician,
            'editingLogistics' => $editingLogistics,
            'editingAdmin' => $editingAdmin,
            'editingSuperAdmin' => $editingSuperAdmin,
            'permissions' => [
                'canManageTechnicians' => in_array($currentRole, ['admin', 'super_admin'], true),
                'canManageLogistics' => in_array($currentRole, ['admin', 'super_admin'], true),
                'canManageAdmins' => $currentRole === 'super_admin',
                'canManageSuperAdmins' => $currentRole === 'super_admin',
                'canViewMovements' => in_array($currentRole, ['admin', 'super_admin'], true),
            ],
        ]);
    }

    /**
     * Display movement history for a specific user.
     */
    public function movements(Request $request, User $user): View
    {
        $currentRole = $request->user()->role;

        if (! in_array($currentRole, ['admin', 'super_admin'], true)) {
            abort(403);
        }

        if ($currentRole === 'admin' && ! in_array($user->role, ['technician', 'logistics'], true)) {
            abort(403);
        }

        $location = null;

        if (in_array($user->role, ['technician', 'logistics'], true)) {
            $location = $user->stockLocation()->first();
        }

        $movementsQuery = StockMovement::query()
            ->with([
                'material',
                'serial',
                'fromLocation',
                'toLocation',
                'performer',
            ])
            ->where(function ($query) use ($user, $location) {
                $query->where('performed_by', $user->id);

                if ($location) {
                    $query->orWhere('from_location_id', $location->id)
                        ->orWhere('to_location_id', $location->id);
                }
            });

        if ($request->filled('from')) {
            $from = now()->parse($request->query('from'))->startOfDay();
            $movementsQuery->where('performed_at', '>=', $from);
        }

        if ($request->filled('to')) {
            $to = now()->parse($request->query('to'))->endOfDay();
            $movementsQuery->where('performed_at', '<=', $to);
        }

        $movements = $movementsQuery
            ->orderByDesc('performed_at')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.personnel.movements', [
            'viewedUser' => $user,
            'movements' => $movements,
            'location' => $location,
            'backTab' => match ($user->role) {
                'technician' => 'technicians',
                'logistics' => 'logistics',
                'admin' => 'admins',
                'super_admin' => 'super_admins',
                default => 'technicians',
            },
        ]);
    }
}
