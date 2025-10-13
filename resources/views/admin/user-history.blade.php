@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="mb-1">Historico de acciones de usuarios</h2>
            <p class="text-muted mb-0">Registros de creacion, edicion y eliminacion realizados por el personal autorizado.</p>
        </div>
    </div>

    <form method="GET" class="card shadow-sm mb-4">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Desde</label>
                <input type="date" name="from" value="{{ $from ?? '' }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Hasta</label>
                <input type="date" name="to" value="{{ $to ?? '' }}" class="form-control">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="{{ route('admin.user-history') }}" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($logs->isEmpty())
                <p class="text-muted mb-0">Todavia no se han registrado acciones sobre usuarios.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Movimiento</th>
                                <th>Usuario afectado</th>
                                <th>Realizado por</th>
                                <th>Detalles</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                @php
                                    $actionLabels = [
                                        'created' => 'Creacion',
                                        'updated' => 'Actualizacion',
                                        'deleted' => 'Eliminacion',
                                    ];

                                    $actionLabel = $actionLabels[$log->action] ?? ucfirst($log->action);
                                @endphp
                                <tr>
                                    <td>{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $actionLabel }}</span>
                                    </td>
                                    <td>
                                        {{ optional($log->target)->name ?? 'Usuario eliminado' }}
                                        @if(optional($log->target)->email)
                                            <br>
                                            <small class="text-muted">{{ optional($log->target)->email }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        {{ optional($log->actor)->name ?? 'Sistema' }}
                                        @if(optional($log->actor)->email)
                                            <br>
                                            <small class="text-muted">{{ optional($log->actor)->email }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $log->details }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
