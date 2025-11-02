@extends('layouts.app')

@section('content')
    @php
        $formContext = old('form_context');
        $isCreateContext = $formContext === 'create';
        $categoryValue = $categoryOptions[0] ?? 'equipo';
        if ($isCreateContext) {
            $categoryValue = old('category', $categoryValue);
        }
        $categoryValue = in_array($categoryValue, $categoryOptions, true) ? $categoryValue : ($categoryOptions[0] ?? 'equipo');
        $serializedOld = $isCreateContext ? old('is_serialized') : null;
        $serializedDefault = $serializedOld !== null
            ? ((string) $serializedOld === '1')
            : ($defaultSerialized[$categoryValue] ?? false);
        $activeDefault = $isCreateContext ? (old('is_active', '1') !== '0') : true;
        $typeOldValue = $isCreateContext ? old('type') : null;
        $modelOldValue = $isCreateContext ? old('model') : null;
        $categoryLabels = [
            'equipo' => 'Equipo',
            'acometida' => 'Acometida',
            'roseta' => 'Roseta',
            'otro' => 'Otro',
        ];
        $highlightMaterialId = session('highlight_material_id');
        $editingMaterialId = session('editing_material_id');
        $updateErrors = $errors->updateMaterial ?? null;
    @endphp

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h2 class="mb-0">Crear material</h2>
        <a href="{{ route('admin.materials') }}" class="btn btn-outline-secondary">Volver al listado</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Revisa los campos:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.materials.store') }}">
                @csrf
                <input type="hidden" name="form_context" value="create">

                <div class="row g-4">
                    <div class="col-12 col-lg-6">
                        <div class="mb-3">
                            <label for="category" class="form-label">Categoría</label>
                            <select
                                id="category"
                                name="category"
                                class="form-select @error('category') is-invalid @enderror"
                                required
                            >
                                @foreach ($categoryOptions as $category)
                                    <option value="{{ $category }}" @selected($categoryValue === $category)>
                                        {{ ucfirst($category) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3" id="type-select-wrapper">
                            <label for="type_select" class="form-label">Tipo sugerido</label>
                            <select id="type_select" class="form-select">
                                <option value="">Selecciona un tipo</option>
                            </select>
                            <small class="text-muted">Puedes escoger un tipo sugerido o escribirlo manualmente.</small>
                        </div>

                        <div class="mb-3" id="type-input-wrapper">
                            <label for="type" class="form-label">Tipo</label>
                            <input
                                type="text"
                                id="type"
                                name="type"
                                class="form-control @error('type') is-invalid @enderror"
                                value="{{ $typeOldValue }}"
                                maxlength="100"
                                required
                            >
                            @error('type')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="model" class="form-label">Modelo (opcional)</label>
                            <input
                                type="text"
                                id="model"
                                name="model"
                                class="form-control @error('model') is-invalid @enderror"
                                value="{{ $modelOldValue }}"
                                maxlength="150"
                            >
                            @error('model')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="form-check mb-3">
                            <input type="hidden" name="is_serialized" value="0">
                            <input
                                type="checkbox"
                                id="is_serialized"
                                name="is_serialized"
                                value="1"
                                class="form-check-input @error('is_serialized') is-invalid @enderror"
                                @checked($serializedDefault)
                            >
                            <label class="form-check-label" for="is_serialized">Material serializado</label>
                            @error('is_serialized')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check mb-4">
                            <input type="hidden" name="is_active" value="0">
                            <input
                                type="checkbox"
                                id="is_active"
                                name="is_active"
                                value="1"
                                class="form-check-input @error('is_active') is-invalid @enderror"
                                @checked($activeDefault)
                            >
                            <label class="form-check-label" for="is_active">Activo</label>
                            @error('is_active')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Guardar</button>
                            <a href="{{ route('admin.materials') }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="card-title mb-0">Materiales existentes</h5>
            </div>

            @if (session('material_error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('material_error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Categoría</th>
                            <th>Tipo</th>
                            <th>Modelo</th>
                            <th>Serializado</th>
                            <th>Activo</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($materials as $material)
                            @php
                                $materialCategoryKey = $reverseCategoryMap[$material->category] ?? $material->category;
                                $materialCategoryLabel = $categoryLabels[$materialCategoryKey] ?? ucfirst($materialCategoryKey);
                                $shouldShowEdit = $editingMaterialId === $material->id || $formContext === 'update-' . $material->id;
                                $isHighlighted = $highlightMaterialId === $material->id;
                                $materialErrors = ($editingMaterialId === $material->id || $formContext === 'update-' . $material->id) ? $updateErrors : null;
                                $editFormId = 'material-edit-' . $material->id;
                                $isEditingContext = $formContext === 'update-' . $material->id;
                                $editCategoryValue = $isEditingContext ? old('category', $materialCategoryKey) : $materialCategoryKey;
                                if (! in_array($editCategoryValue, $categoryOptions, true)) {
                                    $editCategoryValue = $materialCategoryKey;
                                }
                                $editTypeValue = $isEditingContext ? old('type', $material->type) : $material->type;
                                $editModelValue = $isEditingContext ? old('model', $material->model) : $material->model;
                                $editIsSerialized = $isEditingContext
                                    ? ((string) old('is_serialized', $material->is_serialized ? '1' : '0') === '1')
                                    : (bool) $material->is_serialized;
                                $editIsActive = $isEditingContext
                                    ? ((string) old('is_active', $material->is_active ? '1' : '0') === '1')
                                    : (bool) $material->is_active;
                            @endphp
                            <tr class="{{ $isHighlighted ? 'table-success' : '' }}">
                                <td>{{ $material->id }}</td>
                                <td>{{ $materialCategoryLabel }}</td>
                                <td>{{ ucfirst($material->type) }}</td>
                                <td>{{ $material->model ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-{{ $material->is_serialized ? 'info' : 'secondary' }}">
                                        {{ $material->is_serialized ? 'Sí' : 'No' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $material->is_active ? 'success' : 'dark' }}">
                                        {{ $material->is_active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $editFormId }}" aria-expanded="{{ $shouldShowEdit ? 'true' : 'false' }}" aria-controls="{{ $editFormId }}">
                                            Editar
                                        </button>
                                        <form method="POST" action="{{ route('admin.materials.destroy', $material) }}" onsubmit="return confirm('¿Seguro que deseas eliminar este material? Esta acción no se puede deshacer.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr class="collapse {{ $shouldShowEdit ? 'show' : '' }}" id="{{ $editFormId }}">
                                <td colspan="7">
                                    <form method="POST" action="{{ route('admin.materials.update', $material) }}" class="border rounded p-3 bg-light">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="form_context" value="update-{{ $material->id }}">

                                        <div class="row g-3">
                                            <div class="col-12 col-lg-3">
                                                <label for="category-{{ $material->id }}" class="form-label">Categoría</label>
                                                <select
                                                    id="category-{{ $material->id }}"
                                                    name="category"
                                                    class="form-select {{ $materialErrors && $materialErrors->has('category') ? 'is-invalid' : '' }}"
                                                    required
                                                >
                                                    @foreach ($categoryOptions as $categoryOption)
                                                        <option value="{{ $categoryOption }}" @selected($editCategoryValue === $categoryOption)>{{ $categoryLabels[$categoryOption] ?? ucfirst($categoryOption) }}</option>
                                                    @endforeach
                                                </select>
                                                @if ($materialErrors && $materialErrors->has('category'))
                                                    <div class="invalid-feedback d-block">{{ $materialErrors->first('category') }}</div>
                                                @endif
                                            </div>
                                            <div class="col-12 col-lg-3">
                                                <label for="type-{{ $material->id }}" class="form-label">Tipo</label>
                                                <input
                                                    type="text"
                                                    id="type-{{ $material->id }}"
                                                    name="type"
                                                    class="form-control {{ $materialErrors && $materialErrors->has('type') ? 'is-invalid' : '' }}"
                                                    value="{{ $editTypeValue }}"
                                                    maxlength="100"
                                                    required
                                                >
                                                @if ($materialErrors && $materialErrors->has('type'))
                                                    <div class="invalid-feedback d-block">{{ $materialErrors->first('type') }}</div>
                                                @endif
                                            </div>
                                            <div class="col-12 col-lg-3">
                                                <label for="model-{{ $material->id }}" class="form-label">Modelo (opcional)</label>
                                                <input
                                                    type="text"
                                                    id="model-{{ $material->id }}"
                                                    name="model"
                                                    class="form-control {{ $materialErrors && $materialErrors->has('model') ? 'is-invalid' : '' }}"
                                                    value="{{ $editModelValue }}"
                                                    maxlength="150"
                                                >
                                                @if ($materialErrors && $materialErrors->has('model'))
                                                    <div class="invalid-feedback d-block">{{ $materialErrors->first('model') }}</div>
                                                @endif
                                            </div>
                                            <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                <div class="form-check mb-2">
                                                    <input type="hidden" name="is_serialized" value="0">
                                                    <input
                                                        type="checkbox"
                                                        id="is_serialized-{{ $material->id }}"
                                                        name="is_serialized"
                                                        value="1"
                                                        class="form-check-input {{ $materialErrors && $materialErrors->has('is_serialized') ? 'is-invalid' : '' }}"
                                                        @checked($editIsSerialized)
                                                    >
                                                    <label class="form-check-label" for="is_serialized-{{ $material->id }}">Material serializado</label>
                                                    @if ($materialErrors && $materialErrors->has('is_serialized'))
                                                        <div class="invalid-feedback d-block">{{ $materialErrors->first('is_serialized') }}</div>
                                                    @endif
                                                </div>
                                                <div class="form-check">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input
                                                        type="checkbox"
                                                        id="is_active-{{ $material->id }}"
                                                        name="is_active"
                                                        value="1"
                                                        class="form-check-input {{ $materialErrors && $materialErrors->has('is_active') ? 'is-invalid' : '' }}"
                                                        @checked($editIsActive)
                                                    >
                                                    <label class="form-check-label" for="is_active-{{ $material->id }}">Activo</label>
                                                    @if ($materialErrors && $materialErrors->has('is_active'))
                                                        <div class="invalid-feedback d-block">{{ $materialErrors->first('is_active') }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="col-12 d-flex justify-content-end gap-2">
                                                <button type="submit" class="btn btn-primary">Guardar cambios</button>
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-secondary"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#{{ $editFormId }}"
                                                    aria-expanded="true"
                                                    aria-controls="{{ $editFormId }}"
                                                >
                                                    Cancelar
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">No hay materiales registrados todavía.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const categorySelect = document.getElementById('category');
            const typeSelect = document.getElementById('type_select');
            const typeSelectWrapper = document.getElementById('type-select-wrapper');
            const typeInput = document.getElementById('type');
            const isSerializedCheckbox = document.getElementById('is_serialized');
            const typeSuggestions = @json($typeSuggestions);
            const defaultSerialized = @json($defaultSerialized);
            const hasSerializedOld = @json($serializedOld !== null);

            function populateTypeOptions(category) {
                const currentValue = (typeInput.value || '').toLowerCase();
                const options = typeSuggestions[category] || [];

                typeSelect.innerHTML = '<option value="">Selecciona un tipo</option>';

                options.forEach(function (option) {
                    const optionElement = document.createElement('option');
                    optionElement.value = option;
                    optionElement.textContent = option.charAt(0).toUpperCase() + option.slice(1);

                    if (currentValue && currentValue === option.toLowerCase()) {
                        optionElement.selected = true;
                    }

                    typeSelect.appendChild(optionElement);
                });

                if (options.length === 0) {
                    typeSelectWrapper.classList.add('d-none');
                } else {
                    typeSelectWrapper.classList.remove('d-none');
                }
            }

            function applySerializedDefault(category, force = false) {
                if (!force && hasSerializedOld) {
                    return;
                }

                if (Object.prototype.hasOwnProperty.call(defaultSerialized, category)) {
                    isSerializedCheckbox.checked = Boolean(defaultSerialized[category]);
                }
            }

            categorySelect.addEventListener('change', function (event) {
                const category = event.target.value;
                populateTypeOptions(category);
                applySerializedDefault(category, true);
            });

            typeSelect.addEventListener('change', function (event) {
                if (event.target.value) {
                    typeInput.value = event.target.value;
                }
            });

            populateTypeOptions(categorySelect.value);
            applySerializedDefault(categorySelect.value);
        });
    </script>
@endsection
