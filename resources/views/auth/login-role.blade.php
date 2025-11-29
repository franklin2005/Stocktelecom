@extends('layouts.app')

@section('content')
    <div class="st-card mx-auto p-4" style="max-width: 420px;">
        <h4 class="mb-3 text-center fw-semibold text-primary"><i class="bi bi-person-circle me-2"></i>{{ $title }}</h4>

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Ocurrió un error:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}">
            @csrf
            <input type="hidden" name="role_key" value="{{ $roleKey }}">

            <div class="mb-3">
                <label for="email" class="form-label">Correo electrónico</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    class="form-control @error('email') is-invalid @enderror"
                    required
                    autofocus
                >
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control @error('password') is-invalid @enderror"
                    required
                >
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label" for="remember">Recordarme</label>
            </div>

            <button type="submit" class="btn btn-st w-100"><i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión</button>
        </form>

        <div class="mt-3 text-center">
            <a href="{{ route('login') }}" class="small text-decoration-none st-muted">
                &larr; Volver a la selección de perfil
            </a>
        </div>
    </div>
@endsection
