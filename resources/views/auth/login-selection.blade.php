@extends('layouts.app')

@section('content')
    <div class="text-center mb-4">
        <h2 class="fw-semibold mb-1 text-primary">Selecciona tu perfil</h2>
        <p class="st-muted">Elige el tipo de usuario para iniciar sesión en el sistema.</p>
    </div>

    <div class="row g-4 justify-content-center">
        {{-- Perfil: Técnico --}}
        <div class="col-12 col-md-4 col-lg-3">
            <div class="st-card h-100 text-center p-4 d-flex flex-column">
                <h5 class="fw-semibold mb-2">Técnico</h5>
                <p class="st-muted flex-grow-1">
                    Accede a tu panel para gestionar tu inventario y tus órdenes de trabajo.
                </p>
                <a href="{{ route('login.role', 'technician') }}" class="btn btn-st w-100 mt-2">
                    Entrar como Técnico
                </a>
            </div>
        </div>

        {{-- Perfil: Logística --}}
        <div class="col-12 col-md-4 col-lg-3">
            <div class="st-card h-100 text-center p-4 d-flex flex-column">
                <h5 class="fw-semibold mb-2">Logística</h5>
                <p class="st-muted flex-grow-1">
                    Gestiona transferencias, materiales y existencias del almacén principal.
                </p>
                <a href="{{ route('login.role', 'logistics') }}" class="btn btn-outline-st w-100 mt-2">
                    Entrar como Logística
                </a>
            </div>
        </div>

        {{-- Perfil: Administrador --}}
        <div class="col-12 col-md-4 col-lg-3">
            <div class="st-card h-100 text-center p-4 d-flex flex-column">
                <h5 class="fw-semibold mb-2">Administrador</h5>
                <p class="st-muted flex-grow-1">
                    Supervisa usuarios, materiales, transferencias y órdenes del sistema.
                </p>
                <a href="{{ route('login.role', 'admin') }}" class="btn btn-outline-dark w-100 mt-2">
                    Entrar como Administrador
                </a>
            </div>
        </div>
    </div>
@endsection
