@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="mb-1">Panel de tecnico</h2>
            <p class="text-muted mb-0">Gestiona tu stock y tus ordenes de trabajo.</p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Mi stock</h5>
                    <p class="card-text text-muted">Visualiza los materiales asignados a tu inventario.</p>
                    <a href="{{ route('technician.stock') }}" class="btn btn-outline-success">Ver mi stock</a>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Transferencias</h5>
                    <p class="card-text text-muted">Transfiere materiales a otros tecnicos y acepta recepciones.</p>
                    <a href="{{ route('technician.transfers') }}" class="btn btn-outline-success">Gestionar transferencias</a>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Ordenes de trabajo</h5>
                    <p class="card-text text-muted">Crea y consulta ordenes de trabajo asociadas a tus instalaciones.</p>
                    <a href="{{ route('technician.work-orders') }}" class="btn btn-outline-success">Ver ordenes</a>
                </div>
            </div>
        </div>
    </div>
@endsection
