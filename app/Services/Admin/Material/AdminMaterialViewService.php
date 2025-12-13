<?php

namespace App\Services\Admin\Material;

use App\Models\Material;
use App\Models\User;
use App\Services\Admin\Material\AdminMaterialAuthorizationService;
use App\Services\Admin\Material\AdminMaterialCrudService;
use Illuminate\Support\Facades\Auth;

class AdminMaterialViewService
{
    public function __construct(
        private readonly AdminMaterialLocationService $locationService,
        private readonly AdminMaterialAuthorizationService $authorizationService,
        private readonly AdminMaterialCrudService $crudService,
    ) {
    }

    public function indexData(): array
    {
        $warehouseLocation = $this->locationService->warehouseLocation();
        // cargar materiales con inventario y numeros de serie en el almacen
        $materials = Material::query()
            ->with([    // cargar inventario y numeros de serie en el almacen
                'inventories' => function ($query) use ($warehouseLocation) {
                    $query->where('location_id', $warehouseLocation->id);
                },  // cargar numeros de serie disponibles en el almacen
                'serials' => function ($query) use ($warehouseLocation) {
                    $query->where('current_location_id', $warehouseLocation->id)
                        ->where('status', 'available');
                },  // fin relaciones
            ])
            ->orderBy('category')
            ->orderBy('type')
            ->orderBy('model')
            ->get();
                // cargar tecnicos con ubicaciones de stock
        $technicians = User::technicians()
            ->orderBy('name')
            ->with('stockLocation')
            ->get();
                
        return [// datos para la vista de inventario
            'materials' => $materials,
            'warehouseLocation' => $warehouseLocation,
            'technicians' => $technicians,
            'canManageWarehouse' => $this->authorizationService->canManageWarehouse(),
        ];
    }
    // datos para la vista de creacion de materiales
    public function createData(): array
    {   // opciones de categoria
        $categoryOptions = array_keys($this->crudService->getCategoryMap());
        // sugerencias de tipo por categoria
        $typeSuggestions = [
            'equipo' => ['router', 'ont', 'decodificador', 'mando'],
            'acometida' => ['ZTE', 'Huawei', 'Corning', '3M', 'Mixta', 'Interior'],
            'roseta' => ['Final', 'Transici?n'],
        ];
        // valores por defecto de serializacion por categoria
        $defaultSerialized = [
            'equipo' => true,
            'acometida' => false,
            'roseta' => false,
            'otro' => false,
        ];
        // cargar todos los materiales existentes
        $materials = Material::query()
            ->orderBy('category')
            ->orderBy('type')
            ->orderBy('model')
            ->get();
        // mapa inverso de categorias
        $reverseCategoryMap = array_flip($this->crudService->getCategoryMap());
        // retornar datos para la vista
        return [
            'categoryOptions' => $categoryOptions,
            'typeSuggestions' => $typeSuggestions,
            'defaultSerialized' => $defaultSerialized,
            'materials' => $materials,
            'categoryMap' => $this->crudService->getCategoryMap(),
            'reverseCategoryMap' => $reverseCategoryMap,
        ];
    }
}
