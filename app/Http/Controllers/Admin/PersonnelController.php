<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\UserActionLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonnelController extends Controller
{
    /**
     * Muestra el personal y formularios de gestión.
     */
    public function index(Request $request): View
    {   // rol del usuario actual
        $currentRole = $request->user()->role;
        $activeTab = $request->query('tab', 'technicians');
        // validar tab activo
        if (! in_array($activeTab, ['technicians', 'logistics', 'admins', 'super_admins'], true)) {
            $activeTab = 'technicians';
        }
        // obtener personal por rol
        $technicians = User::technicians()
            ->with([
                'stockLocation' => fn ($query) => $query->with(['inventories', 'materialSerials']),
            ])
            ->orderBy('name')
            ->get();
                // obtener personal de logistica
        $logistics = User::logistics()
            ->with([
                'stockLocation' => fn ($query) => $query->with(['inventories', 'materialSerials']),
            ])
            ->orderBy('name')
            ->get();
        // obtener administradores
        $admins = User::admins()
            ->orderBy('name')
            ->get();
        // obtener super administradores
        $superAdmins = User::superAdmins()
            ->orderBy('name')
            ->get();
        // inicializar variables de edición en null
        $editingTechnician = null;
        $editingLogistics = null;
        $editingAdmin = null;
        $editingSuperAdmin = null;
        // verificar si se está editando algún usuario segun query params
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
        // retornar vista con datos
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
            'permissions' => [// permisos segun rol actual
                'canManageTechnicians' => in_array($currentRole, ['admin', 'super_admin'], true),
                'canManageLogistics' => in_array($currentRole, ['admin', 'super_admin'], true),
                'canManageAdmins' => $currentRole === 'super_admin',
                'canManageSuperAdmins' => $currentRole === 'super_admin',
                'canViewMovements' => in_array($currentRole, ['admin', 'super_admin'], true),
            ],
        ]);
    }

    /**
     *  Muestra los movimientos asociados a un usuario específico.
     */
    public function movements(Request $request, User $user): View
    {   // Verificar permisos
        $currentRole = $request->user()->role;
        // Solo admin y super_admin pueden ver estos datos
        if (! in_array($currentRole, ['admin', 'super_admin'], true)) {
            abort(403);
        }
        // Los admin solo pueden ver tecnicos y logistica
        if ($currentRole === 'admin' && ! in_array($user->role, ['technician', 'logistics'], true)) {
            abort(403);
        }
        // Obtener la ubicacion de stock asociada al usuario (si aplica)
        $location = null;
        if (in_array($user->role, ['technician', 'logistics'], true)) {
            $location = $user->stockLocation()->first();
        }

        // Logs de acciones que este usuario realizó sobre otros usuarios (histórico de usuarios tocados)
        $logsQuery = UserActionLog::query()
            ->with(['actor', 'target'])
            ->where('actor_id', $user->id);

        // Movimientos de stock asociados al usuario (solo visibles para super_admin)
        $stockMovementsQuery = null;
        if ($currentRole === 'super_admin') {
            $stockMovementsQuery = StockMovement::query()
                ->with([
                    'material',
                    'serial',
                    'fromLocation',
                    'toLocation',
                    'performer',
                ])
                ->where(function ($query) use ($user, $location) {
                    $query->where('performed_by', $user->id);
                    // Si el usuario tiene una ubicacion de stock, incluir movimientos relacionados
                    if ($location) {
                        $query->orWhere('from_location_id', $location->id)
                            ->orWhere('to_location_id', $location->id);
                    }
                });
        }
        // Aplicar filtros de fecha si existen
        if ($request->filled('from')) {
            $from = now()->parse($request->query('from'))->startOfDay();
            $logsQuery->where('created_at', '>=', $from);
            if ($stockMovementsQuery) {
                $stockMovementsQuery->where('performed_at', '>=', $from);
            }
        }
        // Filtrar hasta fecha
        if ($request->filled('to')) {
            $to = now()->parse($request->query('to'))->endOfDay();
            $logsQuery->where('created_at', '<=', $to);
            if ($stockMovementsQuery) {
                $stockMovementsQuery->where('performed_at', '<=', $to);
            }
        }
        // Obtener resultados paginados
        $logs = $logsQuery
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();
        // Movimientos de stock paginados
        $stockMovements = null;
        if ($stockMovementsQuery) {
            $stockMovements = $stockMovementsQuery
                ->orderByDesc('performed_at')
                ->orderByDesc('created_at')
                ->paginate(25, ['*'], 'stock_page')
                ->appends($request->query());
        }
        // Retornar vista con datos
        return view('admin.personnel.movements', [
            'viewedUser' => $user,
            'logs' => $logs,
            'stockMovements' => $stockMovements,
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
