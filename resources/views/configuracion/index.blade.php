@extends('layouts.app')

@section('content')
<div class="config-page">
    <div class="config-head">
        <div>
            <div class="breadcrumb">Inicio <span>›</span> Configuración General</div>
            <h1>Configuración General</h1>
            <p>Administra la identidad, apariencia y parámetros generales del sistema.</p>
        </div>
        <div class="config-head-badge">Solo Administrador</div>
    </div>

    <form method="POST" action="{{ route('configuracion.update') }}" enctype="multipart/form-data" id="configForm">
        @csrf
        @if($errors->any())
            <div class="config-alert config-alert-error">{{ $errors->first() }}</div>
        @endif
        @if(session('success'))
            <div class="config-alert config-alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="config-alert config-alert-error">{{ session('error') }}</div>
        @endif

        <section class="config-card">
            <div class="config-card-title"><span>▣</span><div><h2>Identidad del sistema</h2><p>Estos datos se utilizan en el encabezado y en la pantalla de ingreso.</p></div></div>
            <div class="config-grid config-grid-3">
                <label><span>Nombre del sistema <b>*</b></span><input name="nombre_sistema" value="{{ old('nombre_sistema',$config['nombre_sistema'] ?? 'Sistema Electoral') }}" required maxlength="150"></label>
                <label><span>Institución</span><input name="institucion" value="{{ old('institucion',$config['institucion'] ?? '') }}" maxlength="200"></label>
                <label><span>Periodo electoral</span><input name="periodo_electoral" value="{{ old('periodo_electoral',$config['periodo_electoral'] ?? '') }}" maxlength="100" placeholder="Ej. Elecciones Regionales y Municipales 2026"></label>
            </div>
        </section>

        <section class="config-card">
            <div class="config-card-title"><span>◉</span><div><h2>Logo</h2><p>El mismo logo se utilizará en el encabezado y en la pantalla de login.</p></div></div>
            <div class="logo-config-row">
                <div class="logo-preview">
                    @if(!empty($config['logo_path']))<img src="{{ asset($config['logo_path']) }}" alt="Logo actual">@else<div class="logo-empty">Sin logo</div>@endif
                </div>
                <div class="logo-config-fields">
                    <label><span>Nuevo logo</span><input type="file" name="logo" accept="image/jpeg,image/png,image/webp"></label>
                    <small>JPG, PNG o WEBP. Tamaño máximo: 5 MB.</small>
                    @if(!empty($config['logo_path']))
                        <button type="button" class="config-danger-link" id="removeLogoBtn">Eliminar logo actual</button>
                    @endif
                </div>
            </div>
        </section>

        <section class="config-card">
            <div class="config-card-title"><span>◐</span><div><h2>Tema y apariencia</h2><p>Personaliza los colores de la interfaz sin modificar el código.</p></div></div>
            <div class="theme-presets">
                <button type="button" class="theme-preset" data-theme="#f28c28,#e87d15,#20242b,#18202d,#101722,#fff3e8">Tema actual</button>
                <button type="button" class="theme-preset" data-theme="#198754,#146c43,#7a1f2b,#24412f,#183021,#eef9f1">Verde / rojo / blanco</button>
                <button type="button" class="theme-preset" data-theme="#2874c6,#1f5d9c,#172b45,#1b3048,#122438,#edf4fc">Azul institucional</button>
            </div>
            <div class="config-grid config-grid-3">
                <label><span>Color principal</span><input type="color" name="theme_primary" value="{{ old('theme_primary',$config['theme_primary'] ?? '#f28c28') }}"></label>
                <label><span>Color principal oscuro</span><input type="color" name="theme_primary_dark" value="{{ old('theme_primary_dark',$config['theme_primary_dark'] ?? '#e87d15') }}"></label>
                <label><span>Color barra superior</span><input type="color" name="theme_topbar" value="{{ old('theme_topbar',$config['theme_topbar'] ?? '#20242b') }}"></label>
                <label><span>Color inicial del menú</span><input type="color" name="theme_sidebar" value="{{ old('theme_sidebar',$config['theme_sidebar'] ?? '#18202d') }}"></label>
                <label><span>Color final del menú</span><input type="color" name="theme_sidebar_end" value="{{ old('theme_sidebar_end',$config['theme_sidebar_end'] ?? '#101722') }}"></label>
                <label><span>Color suave</span><input type="color" name="theme_soft" value="{{ old('theme_soft',$config['theme_soft'] ?? '#fff3e8') }}"></label>
            </div>
            <div class="theme-preview" id="themePreview"><div class="preview-top"></div><div class="preview-side"></div><div class="preview-body"><div class="preview-card"></div><div class="preview-button"></div></div></div>
        </section>

        <section class="config-card">
            <div class="config-card-title"><span>◷</span><div><h2>Fecha y zona horaria</h2><p>Parámetros de presentación temporal del sistema.</p></div></div>
            <div class="config-grid config-grid-3">
                <label><span>Zona horaria</span><select name="zona_horaria">@foreach(DateTimeZone::listIdentifiers() as $tz)<option value="{{ $tz }}" @selected(old('zona_horaria',$config['zona_horaria'] ?? 'America/Lima')===$tz)>{{ $tz }}</option>@endforeach</select></label>
                <label><span>Formato de fecha</span><input name="formato_fecha" value="{{ old('formato_fecha',$config['formato_fecha'] ?? 'DD/MM/YYYY') }}"></label>
                <label><span>Formato fecha y hora</span><input name="formato_fecha_hora" value="{{ old('formato_fecha_hora',$config['formato_fecha_hora'] ?? 'DD/MM/YYYY HH:MM:SS') }}"></label>
            </div>
        </section>

        <section class="config-card">
            <div class="config-card-title"><span>🔐</span><div><h2>Seguridad</h2><p>Parámetros que controlan el acceso al sistema.</p></div></div>
            <div class="config-grid config-grid-4">
                <label><span>Máximo de intentos</span><input type="number" name="max_intentos_login" min="1" max="20" value="{{ old('max_intentos_login',$config['max_intentos_login'] ?? 5) }}"></label>
                <label><span>Bloqueo (minutos)</span><input type="number" name="minutos_bloqueo_login" min="1" max="1440" value="{{ old('minutos_bloqueo_login',$config['minutos_bloqueo_login'] ?? 15) }}"></label>
                <label><span>Expiración contraseña (días)</span><input type="number" name="dias_expiracion_password" min="0" max="3650" value="{{ old('dias_expiracion_password',$config['dias_expiracion_password'] ?? 0) }}"></label>
                <label><span>Tamaño máximo de archivo (MB)</span><input type="number" name="max_tamano_archivo_mb" min="1" max="1000" value="{{ old('max_tamano_archivo_mb',$config['max_tamano_archivo_mb'] ?? 10) }}"></label>
            </div>
            <small class="config-help">Si la expiración está en 0, la contraseña no expira automáticamente.</small>
        </section>

        <section class="config-card">
            <div class="config-card-title"><span>▤</span><div><h2>Resultados</h2><p>Parámetros de presentación de porcentajes y resultados.</p></div></div>
            <div class="config-grid config-grid-3">
                <label><span>Decimales en resultados</span><input type="number" name="decimales_resultados" min="0" max="6" value="{{ old('decimales_resultados',$config['decimales_resultados'] ?? 2) }}"></label>
            </div>
        </section>

        <div class="config-actions">
            <button type="button" class="config-reset" id="openResetBtn">
                <span aria-hidden="true">↺</span> Puesta en Cero
            </button>
            <button type="submit" class="config-save">Guardar configuración</button>
        </div>
    </form>


    <div class="config-modal-backdrop" id="resetModal" hidden>
        <div class="config-reset-modal" role="dialog" aria-modal="true" aria-labelledby="resetModalTitle">
            <button type="button" class="config-modal-close" id="closeResetBtn" aria-label="Cerrar">×</button>
            <div class="config-reset-icon">!</div>
            <h2 id="resetModalTitle">Puesta en Cero</h2>
            <p class="config-reset-lead">Esta acción eliminará <strong>todas las actas digitadas</strong> de <code>digitacion_acta</code> y reiniciará su correlativo para que la próxima acta empiece en <strong>1</strong>.</p>
            <div class="config-reset-warning">
                <strong>Se conservarán:</strong>
                regiones, provincias, distritos, locales, mesas, partidos políticos, personas, personeros, usuarios, roles, configuración y auditoría.
            </div>
            <p class="config-reset-confirm-label">Para continuar, escriba exactamente:</p>
            <div class="config-reset-confirm-code">PUESTA EN CERO</div>
            <input type="text" id="resetConfirmInput" class="config-reset-input" autocomplete="off" spellcheck="false" placeholder="Escriba PUESTA EN CERO">
            <form method="POST" action="{{ route('configuracion.puesta-cero') }}" id="resetForm">
                @csrf
                <input type="hidden" name="confirmacion" id="resetConfirmValue">
                <div class="config-reset-actions">
                    <button type="button" class="config-cancel" id="cancelResetBtn">Cancelar</button>
                    <button type="submit" class="config-reset-submit" id="confirmResetBtn" disabled>Poner en Cero</button>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('configuracion.logo.remove') }}" id="removeLogoForm" hidden>@csrf @method('DELETE')</form>
</div>

<script>
(() => {
    const form = document.getElementById('configForm');
    const fields = ['theme_primary','theme_primary_dark','theme_topbar','theme_sidebar','theme_sidebar_end','theme_soft'];
    const preview = document.getElementById('themePreview');
    const setTheme = (values) => {
        fields.forEach((name,i) => { const el=form?.querySelector(`[name="${name}"]`); if(el && values[i]) el.value=values[i]; });
        updatePreview();
    };
    const updatePreview = () => {
        if(!form || !preview) return;
        const v = Object.fromEntries(fields.map(n => [n, form.querySelector(`[name="${n}"]`)?.value || '']));
        preview.style.setProperty('--preview-primary',v.theme_primary);
        preview.style.setProperty('--preview-dark',v.theme_primary_dark);
        preview.style.setProperty('--preview-top',v.theme_topbar);
        preview.style.setProperty('--preview-side',v.theme_sidebar);
        preview.style.setProperty('--preview-side-end',v.theme_sidebar_end);
    };
    document.querySelectorAll('.theme-preset').forEach(btn => btn.addEventListener('click', () => setTheme(btn.dataset.theme.split(','))));
    fields.forEach(n => form?.querySelector(`[name="${n}"]`)?.addEventListener('input',updatePreview));
    updatePreview();
    document.getElementById('removeLogoBtn')?.addEventListener('click', () => {
        if(confirm('¿Desea eliminar el logo actual?')) document.getElementById('removeLogoForm').submit();
    });

    const resetModal = document.getElementById('resetModal');
    const resetInput = document.getElementById('resetConfirmInput');
    const resetValue = document.getElementById('resetConfirmValue');
    const resetSubmit = document.getElementById('confirmResetBtn');
    const openReset = () => {
        if (!resetModal) return;
        resetModal.hidden = false;
        document.body.classList.add('config-modal-open');
        resetInput?.focus();
    };
    const closeReset = () => {
        if (!resetModal) return;
        resetModal.hidden = true;
        document.body.classList.remove('config-modal-open');
        if (resetInput) resetInput.value = '';
        if (resetValue) resetValue.value = '';
        if (resetSubmit) resetSubmit.disabled = true;
    };
    document.getElementById('openResetBtn')?.addEventListener('click', openReset);
    document.getElementById('closeResetBtn')?.addEventListener('click', closeReset);
    document.getElementById('cancelResetBtn')?.addEventListener('click', closeReset);
    resetInput?.addEventListener('input', () => {
        const ok = resetInput.value.trim() === 'PUESTA EN CERO';
        if (resetValue) resetValue.value = resetInput.value.trim();
        if (resetSubmit) resetSubmit.disabled = !ok;
    });
    resetModal?.addEventListener('click', (e) => {
        if (e.target === resetModal) closeReset();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && resetModal && !resetModal.hidden) closeReset();
    });
})();
</script>
@endsection
