@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card st-card">
                <div class="card-header fw-semibold"><i class="bi bi-person-circle me-2"></i>Perfil</div>
                <div class="card-body">
                    <dl class="row mb-4">
                        <dt class="col-sm-4">Nombre</dt>
                        <dd class="col-sm-8">{{ $user->name }}</dd>

                        <dt class="col-sm-4">Correo</dt>
                        <dd class="col-sm-8">{{ $user->email }}</dd>

                        <dt class="col-sm-4">Código</dt>
                        <dd class="col-sm-8">{{ $user->tech_code ?? 'No aplica' }}</dd>
                    </dl>

                    <div class="d-grid">
                        <a href="{{ route('profile.edit') }}" class="btn btn-success-st"><i class="bi bi-pencil-square me-1"></i>Cambiar contraseña</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
