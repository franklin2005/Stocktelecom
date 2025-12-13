<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\User;
use App\Services\Admin\Material\AdminMaterialAssignmentService;
use App\Services\Admin\Material\AdminMaterialAuthorizationService;
use App\Services\Admin\Material\AdminMaterialCrudService;
use App\Services\Admin\Material\AdminMaterialLocationService;
use App\Services\Admin\Material\AdminMaterialStockService;
use App\Services\Admin\Material\AdminMaterialViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialController extends Controller
{
    public function __construct(
        private readonly AdminMaterialViewService $viewService,
        private readonly AdminMaterialCrudService $crudService,
        private readonly AdminMaterialStockService $stockService,
        private readonly AdminMaterialAssignmentService $assignmentService,
        private readonly AdminMaterialLocationService $locationService,
        private readonly AdminMaterialAuthorizationService $authorizationService,
    ) {
    }

    /**
     * Muestra el inventario del almacen y formularios de gestion.
     */
    public function index(): View
    {
        $data = $this->viewService->indexData();

        return view('admin.materials', $data);
    }

    /**
     * Formulario de creación de materiales.
     */
    public function create(): View
    {
        $data = $this->viewService->createData();

        return view('admin.materials.create', $data);
    }

    /**
     * Persiste el nuevo material base.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'in:equipo,acometida,roseta,otro'],
            'type' => ['required', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:150'],
            'is_serialized' => ['required', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        
        $result = $this->crudService->store($validated);
        // Si hay un error, redirigir con errores
        if ($result instanceof RedirectResponse) {
            return $result;
        }
        // Si se crea correctamente, redirigir con mensaje de exito
        session()->flash('status', 'Material creado correctamente.');
        // Resaltar el material creado al redirigir
        return redirect()
            ->route('admin.materials.create')
            ->with('highlight_material_id', $result->id);
    }

    /**
     * Actualiza un material existente.
     */
    public function update(Request $request, Material $material): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'category' => ['required', 'string', 'in:equipo,acometida,roseta,otro'],
            'type' => ['required', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:150'],
            'is_serialized' => ['required', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        // Si hay errores de validacion, redirigir con errores
        if ($validator->fails()) {
            return redirect()
                ->route('admin.materials.create')
                ->withErrors($validator, 'updateMaterial')
                ->withInput()
                ->with('editing_material_id', $material->id);
        }
        // Intentar actualizar el material
        $result = $this->crudService->update($material, $validator->validated());
        // Si hay un error, redirigir con errores
        if ($result instanceof RedirectResponse) {
            return $result;
        }
        // Si se actualiza correctamente, redirigir con mensaje de exito
        session()->flash('status', 'Material actualizado correctamente.');
        // Resaltar el material actualizado al redirigir
        return redirect()
            ->route('admin.materials.create')
            ->with('highlight_material_id', $material->id);
    }

    /**
     * Elimina un material si no tiene dependencias.
     */
    public function destroy(Material $material): RedirectResponse
    {   // Intentar eliminar el material
        $result = $this->crudService->destroy($material);
        // Si hay un error, redirigir con errores
        if ($result instanceof RedirectResponse) {
            return $result;
        }
        // Si se elimina correctamente, redirigir con mensaje de exito
        session()->flash('status', 'Material eliminado correctamente.');

        return redirect()->route('admin.materials.create');
    }

    /**
     * Registra nuevas existencias en el almacen principal.
     */
    public function addStock(Request $request): RedirectResponse
    {   // Asegurar permisos
        $this->authorizationService->ensureCanManageWarehouse();
        // Obtener el material
        $material = Material::findOrFail($request->input('material_id'));
        // Agregar stock
        return $this->stockService->addStock($request, $material);
    }

    /**
     * Genera una transferencia hacia un tecnico a la espera de aceptacion.
     */
    public function assignToTechnician(Request $request): RedirectResponse
    {   // Asegurar permisos
        $this->authorizationService->ensureCanManageWarehouse();
        // Obtener el material
        $material = Material::findOrFail($request->input('material_id'));
        // Verificar que el material este activo
        if (! $material->is_active) {
            return back()->withErrors([
                'material_id' => 'El material está inactivo. Actívalo antes de generar nuevas asignaciones.',
            ])->withInput();
        }
        // Validar tecnico destinatario
        $technician = User::technicians()->whereKey($request->input('technician_id'))->first();
        // Si no es valido, error
        if (! $technician) {
            return back()->withErrors([
                'technician_id' => 'Selecciona un tecnico valido.',
            ])->withInput();
        }
        // Asignar al tecnico
        return $this->assignmentService->assignToTechnician($request, $material, $technician);
    }

    /**
     * Elimina existencias del almacen principal.
     */
    public function removeStock(Request $request): RedirectResponse
    {   // Asegurar permisos
        $this->authorizationService->ensureCanManageWarehouse();
        // Obtener el material
        $material = Material::findOrFail($request->input('material_id'));
        // Eliminar stock
        return $this->stockService->removeStock($request, $material);
    }

    /**
     * Exporta el inventario del almacén principal a CSV.
     */
    public function exportWarehouseCsv(): StreamedResponse
    {   // Asegurar permisos
        $this->authorizationService->ensureCanManageWarehouse();
        // Obtener datos del inventario
        $data = $this->viewService->indexData();
        $materials = $data['materials'] ?? collect();
        // Etiquetas de categorias
        $categoryLabels = [
            'equipment' => 'Equipo',
            'acometida' => 'Acometida',
            'roseta' => 'Roseta',
            'other' => 'Otro',
        ];
        // Nombre del archivo
        $fileName = 'inventario_almacen_' . now()->format('Ymd_His') . '.csv';
        // Callback para generar el CSV
        $callback = static function () use ($materials, $categoryLabels) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 para compatibilidad con Excel
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Cabeceras de la tabla
            fputcsv($handle, [
                'Categoría',
                'Tipo',
                'Modelo',
                'Serializado',
                'Stock almacén',
                'Series disponibles',
            ], ';');
            // Filas de materiales
            foreach ($materials as $material) {
                $warehouseInventory = $material->inventories->first();
                $warehouseQuantity = $warehouseInventory?->quantity ?? 0;
                $availableSerials = $material->serials ?? collect();
                
                fputcsv($handle, [
                    $categoryLabels[$material->category] ?? ucfirst($material->category),
                    ucfirst($material->type),
                    $material->model ?? '',
                    $material->is_serialized ? 'Sí' : 'No',
                    $warehouseQuantity,
                    $material->is_serialized ? $availableSerials->count() : '',
                ], ';');
            }
            
            fclose($handle);
        };
        // Retornar respuesta de descarga
        return response()->streamDownload($callback, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
