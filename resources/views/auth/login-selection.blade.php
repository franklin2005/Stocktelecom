@extends('layouts.app')

@section('content')
    <div class="text-center mb-4">
        <h2 class="fw-semibold mb-1 text-primary">Selecciona tu perfil</h2>
        <p class="st-muted">Elige el tipo de usuario para iniciar sesión en el sistema.</p>
    </div>

    <div class="row g-4 justify-content-center">
        {{-- Perfil: Técnico --}}
        <div class="col-12 col-md-6 col-xl-4">
            <div class="st-card h-100 text-center p-4 p-xl-5 d-flex flex-column role-card">
                <h5 class="fw-semibold mb-2 text-wrap">Técnico</h5>
                <p class="st-muted flex-grow-1 overflow-hidden">
                    Perfil de Técnico.
                </p>
                <a href="{{ route('login.role', 'technician') }}" class="btn btn-st w-100 mt-2 flex-shrink-0 role-btn">
                    Entrar
                </a>
            </div>
        </div>

        {{-- Perfil: Logística --}}
        <div class="col-12 col-md-6 col-xl-4">
            <div class="st-card h-100 text-center p-4 p-xl-5 d-flex flex-column role-card">
                <h5 class="fw-semibold mb-2 text-wrap">Logística</h5>
                <p class="st-muted flex-grow-1 overflow-hidden">
                    Perfil de Logística.
                </p>
                <a href="{{ route('login.role', 'logistics') }}" class="btn btn-st w-100 mt-2 flex-shrink-0 role-btn">
                    Entrar
                </a>
            </div>
        </div>

        {{-- Perfil: Administrador --}}
        <div class="col-12 col-md-6 col-xl-4">
            <div class="st-card h-100 text-center p-4 p-xl-5 d-flex flex-column role-card">
                <h5 class="fw-semibold mb-2 text-wrap">Administrador</h5>
                <p class="st-muted flex-grow-1 overflow-hidden">
                    Perfil de Administrador.
                </p>
                <a href="{{ route('login.role', 'admin') }}" class="btn btn-st w-100 mt-2 flex-shrink-0 role-btn">
                    Entrar
                </a>
            </div>
        </div>
    </div>
@endsection
