<?php

namespace App\Services\Admin\Material;

use App\Models\Material;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminMaterialCrudService
{
    public const CATEGORY_MAP = [
        'equipo' => 'equipment',
        'acometida' => 'acometida',
        'roseta' => 'roseta',
        'otro' => 'other',
    ];

    public function store(array $validated): Material|RedirectResponse
    {
        $category = strtolower(trim($validated['category']));
        $type = trim($validated['type']);
        $model = array_key_exists('model', $validated) && $validated['model'] !== null
            ? trim($validated['model'])
            : null;

        if ($model === '') {
            $model = null;
        }

        $databaseCategory = self::CATEGORY_MAP[$category] ?? $category;

        $normalizedCategory = mb_strtolower($databaseCategory, 'UTF-8');
        $normalizedType = mb_strtolower($type, 'UTF-8');
        $normalizedModel = $model !== null ? mb_strtolower($model, 'UTF-8') : null;

        $duplicateQuery = Material::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(category) = ?', [$normalizedCategory])
            ->whereRaw('LOWER(type) = ?', [$normalizedType]);

        if ($normalizedModel === null) {
            $duplicateQuery->whereNull('model');
        } else {
            $duplicateQuery->whereRaw('LOWER(model) = ?', [$normalizedModel]);
        }

        if ($duplicateQuery->exists()) {
            return back()->withErrors([
                'type' => 'Ya existe un material activo con la misma categor?a, tipo y modelo.',
            ])->withInput();
        }

        $isSerialized = (bool) ($validated['is_serialized'] ?? false);
        $isActive = (bool) ($validated['is_active'] ?? false);

        return Material::create([
            'category' => $databaseCategory,
            'type' => $type,
            'model' => $model,
            'is_serialized' => $isSerialized,
            'is_active' => $isActive,
        ]);
    }

    public function update(Material $material, array $validated): Material|RedirectResponse
    {
        $validator = Validator::make($validated, [
            'category' => ['required', 'string', 'in:equipo,acometida,roseta,otro'],
            'type' => ['required', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:150'],
            'is_serialized' => ['required', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('admin.materials.create')
                ->withErrors($validator, 'updateMaterial')
                ->withInput()
                ->with('editing_material_id', $material->id);
        }

        $validated = $validator->validated();

        $categoryKey = strtolower(trim($validated['category']));
        $type = trim($validated['type']);
        $model = array_key_exists('model', $validated) && $validated['model'] !== null
            ? trim($validated['model'])
            : null;

        if ($model === '') {
            $model = null;
        }

        $databaseCategory = self::CATEGORY_MAP[$categoryKey] ?? $categoryKey;

        $isSerialized = (bool) ($validated['is_serialized'] ?? false);
        $isActive = (bool) ($validated['is_active'] ?? false);

        if ($isActive) {
            $normalizedCategory = mb_strtolower($databaseCategory, 'UTF-8');
            $normalizedType = mb_strtolower($type, 'UTF-8');
            $normalizedModel = $model !== null ? mb_strtolower($model, 'UTF-8') : null;

            $duplicateQuery = Material::query()
                ->where('id', '!=', $material->id)
                ->where('is_active', true)
                ->whereRaw('LOWER(category) = ?', [$normalizedCategory])
                ->whereRaw('LOWER(type) = ?', [$normalizedType]);

            if ($normalizedModel === null) {
                $duplicateQuery->whereNull('model');
            } else {
                $duplicateQuery->whereRaw('LOWER(model) = ?', [$normalizedModel]);
            }

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

        $material->update([
            'category' => $databaseCategory,
            'type' => $type,
            'model' => $model,
            'is_serialized' => $isSerialized,
            'is_active' => $isActive,
        ]);

        return $material;
    }

    public function destroy(Material $material): bool|RedirectResponse
    {
        $hasDependencies = $material->serials()->exists()
            || $material->inventories()->exists()
            || $material->transferItems()->exists()
            || $material->workOrderItems()->exists()
            || DB::table('stock_movements')->where('material_id', $material->id)->exists();

        if ($hasDependencies) {
            return redirect()
                ->route('admin.materials.create')
                ->with('material_error', 'No es posible eliminar el material porque tiene movimientos, inventario o series asociadas.')
                ->with('highlight_material_id', $material->id);
        }

        $material->delete();

        return true;
    }

    public function getCategoryMap(): array
    {
        return self::CATEGORY_MAP;
    }
}
