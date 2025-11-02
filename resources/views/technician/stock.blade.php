@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1">Mi stock</h1>
            <p class="st-muted mb-0">Ubicación asignada: {{ $location->name }}</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <div class="st-card p-3 h-100">
                <h5 class="card-title mb-3">Materiales no serializados</h5>

                @if ($nonSerializedInventory->isEmpty())
                    <p class="st-muted mb-0">No tienes unidades no serializadas en stock.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
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

        <div class="col-12 col-xl-6">
            <div class="st-card p-3 h-100">
                <h5 class="card-title mb-3">Materiales serializados</h5>

                @if ($serializedGroups->isEmpty())
                    <p class="st-muted mb-0">No tienes números de serie asignados actualmente.</p>
                @else
                    <div class="accordion" id="technicianSerials">
                        @foreach ($serializedGroups as $materialId => $serialGroup)
                            @php
                                $material = $serialGroup->first()->material;
                                $aggregate = $serializedAggregates->firstWhere('material_id', $materialId);
                                $quantity = $aggregate?->quantity ?? $serialGroup->count();
                                $accordionId = 'material-'.$materialId;
                            @endphp
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading-{{ $accordionId }}">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $accordionId }}" aria-expanded="false" aria-controls="collapse-{{ $accordionId }}">
                                        {{ ucfirst($material->type ?? 'Material') }}
                                        @if ($material?->model)
                                            - {{ $material->model }}
                                        @endif
                                        <span class="badge badge-soft ms-2">{{ $quantity }} en stock</span>
                                    </button>
                                </h2>
                                <div id="collapse-{{ $accordionId }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ $accordionId }}" data-bs-parent="#technicianSerials">
                                    <div class="accordion-body">
                                        <ul class="list-unstyled mb-0">
                                            @foreach ($serialGroup as $serial)
                                                <li class="py-1">
                                                    <span class="fw-semibold">Serie:</span> {{ $serial->serial_number }}
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
@endsection
