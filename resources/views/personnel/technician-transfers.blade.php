@extends('layouts.app')

@php
    $role = $currentUser->role ?? null;
    $isSelf = $currentUser->id === $technician->id;
    $canVisitPersonnel = in_array($role, ['admin', 'super_admin'], true);
    $backUrl = $canVisitPersonnel
        ? route('admin.personnel', ['tab' => 'technicians'])
        : ($isSelf ? route('technician.transfers') : url()->previous());
@endphp

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="mb-1">Historial de transferencias</h2>
            <p class="text-muted mb-0">
                {{ $technician->name }} · {{ $technician->email }}
                @if ($technician->tech_code)
                    · Código: {{ $technician->tech_code }}
                @endif
                <br>
                <small class="text-muted">Ubicación: {{ $location->name }}</small>
            </p>
        </div>
        <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm">Volver</a>
    </div>

    <form method="GET" class="card shadow-sm mb-4">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Desde</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Hasta</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="{{ route(Route::currentRouteName(), $technician) }}" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($transfers->isEmpty())
                <p class="text-muted mb-0">Aún no se han registrado transferencias para este técnico.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Orden</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Detalle</th>
                                <th>Iniciada por</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transfers as $transfer)
                                <tr>
                                    <td>{{ $transfer->order_number }}</td>
                                    <td>{{ $transfer->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <span class="badge bg-{{ $transfer->status === 'accepted' ? 'success' : ($transfer->status === 'pending' ? 'warning text-dark' : 'danger') }}">
                                            {{ ucfirst($transfer->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $transfer->fromLocation->name ?? '—' }}</td>
                                    <td>{{ $transfer->toLocation->name ?? '—' }}</td>
                                    <td>
                                        <ul class="mb-0 ps-3">
                                            @foreach ($transfer->items as $item)
                                                <li>
                                                    {{ ucfirst($item->material->type) }}
                                                    @if ($item->material->model)
                                                        - {{ $item->material->model }}
                                                    @endif
                                                    @if ($item->material_serial_id && $item->serial)
                                                        (Serie: {{ $item->serial->serial_number }})
                                                    @else
                                                        (Cantidad: {{ $item->quantity }})
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </td>
                                    <td>{{ $transfer->initiator->name ?? 'Sistema' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $transfers->links() }}
            @endif
        </div>
    </div>
@endsection