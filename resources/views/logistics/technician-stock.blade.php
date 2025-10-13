@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="mb-1">Stock de tecnico</h2>
            <p class="text-muted mb-0">
                {{ $technician->name }} · {{ $technician->email }}
                @if ($technician->tech_code)
                    · Codigo: {{ $technician->tech_code }}
                @endif
                <br>
                <small class="text-muted">Ubicacion: {{ $location->name }}</small>
            </p>
        </div>
        <a href="{{ route('technicians.overview') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Materiales no serializados</h5>
                    @if ($nonSerializedInventory->isEmpty())
                        <p class="text-muted mb-0">No hay unidades no serializadas en stock.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Material</th>
                                        <th class="text-end">Cantidad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($nonSerializedInventory as $item)
                                        <tr>
                                            <td>
                                                {{ ucfirst($item->material->type) }}
                                                @if ($item->material->model)
                                                    - {{ $item->material->model }}
                                                @endif
                                            </td>
                                            <td class="text-end">{{ $item->quantity }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Materiales serializados</h5>
                    @if ($serializedGroups->isEmpty())
                        <p class="text-muted mb-0">No hay numeros de serie asignados.</p>
                    @else
                        <div class="accordion" id="technicianSerialsOverview">
                            @foreach ($serializedGroups as $materialId => $serialGroup)
                                @php
                                    $material = $serialGroup->first()->material;
                                    $quantity = $serializedInventory[$materialId]->quantity ?? $serialGroup->count();
                                    $accordionId = 'material-'.$materialId;
                                @endphp
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading-{{ $accordionId }}">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $accordionId }}" aria-expanded="false" aria-controls="collapse-{{ $accordionId }}">
                                            {{ ucfirst($material->type ?? 'Material') }}
                                            @if ($material?->model)
                                                - {{ $material->model }}
                                            @endif
                                            <span class="badge bg-secondary ms-2">{{ $quantity }} en stock</span>
                                        </button>
                                    </h2>
                                    <div id="collapse-{{ $accordionId }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ $accordionId }}" data-bs-parent="#technicianSerialsOverview">
                                        <div class="accordion-body">
                                            <ul class="list-unstyled mb-0">
                                                @foreach ($serialGroup as $serial)
                                                    <li class="py-1">
                                                        Serie: {{ $serial->serial_number }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
