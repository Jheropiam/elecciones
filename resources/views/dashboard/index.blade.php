@extends('layouts.app')

@section('content')
<div class="dashboard-page dashboard-page-compact">
    <section class="welcome-section">
        <h1>Panel principal</h1>
        <p>Bienvenido, <strong>{{ $user->nombres }} {{ $user->apellido_paterno }} {{ $user->apellido_materno }}</strong>.</p>

        <div class="role-banner">
            <span class="role-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 12a4.2 4.2 0 1 0 0-8.4 4.2 4.2 0 0 0 0 8.4Zm0 2c-4.7 0-8.5 2.5-8.5 5.6V21h17v-1.4c0-3.1-3.8-5.6-8.5-5.6Z"/></svg>
            </span>
            <span>Rol: <strong>{{ $roles->implode(', ') }}</strong></span>
        </div>
    </section>

    @php
        $systemName = $config['nombre_sistema'] ?? 'Sistema Electoral';
        $institution = trim((string)($config['institucion'] ?? ''));
        $period = trim((string)($config['periodo_electoral'] ?? ''));
        $logoPath = trim((string)($config['logo_path'] ?? ''));
        $logoSrc = $logoPath ?: 'uploads/configuracion/logo_sistema_default.png';
    @endphp

    <section class="election-hero election-hero-dynamic" aria-label="Identidad del sistema">
        <div class="hero-branding">
            <div class="hero-logo-wrap">
                <img
                    src="{{ asset($logoSrc) }}"
                    alt="Logo de {{ $systemName }}"
                    class="hero-logo"
                    onerror="this.onerror=null;this.src='{{ asset('uploads/configuracion/logo_sistema_default.png') }}';"
                >
            </div>
            <div class="hero-title">
                <h2>{{ $systemName }}</h2>
                @if($institution)
                    <h3>{{ $institution }}</h3>
                @endif
                @if($period)
                    <p class="hero-period">{{ $period }}</p>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
