@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-person-fill-gear me-2"></i>Histórico de acciones de usuarios</h1>
            <p class="st-muted mb-0">
                Registros de creación, edición y eliminación realizados por el personal autorizado.
            </p>
        </div>
    </div>

    <form method="GET" class="st-card p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Desde</label>
                <input type="date" name="from" value="{{ $from ?? '' }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Hasta</label>
                <input type="date" name="to" value="{{ $to ?? '' }}" class="form-control">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-st">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <a href="{{ route('admin.user-history') }}" class="btn btn-danger-st">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar
                </a>
            </div>
        </div>
    </form>

    <div class="st-card p-3">
        @if ($logs->isEmpty())
            <p class="st-muted mb-0">Todavía no se han registrado acciones sobre usuarios.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Acción</th>
                            <th>Usuario afectado</th>
                            <th>Realizado por</th>
                            <th>Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            @php
                                $actionLabels = [
                                    'created' => 'CREADO',
                                    'updated' => 'ACTUALIZADO',
                                    'deleted' => 'ELIMINADO',
                                ];

                                $badgeClasses = [
                                    'created' => 'badge-success-soft',
                                    'updated' => 'badge-warning-soft',
                                    'deleted' => 'badge-danger-soft',
                                ];

                                $actionLabel = $actionLabels[$log->action] ?? ucfirst($log->action);
                                $badgeClass  = $badgeClasses[$log->action] ?? 'badge-soft';
                            @endphp

                            <tr>
                                <td>{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <span class="badge {{ $badgeClass }}">
                                        {{ $actionLabel }}
                                    </span>
                                </td>
                                <td>
                                    {{ optional($log->target)->name ?? 'Usuario eliminado' }}
                                    @if (optional($log->target)->email)
                                        <br>
                                        <small class="st-muted">{{ optional($log->target)->email }}</small>
                                    @endif
                                </td>
                                <td>
                                    {{ optional($log->actor)->name ?? 'Sistema' }}
                                    @if (optional($log->actor)->email)
                                        <br>
                                        <small class="st-muted">{{ optional($log->actor)->email }}</small>
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
@endsection
