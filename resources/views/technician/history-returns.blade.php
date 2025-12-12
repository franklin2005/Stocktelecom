@extends('layouts.app')

@php
    $role = $currentUser->role ?? null;
    $isSelf = $currentUser->id === $technician->id;
    $canVisitPersonnel = in_array($role, ['admin', 'super_admin'], true);
    $backUrl = $canVisitPersonnel
        ? route('admin.personnel', ['tab' => 'technicians'])
        : ($isSelf ? route('technician.transfers') : url()->previous());

    $statusMap = [
        'accepted' => ['label' => 'ACEPTADA', 'class' => 'badge-success-soft'],
        'pending' => ['label' => 'PENDIENTE', 'class' => 'badge-warning-soft'],
        'rejected' => ['label' => 'RECHAZADA', 'class' => 'badge-danger-soft'],
        'cancelled' => ['label' => 'CANCELADA', 'class' => 'badge-danger-soft'],
    ];
@endphp

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-arrow-left-right me-2"></i>Histórico de devoluciones</h1>
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

    <form method="GET" class="st-card p-3 mb-4">
        <div class="row g-2 g-lg-3 align-items-end">
            <div class="col-6 col-lg-3">
                <label class="form-label">Desde</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control">
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label">Hasta</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control">
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label">Estado</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($statusMap as $value => $data)
                        <option value="{{ $value }}" @selected(request('status') === $value)>
                         {{ $data['label'] }}
                        </option>
                        @endforeach
                    </select>

            </div>
            <div class="col-12 col-lg-3 d-grid d-lg-flex gap-2">
                <button type="submit" class="btn btn-st"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                <a href="{{ route(Route::currentRouteName(), $technician) }}" class="btn btn-danger-st"><i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar</a>
            </div>
        </div>
    </form>

    <div class="st-card p-3">
        @if ($returns->isEmpty())
            <p class="st-muted mb-0">Aún no se han registrado devoluciones para este técnico.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Destino</th>
                            @php $isAdminView = in_array($currentUser->role, ['admin', 'super_admin', 'logistics'], true); @endphp
                            @if ($isAdminView)
                                <th class="text-end">Acción</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($returns as $return)
                            @php
                                $st = $statusMap[$return->status] ?? ['label' => ucfirst($return->status), 'class' => 'badge-soft'];
                                $detailRoute = $isAdminView
                                    ? route('admin.technicians.returns.history.show', [$technician, $return])
                                    : route('technician.returns.history.show', $return);
                            @endphp
                            <tr class="align-middle"
                                @if (! $isAdminView) style="cursor:pointer;" onclick="window.location='{{ $detailRoute }}'" @endif>
                                <td>{{ $return->created_at->format('d/m/Y H:i') }}</td>
                                <td><span class="badge {{ $st['class'] }}">{{ $st['label'] }}</span></td>
                                <td>{{ $return->toLocation->name ?? '-' }}</td>
                                @if ($isAdminView)
                                    <td class="text-end">
                                        <a href="{{ $detailRoute }}" class="btn btn-sm btn-soft-st">
                                            <i class="bi bi-eye me-1"></i>Ver detalle
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $returns->links() }}
            </div>
        @endif
    </div>
@endsection
