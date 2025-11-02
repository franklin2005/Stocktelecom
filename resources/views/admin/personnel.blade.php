@extends('layouts.app')

@php
    $tabs = [
        'technicians'    => 'Técnicos',
        'logistics'      => 'Logística',
        'admins'         => 'Administradores',
        'super_admins'   => 'Superadministradores',
    ];
@endphp

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1">Gestión de personal</h1>
            <p class="st-muted mb-0">Administra técnicos, logística, administradores y superadministradores según tus permisos.</p>
        </div>
    </div>

    @if ($errors->getBag('deleteStaff')->has('general'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ $errors->getBag('deleteStaff')->first('general') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    <div class="st-card p-2 mb-3">
        <ul class="nav nav-pills flex-wrap">
            @foreach ($tabs as $tabKey => $tabLabel)
                @php
                    $active = $activeTab === $tabKey;
                @endphp
                <li class="nav-item me-2 mb-2">
                    <a
                        href="{{ route('admin.personnel', ['tab' => $tabKey]) }}"
                        class="nav-link {{ $active ? 'active' : '' }}"
                        @if($active) aria-current="page" @endif
                    >
                        {{ $tabLabel }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

    @if ($activeTab === 'technicians')
        @include('admin.personnel.tabs.technicians')
    @elseif ($activeTab === 'logistics')
        @include('admin.personnel.tabs.logistics')
    @elseif ($activeTab === 'admins')
        @include('admin.personnel.tabs.admins')
    @elseif ($activeTab === 'super_admins')
        @include('admin.personnel.tabs.super_admins')
    @endif
@endsection
