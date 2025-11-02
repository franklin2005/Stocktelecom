@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1">Panel del técnico</h1>
            <p class="st-muted mb-0">Accede a tu inventario, gestiona transferencias y controla tus órdenes de trabajo.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-md-6 col-lg-4">
            <div class="st-card p-3 h-100">
                <h5 class="card-title">Mi stock</h5>
                <p class="st-muted flex-grow-1 mb-3">
                    Consulta los materiales actualmente asignados a tu inventario personal.
                </p>
                <a href="{{ route('technician.stock') }}" class="btn btn-st w-100">Ver mi stock</a>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
            <div class="st-card p-3 h-100">
                <h5 class="card-title">Transferencias</h5>
                <p class="st-muted flex-grow-1 mb-3">
                    Envía materiales a otros técnicos o acepta recepciones pendientes.
                </p>
                <a href="{{ route('technician.transfers') }}" class="btn btn-st w-100">Gestionar transferencias</a>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
            <div class="st-card p-3 h-100">
                <h5 class="card-title">Órdenes de trabajo</h5>
                <p class="st-muted flex-grow-1 mb-3">
                    Crea, consulta y actualiza tus órdenes de trabajo activas o completadas.
                </p>
                <a href="{{ route('technician.work-orders') }}" class="btn btn-st w-100">Ver órdenes</a>
            </div>
        </div>
    </div>
@endsection
