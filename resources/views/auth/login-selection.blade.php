@extends('layouts.app')

@section('content')
    <div class="text-center mb-4">
        <h2 class="fw-semibold mb-1 text-primary"><i class="bi bi-person-circle me-2"></i>Selecciona tu perfil</h2>
        <p class="st-muted">Elige el tipo de usuario para iniciar sesión en el sistema.</p>
    </div>

    <div class="row g-4 justify-content-center">
        {{-- Perfil: Técnico --}}
        <div class="col-12 col-md-6 col-xl-4">
            <div class="st-card h-100 text-center p-4 p-xl-5 d-flex flex-column role-card">
                <h5 class="fw-semibold mb-2 text-wrap">
                    <i class="bi bi-tools me-2"></i>
                    Técnico
                </h5>
                <p class="st-muted flex-grow-1 overflow-hidden">
                    Perfil de Técnico.
                </p>
                <a href="{{ route('login.role', 'technician') }}" class="btn btn-st w-100 mt-2 flex-shrink-0 role-btn">
                    <i class="bi bi-box-arrow-in-right me-1"></i>
                    Entrar
                </a>
            </div>
        </div>

        {{-- Perfil: Logística --}}
        <div class="col-12 col-md-6 col-xl-4">
            <div class="st-card h-100 text-center p-4 p-xl-5 d-flex flex-column role-card">
                <h5 class="fw-semibold mb-2 text-wrap">
                    <i class="bi bi-box-seam me-2"></i>
                    Logística
                </h5>
                <p class="st-muted flex-grow-1 overflow-hidden">
                    Perfil de Logística.
                </p>
                <a href="{{ route('login.role', 'logistics') }}" class="btn btn-st w-100 mt-2 flex-shrink-0 role-btn">
                    <i class="bi bi-box-arrow-in-right me-1"></i>
                    Entrar
                </a>
            </div>
        </div>

        {{-- Perfil: Administrador --}}
        <div class="col-12 col-md-6 col-xl-4">
            <div class="st-card h-100 text-center p-4 p-xl-5 d-flex flex-column role-card">
                <h5 class="fw-semibold mb-2 text-wrap">
                    <i class="bi bi-shield-check me-2"></i>
                    Admin
                </h5>
                <p class="st-muted flex-grow-1 overflow-hidden">
                    Perfil de Administrador.
                </p>
                <a href="{{ route('login.role', 'admin') }}" class="btn btn-st w-100 mt-2 flex-shrink-0 role-btn">
                    <i class="bi bi-box-arrow-in-right me-1"></i>
                    Entrar
                </a>
            </div>
        </div>
    </div>
@endsection
