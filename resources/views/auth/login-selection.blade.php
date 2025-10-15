@extends('layouts.app')

@section('content')
    <div class="text-center mb-4">
        <h2 class="mb-1">Selecciona tu perfil</h2>
        <p class="text-muted">Elige el tipo de usuario para iniciar sesión.</p>
    </div>

    <div class="row g-4 justify-content-center">
        <div class="col-12 col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex flex-column align-items-center text-center">
                    <h5 class="card-title">Técnico</h5>
                    <p class="text-muted flex-grow-1">Acceso al panel de técnicos para gestionar tu inventario y órdenes.</p>
                    <a href="{{ route('login.role', 'technician') }}" class="btn btn-primary w-100">Entrar como Técnico</a>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex flex-column align-items-center text-center">
                    <h5 class="card-title">Logística</h5>
                    <p class="text-muted flex-grow-1">Gestiona transferencias y stock del almacén principal.</p>
                    <a href="{{ route('login.role', 'logistics') }}" class="btn btn-outline-primary w-100">Entrar como Logística</a>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex flex-column align-items-center text-center">
                    <h5 class="card-title">Administrador</h5>
                    <p class="text-muted flex-grow-1">Accede al panel de administración para supervisar el sistema.</p>
                    <a href="{{ route('login.role', 'admin') }}" class="btn btn-outline-dark w-100">Entrar como Administrador</a>
                </div>
            </div>
        </div>
    </div>
@endsection
