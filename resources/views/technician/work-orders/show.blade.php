@extends('layouts.app')

@php
    // Mapeo de estado → etiqueta + clase visual (soft)
    $statusMap = [
        'open'      => ['label' => 'Abierta',    'class' => 'badge-warning-soft'],
        'confirmed' => ['label' => 'Confirmada', 'class' => 'badge-success-soft'],
        'cancelled' => ['label' => 'Cancelada',  'class' => 'badge-danger-soft'],
    ];
    $st = $statusMap[$workOrder->status] ?? ['label' => ucfirst($workOrder->status), 'class' => 'badge-soft'];
@endphp

@section('content')
    <div class="mb-3">
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-clipboard-check me-2"></i>Orden #{{ $workOrder->order_number }}</h1>
            <p class="st-muted mb-0">
                Creada el {{ $workOrder->created_at?->format('d/m/Y H:i') }} ·
                Estado: <span class="badge {{ $st['class'] }}">{{ $st['label'] }}</span>
            </p>
        </div>
        <a href="{{ route('technician.work-orders') }}" class="btn btn-soft-st">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
            <div class="st-card p-3 h-100">
                <h5 class="card-title mb-3">Información general</h5>
                <dl class="row mb-0">
                    <dt class="col-sm-5 st-muted">Número de orden</dt>
                    <dd class="col-sm-7">{{ $workOrder->order_number }}</dd>

                    <dt class="col-sm-5 st-muted">Estado</dt>
                    <dd class="col-sm-7">
                        <span class="badge {{ $st['class'] }}">{{ $st['label'] }}</span>
                    </dd>

                    <dt class="col-sm-5 st-muted">Técnico</dt>
                    <dd class="col-sm-7">{{ $workOrder->technician_name }}</dd>

                    <dt class="col-sm-5 st-muted">Código técnico</dt>
                    <dd class="col-sm-7">{{ $workOrder->technician_code }}</dd>

                    <dt class="col-sm-5 st-muted">Creada</dt>
                    <dd class="col-sm-7">{{ $workOrder->created_at?->format('d/m/Y H:i') }}</dd>

                    <dt class="col-sm-5 st-muted">Actualizada</dt>
                    <dd class="col-sm-7">{{ $workOrder->updated_at?->format('d/m/Y H:i') }}</dd>
                </dl>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="st-card p-3 h-100">
                <h5 class="card-title mb-3">Notas</h5>
                @if ($workOrder->notes)
                    <p class="mb-1">{{ $workOrder->notes }}</p>
                    <small class="st-muted">
                        Registrado por: {{ $workOrder->notes_author_name ?? 'Desconocido' }}
                        @if ($workOrder->notes_author_type)
                            ({{ ucfirst(str_replace('_', ' ', $workOrder->notes_author_type)) }})
                        @endif
                    </small>
                @else
                    <p class="st-muted mb-0">Sin notas registradas.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="st-card p-3 mb-4">
        <h5 class="card-title mb-3">Materiales por cantidad</h5>
        @if ($quantityItems->isEmpty())
            <p class="st-muted mb-0">No se registraron materiales por cantidad en esta orden.</p>
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
                        @foreach ($quantityItems as $item)
                            <tr>
                                <td>
                                    {{ ucfirst($item->material->type ?? 'Material') }}
                                    @if ($item->material?->model)
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

    <div class="st-card p-3">
        <h5 class="card-title mb-3">Materiales con número de serie</h5>
        @if ($serialItems->isEmpty())
            <p class="st-muted mb-0">No se registraron números de serie en esta orden.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Serie</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($serialItems as $item)
                            <tr>
                                <td>
                                    {{ ucfirst($item->material->type ?? 'Material') }}
                                    @if ($item->material?->model)
                                        - {{ $item->material->model }}
                                    @endif
                                </td>
                                <td>{{ $item->serial?->serial_number }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
