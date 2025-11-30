@extends('layouts.app')

@section('content')
    <div class="st-card mx-auto p-4" style="max-width: 420px;">
        <h4 class="mb-3 text-center fw-semibold text-primary">
            <i class="bi bi-key me-2"></i>
            Restablecer contraseña
        </h4>

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

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
                <label for="email" class="form-label">Correo electrónico</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email', $email) }}"
                    class="form-control @error('email') is-invalid @enderror"
                    required
                    autocomplete="username"
                >
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Nueva contraseña</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control @error('password') is-invalid @enderror"
                    required
                    autocomplete="new-password"
                >
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    class="form-control @error('password_confirmation') is-invalid @enderror"
                    required
                    autocomplete="new-password"
                >
                @error('password_confirmation')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-st w-100">
                <i class="bi bi-arrow-repeat me-1"></i>
                Restablecer contraseña
            </button>
        </form>

        <div class="mt-3 text-center">
            <a href="{{ route('login') }}" class="small text-decoration-none st-muted">
                &larr; Volver al inicio de sesión
            </a>
        </div>
    </div>
@endsection
