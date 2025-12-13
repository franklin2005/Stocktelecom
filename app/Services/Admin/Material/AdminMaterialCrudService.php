<?php

namespace App\Services\Admin\Material;

use App\Models\Material;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminMaterialCrudService
{   // mapa de categorias en español a ingles
    public const CATEGORY_MAP = [
        'equipo' => 'equipment',
        'acometida' => 'acometida',
        'roseta' => 'roseta',
        'otro' => 'other',
    ];
    // crear nuevo material
    public function store(array $validated): Material|RedirectResponse
    {   // validar datos
        $category = strtolower(trim($validated['category']));// categoria en minusculas
        $type = trim($validated['type']);// tipo sin espacios
        $model = array_key_exists('model', $validated) && $validated['model'] !== null
            ? trim($validated['model'])// modelo sin espacios
            : null;// modelo nulo si no existe
        // manejar modelo vacio a nulo
        if ($model === '') {
            $model = null;
        }
        // mapear categoria al valor de base de datos
        $databaseCategory = self::CATEGORY_MAP[$category] ?? $category;
        // verificar duplicados activos
        $normalizedCategory = mb_strtolower($databaseCategory, 'UTF-8');
        $normalizedType = mb_strtolower($type, 'UTF-8');
        $normalizedModel = $model !== null ? mb_strtolower($model, 'UTF-8') : null;
        // construir consulta de duplicados
        $duplicateQuery = Material::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(category) = ?', [$normalizedCategory])
            ->whereRaw('LOWER(type) = ?', [$normalizedType]);
        // manejar modelo nulo en consulta
        if ($normalizedModel === null) {
            $duplicateQuery->whereNull('model');
        } else {
            $duplicateQuery->whereRaw('LOWER(model) = ?', [$normalizedModel]);
        }
        // si existe duplicado, retornar error
        if ($duplicateQuery->exists()) {
            return back()->withErrors([
                'type' => 'Ya existe un material activo con la misma categor?a, tipo y modelo.',
            ])->withInput();
        }
        // convertir campos booleanos
        $isSerialized = (bool) ($validated['is_serialized'] ?? false);
        $isActive = (bool) ($validated['is_active'] ?? false);
        // crear y retornar nuevo material
        return Material::create([
            'category' => $databaseCategory,
            'type' => $type,
            'model' => $model,
            'is_serialized' => $isSerialized,
            'is_active' => $isActive,
        ]);
    }
    // actualizar material existente
    public function update(Material $material, array $validated): Material|RedirectResponse
    {   // validar datos
        $validator = Validator::make($validated, [
            'category' => ['required', 'string', 'in:equipo,acometida,roseta,otro'],
            'type' => ['required', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:150'],
            'is_serialized' => ['required', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        // si hay errores, retornar con errores
        if ($validator->fails()) {
            return redirect()
                ->route('admin.materials.create')
                ->withErrors($validator, 'updateMaterial')
                ->withInput()
                ->with('editing_material_id', $material->id);
        }
        // obtener datos validados
        $validated = $validator->validated();
        // normalizar datos
        $categoryKey = strtolower(trim($validated['category']));
        $type = trim($validated['type']);
        $model = array_key_exists('model', $validated) && $validated['model'] !== null
            ? trim($validated['model'])
            : null;

        if ($model === '') {
            $model = null;
        }
        // mapear categoria al valor de base de datos
        $databaseCategory = self::CATEGORY_MAP[$categoryKey] ?? $categoryKey;
        // convertir campos booleanos
        $isSerialized = (bool) ($validated['is_serialized'] ?? false);
        $isActive = (bool) ($validated['is_active'] ?? false);
        // verificar duplicados activos si se activa el material
        if ($isActive) {
            $normalizedCategory = mb_strtolower($databaseCategory, 'UTF-8');
            $normalizedType = mb_strtolower($type, 'UTF-8');
            $normalizedModel = $model !== null ? mb_strtolower($model, 'UTF-8') : null;
            // construir consulta de duplicados
            $duplicateQuery = Material::query()
                ->where('id', '!=', $material->id)
                ->where('is_active', true)
                ->whereRaw('LOWER(category) = ?', [$normalizedCategory])
                ->whereRaw('LOWER(type) = ?', [$normalizedType]);
            // manejar modelo nulo en consulta
            if ($normalizedModel === null) {
                $duplicateQuery->whereNull('model');
            } else {
                $duplicateQuery->whereRaw('LOWER(model) = ?', [$normalizedModel]);// agregar condicion de modelo
            }
            // si existe duplicado, retornar con error
            if ($duplicateQuery->exists()) {
                return redirect()
                    ->route('admin.materials.create')
                    ->withErrors([
                        'type' => 'Ya existe un material activo con la misma categor?a, tipo y modelo.',
                    ], 'updateMaterial')
                    ->withInput()
                    ->with('editing_material_id', $material->id);
            }
        }
        // actualizar material y retornar
        $material->update([
            'category' => $databaseCategory,
            'type' => $type,
            'model' => $model,
            'is_serialized' => $isSerialized,
            'is_active' => $isActive,
        ]);

        return $material;
    }
    // eliminar material
    public function destroy(Material $material): bool|RedirectResponse
    {   // verificar dependencias antes de eliminar
        $hasDependencies = $material->serials()->exists()// tiene series actualmente
            || $material->inventories()->exists()   // tiene inventario
            || $material->transferItems()->exists() // tiene items de transferencia
            || $material->workOrderItems()->exists()    // tiene items de orden de trabajo
            || DB::table('stock_movements')->where('material_id', $material->id)->exists();// tiene movimientos de stock
        // si tiene dependencias, retornar con error
        if ($hasDependencies) {
            return redirect()
                ->route('admin.materials.create')
                ->with('material_error', 'No es posible eliminar el material porque tiene movimientos, inventario o series asociadas.')
                ->with('highlight_material_id', $material->id);
        }
        // eliminar material
        $material->delete();

        return true;
    }
    // obtener mapa de categorias
    public function getCategoryMap(): array
    {
        return self::CATEGORY_MAP;  // retornar mapa de categorias
    }
}
