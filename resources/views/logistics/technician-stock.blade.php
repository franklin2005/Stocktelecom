@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-box-seam me-2"></i>Stock de técnico</h1>
            <p class="st-muted mb-0">
                {{ $technician->name }} · {{ $technician->email }}
                @if ($technician->tech_code)
                    · Código: {{ $technician->tech_code }}
                @endif
                <br>
                <small class="st-muted">Ubicación: {{ $location->name }}</small>
            </p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <div class="st-card p-3 h-100">
                <h5 class="card-title mb-3">Materiales no serializados</h5>

                @if ($nonSerializedInventory->isEmpty())
                    <p class="st-muted mb-0">No hay unidades no serializadas en stock.</p>
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
                                        <td class="text-end">
                                            <span class="badge badge-soft">{{ $item->quantity }}</span>
                                        </td>
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
                    <p class="st-muted mb-0">No hay números de serie asignados.</p>
                @else
                    <div class="accordion" id="technicianSerialsOverview">
                        @foreach ($serializedGroups as $materialId => $serialGroup)
                            @php
                                $material = $serialGroup->first()->material;
                                $quantity = $serialGroup->count();
                                $accordionId = 'material-' . $materialId;
                            @endphp

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading-{{ $accordionId }}">
                                    <button class="accordion-button collapsed" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#collapse-{{ $accordionId }}"
                                            aria-expanded="false"
                                            aria-controls="collapse-{{ $accordionId }}">
                                        {{ ucfirst($material->type ?? 'Material') }}
                                        @if ($material?->model)
                                            - {{ $material->model }}
                                        @endif
                                        <span class="badge badge-soft ms-2">{{ $quantity }} en stock</span>
                                    </button>
                                </h2>
                                <div id="collapse-{{ $accordionId }}" class="accordion-collapse collapse"
                                     aria-labelledby="heading-{{ $accordionId }}"
                                     data-bs-parent="#technicianSerialsOverview">
                                    <div class="accordion-body">
                                        <ul class="list-unstyled mb-0">
                                            @foreach ($serialGroup as $serial)
                                                <li class="py-1">
                                                    Serie: <span class="badge badge-accent">{{ $serial->serial_number }}</span>
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
