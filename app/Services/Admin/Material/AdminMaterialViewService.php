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

        $materials = Material::query()
            ->with([
                'inventories' => function ($query) use ($warehouseLocation) {
                    $query->where('location_id', $warehouseLocation->id);
                },
                'serials' => function ($query) use ($warehouseLocation) {
                    $query->where('current_location_id', $warehouseLocation->id)
                        ->where('status', 'available');
                },
            ])
            ->orderBy('category')
            ->orderBy('type')
            ->orderBy('model')
            ->get();

        $technicians = User::technicians()
            ->orderBy('name')
            ->with('stockLocation')
            ->get();

        return [
            'materials' => $materials,
            'warehouseLocation' => $warehouseLocation,
            'technicians' => $technicians,
            'canManageWarehouse' => $this->authorizationService->canManageWarehouse(),
        ];
    }

    public function createData(): array
    {
        $categoryOptions = array_keys($this->crudService->getCategoryMap());

        $typeSuggestions = [
            'equipo' => ['router', 'ont', 'decodificador', 'mando'],
            'acometida' => ['ZTE', 'Huawei', 'Corning', '3M', 'Mixta', 'Interior'],
            'roseta' => ['Final', 'Transici?n'],
        ];

        $defaultSerialized = [
            'equipo' => true,
            'acometida' => false,
            'roseta' => false,
            'otro' => false,
        ];

        $materials = Material::query()
            ->orderBy('category')
            ->orderBy('type')
            ->orderBy('model')
            ->get();

        $reverseCategoryMap = array_flip($this->crudService->getCategoryMap());

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
