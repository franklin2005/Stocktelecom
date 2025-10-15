@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('technician.work-orders') }}" class="btn btn-sm btn-outline-secondary">&larr; Volver al listado</a>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Orden #{{ $workOrder->order_number }}</h2>
            <p class="text-muted mb-0">
                Creada el {{ $workOrder->created_at?->format('d/m/Y H:i') }} |
                Estado: <span class="badge {{ $statusLabel['class'] ?? 'bg-secondary' }}">{{ $statusLabel['label'] ?? ucfirst($workOrder->status) }}</span>
            </p>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Información general</h5>
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-muted">Número de orden</dt>
                        <dd class="col-sm-7">{{ $workOrder->order_number }}</dd>

                        <dt class="col-sm-5 text-muted">Estado</dt>
                        <dd class="col-sm-7">
                            <span class="badge {{ $statusLabel['class'] ?? 'bg-secondary' }}">{{ $statusLabel['label'] ?? ucfirst($workOrder->status) }}</span>
                        </dd>

                        <dt class="col-sm-5 text-muted">Técnico</dt>
                        <dd class="col-sm-7">{{ $workOrder->technician_name }}</dd>

                        <dt class="col-sm-5 text-muted">Código técnico</dt>
                        <dd class="col-sm-7">{{ $workOrder->technician_code }}</dd>

                        <dt class="col-sm-5 text-muted">Creada</dt>
                        <dd class="col-sm-7">{{ $workOrder->created_at?->format('d/m/Y H:i') }}</dd>

                        <dt class="col-sm-5 text-muted">Actualizada</dt>
                        <dd class="col-sm-7">{{ $workOrder->updated_at?->format('d/m/Y H:i') }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Notas</h5>
                    @if ($workOrder->notes)
                        <p class="mb-1">{{ $workOrder->notes }}</p>
                        <small class="text-muted">
                            Registrado por: {{ $workOrder->notes_author_name ?? 'Desconocido' }}
                            @if ($workOrder->notes_author_type)
                                ({{ ucfirst(str_replace('_', ' ', $workOrder->notes_author_type)) }})
                            @endif
                        </small>
                    @else
                        <p class="text-muted mb-0">Sin notas registradas.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Materiales por cantidad</h5>
            @if ($quantityItems->isEmpty())
                <p class="text-muted mb-0">No se registraron materiales por cantidad en esta orden.</p>
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
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">Materiales serializados</h5>
            @if ($serialItems->isEmpty())
                <p class="text-muted mb-0">No se registraron seriales en esta orden.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
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
    </div>
@endsection