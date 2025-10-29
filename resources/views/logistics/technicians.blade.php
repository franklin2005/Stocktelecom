@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h2 class="mb-0">Técnicos</h2>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($technicians->isEmpty())
                <p class="text-muted mb-0">Aún no hay técnicos registrados.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
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
                                        <span class="badge bg-secondary">{{ $technician->tech_code ?? 'Pendiente' }}</span>
                                    </td>
                                    <td>{{ $technician->stockLocation->name ?? 'Sin ubicación' }}</td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="{{ route('admin.technicians.stock.overview', $technician) }}" class="btn btn-sm btn-outline-primary">
                                                Ver stock
                                            </a>
                                            <a href="{{ route('admin.technicians.transfers.history', $technician) }}" class="btn btn-sm btn-outline-secondary">
                                                Transferencias
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
    </div>
@endsection
