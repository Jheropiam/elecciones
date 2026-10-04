@extends('layouts.app')

@section('content')
<div class="change-password-page">
    <div class="login-card change-password-card">
        <div class="change-password-header">
            <div class="change-password-icon" aria-hidden="true">🔒</div>
            <div>
                <h1>Cambiar contraseña</h1>
                <p>Por seguridad debe cambiar la contraseña inicial.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('password.change.post') }}">
            @csrf

            <div class="change-password-field">
                <label for="current_password">Contraseña actual</label>
                <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
            </div>

            <div class="change-password-field">
                <label for="password">Nueva contraseña</label>
                <input id="password" type="password" name="password" minlength="8" required autocomplete="new-password">
            </div>

            <div class="change-password-field">
                <label for="password_confirmation">Confirmar nueva contraseña</label>
                <input id="password_confirmation" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password">
            </div>

            <button class="primary change-password-submit" type="submit">
                GUARDAR CONTRASEÑA
            </button>
        </form>
    </div>
</div>
@endsection
