@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0"><i class="bi bi-person-badge me-2"></i>Técnicos</h1>
    </div>

    <div class="st-card p-3">
        @if ($technicians->isEmpty())
            <p class="st-muted mb-0">Aún no hay técnicos registrados.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Código técnico</th>
                            <th>Ubicación</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($technicians as $technician)
                            <tr>
                                <td>{{ $technician->name }}</td>
                                <td>{{ $technician->email }}</td>
                                <td>
                                    @if ($technician->tech_code)
                                        <span class="badge badge-soft">{{ $technician->tech_code }}</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Pendiente</span>
                                    @endif
                                </td>
                                <td>{{ $technician->stockLocation->name ?? 'Sin ubicación' }}</td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('admin.technicians.stock.overview', $technician) }}" class="btn btn-sm btn-accent-st">
                                            <i class="bi bi-box-seam me-1"></i>Ver stock
                                        </a>
                                        <a href="{{ route('admin.technicians.transfers.history', $technician) }}" class="btn btn-sm btn-soft-st">
                                            <i class="bi bi-arrow-repeat me-1"></i>Transferencias
                                        </a>
                                        <a href="{{ route('admin.technicians.returns.history', $technician) }}" class="btn btn-sm btn-info-st">
                                            <i class="bi bi-arrow-left-right me-1"></i>Devoluciones
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
