@extends('layouts.app')

@section('content')
<div class="login-page">
    @php
        $loginConfig = \App\Support\SystemConfig::all();
    @endphp

    <section class="login-card" aria-labelledby="login-title">
        <div class="login-brand">
            @if(!empty($loginConfig['logo_path']))
                <img src="{{ asset($loginConfig['logo_path']) }}" alt="Logo del sistema">
            @endif
            <strong>{{ $loginConfig['nombre_sistema'] ?? 'Sistema Electoral' }}</strong>
        </div>

        <div class="login-heading">
            <h1 id="login-title">Ingreso al sistema</h1>
            <p>Ingrese su DNI y contraseña.</p>
        </div>

        <form method="POST" action="{{ route('login.post') }}" class="login-form">
            @csrf

            <div class="login-field">
                <label for="login-dni">DNI</label>
                <input id="login-dni" type="text" name="dni" value="{{ old('dni') }}" inputmode="numeric" autocomplete="username" maxlength="8" required autofocus>
            </div>

            <div class="login-field">
                <label for="login-password">Contraseña</label>
                <input id="login-password" type="password" name="password" autocomplete="current-password" required>
            </div>

            <button type="submit" class="primary login-submit">INGRESAR</button>
        </form>
    </section>
</div>
@endsection
