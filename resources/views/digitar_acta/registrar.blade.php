@extends('layouts.app')

@section('content')
<div class="page digitar-acta-page">
    <div class="page-heading">
        <div>
            <div class="breadcrumb">Digitación de Actas <span>›</span> Registrar Acta</div>
            <h1>Registrar Acta</h1>
            <p class="page-subtitle">Busque la mesa por su número de seis dígitos y seleccione el tipo de acta a registrar.</p>
        </div>
    </div>

    <section class="module-card">
        <div class="module-card-header">
            <div>
                <h2>Registro de Actas</h2>
                <p>La búsqueda de mesa se valida automáticamente al completar los 6 dígitos.</p>
            </div>
            <button type="button" class="btn btn-primary" id="openBuscarMesa">Registrar nueva acta</button>
        </div>
    </section>
</div>

<div id="buscarMesaModal" class="acta-modal-backdrop" aria-hidden="false">
    <div class="acta-modal acta-modal-search" role="dialog" aria-modal="true" aria-labelledby="buscarMesaTitle">
        <div class="acta-modal-header">
            <h3 id="buscarMesaTitle">Buscar mesa</h3>
            <button type="button" class="acta-modal-close" id="closeBuscarMesa" aria-label="Cerrar">×</button>
        </div>

        <div class="acta-modal-body">
            <label class="acta-field-label" for="numeroMesaInput">NÚMERO DE MESA <span>*</span></label>

            <div class="mesa-search-box" id="mesaSearchBox">
                <span class="mesa-search-icon" aria-hidden="true">⌕</span>
                <input
                    id="numeroMesaInput"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    autocomplete="off"
                    placeholder="Ej: 065249"
                    aria-describedby="mesaValidationMessage"
                >
                <span class="mesa-validation-icon" id="mesaValidationIcon" aria-hidden="true"></span>
            </div>

            <div id="mesaValidationMessage" class="mesa-validation-message" aria-live="polite"></div>

            <div id="tipoActaStep" class="tipo-acta-step hidden">
                <div class="acta-field-label tipo-label">¿QUÉ ACTA DESEA REGISTRAR?</div>

                <div class="acta-type-options">
                    <label class="acta-type-option" data-type="1">
                        <input type="radio" name="tipo_acta_modal" value="1">
                        <span class="radio-visual"></span>
                        <span class="type-icon">◷</span>
                        <span class="type-text">
                            <strong>Acta Regional</strong>
                            <small>Gobernador, Vicegobernador y Consejero Regional</small>
                        </span>
                        <span class="type-status" hidden></span>
                    </label>

                    <label class="acta-type-option" data-type="2">
                        <input type="radio" name="tipo_acta_modal" value="2">
                        <span class="radio-visual"></span>
                        <span class="type-icon">◷</span>
                        <span class="type-text">
                            <strong>Acta Municipal</strong>
                            <small>Alcalde Provincial y Alcalde Distrital</small>
                        </span>
                        <span class="type-status" hidden></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="acta-modal-footer">
            <button type="button" class="acta-modal-cancel" id="cancelBuscarMesa">Cancelar</button>
            <button type="button" class="btn btn-primary acta-continue-btn" id="continueBuscarMesa" disabled>
                Continuar <span aria-hidden="true">→</span>
            </button>
        </div>
    </div>
</div>

<script>
(() => {
    const modal = document.getElementById('buscarMesaModal');
    const openButton = document.getElementById('openBuscarMesa');
    const closeButton = document.getElementById('closeBuscarMesa');
    const cancelButton = document.getElementById('cancelBuscarMesa');
    const input = document.getElementById('numeroMesaInput');
    const box = document.getElementById('mesaSearchBox');
    const icon = document.getElementById('mesaValidationIcon');
    const message = document.getElementById('mesaValidationMessage');
    const typeStep = document.getElementById('tipoActaStep');
    const continueButton = document.getElementById('continueBuscarMesa');
    const typeOptions = Array.from(document.querySelectorAll('.acta-type-option'));

    let mesaData = null;
    let lookupController = null;
    let lookupTimer = null;

    const resetModal = () => {
        mesaData = null;
        if (lookupController) lookupController.abort();
        input.value = '';
        box.classList.remove('is-valid', 'is-invalid', 'is-loading');
        icon.textContent = '';
        icon.className = 'mesa-validation-icon';
        message.textContent = '';
        message.className = 'mesa-validation-message';
        typeStep.classList.add('hidden');
        typeOptions.forEach(option => {
            option.classList.remove('selected', 'disabled-option');
            const radio = option.querySelector('input');
            radio.checked = false;
            radio.disabled = false;
            const status = option.querySelector('.type-status');
            status.hidden = true;
            status.textContent = '';
        });
        continueButton.disabled = true;
    };

    const openModal = () => {
        resetModal();
        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
        window.setTimeout(() => input.focus(), 50);
    };

    const closeModal = () => {
        modal.classList.remove('is-visible');
        modal.setAttribute('aria-hidden', 'true');
    };

    const showMessage = (text, type) => {
        message.textContent = text || '';
        message.className = 'mesa-validation-message ' + (type || '');
    };

    const setLoading = () => {
        box.classList.remove('is-valid', 'is-invalid');
        box.classList.add('is-loading');
        icon.textContent = '…';
        icon.className = 'mesa-validation-icon loading';
        showMessage('Validando mesa…', 'loading');
        typeStep.classList.add('hidden');
        continueButton.disabled = true;
    };

    const validateMesa = async () => {
        const numero = input.value;

        if (numero.length !== 6) {
            box.classList.remove('is-valid', 'is-invalid', 'is-loading');
            icon.textContent = '';
            showMessage('', '');
            typeStep.classList.add('hidden');
            continueButton.disabled = true;
            mesaData = null;
            return;
        }

        setLoading();

        if (lookupController) lookupController.abort();
        lookupController = new AbortController();

        try {
            const response = await fetch(
                @json(route('digitar-acta.buscar.mesa', ['numero' => '__NUMERO__'])).replace('__NUMERO__', numero),
                {
                    method: 'GET',
                    headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    signal: lookupController.signal
                }
            );

            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Mesa no encontrada.');
            }

            mesaData = data;
            box.classList.remove('is-loading', 'is-invalid');
            box.classList.add('is-valid');
            icon.className = 'mesa-validation-icon valid';
            icon.textContent = '✓';
            showMessage(data.message, 'success');

            typeStep.classList.remove('hidden');

            const registered = (data.registradas || []).map(Number);
            let availableCount = 0;

            typeOptions.forEach(option => {
                const type = Number(option.dataset.type);
                const radio = option.querySelector('input');
                const status = option.querySelector('.type-status');
                const already = registered.includes(type);

                option.classList.toggle('disabled-option', already);
                radio.disabled = already;
                radio.checked = false;

                if (already) {
                    status.hidden = false;
                    status.textContent = 'Ya registrada';
                } else {
                    status.hidden = true;
                    status.textContent = '';
                    availableCount++;
                }
            });

            if (availableCount === 0) {
                showMessage('Mesa encontrada, pero sus dos actas ya fueron registradas.', 'warning');
                continueButton.disabled = true;
            } else {
                showMessage(data.message, 'success');
            }
        } catch (error) {
            if (error.name === 'AbortError') return;

            mesaData = null;
            box.classList.remove('is-loading', 'is-valid');
            box.classList.add('is-invalid');
            icon.className = 'mesa-validation-icon invalid';
            icon.textContent = '!';
            showMessage(error.message || 'No se pudo validar la mesa.', 'error');
            typeStep.classList.add('hidden');
            continueButton.disabled = true;
        }
    };

    input.addEventListener('input', () => {
        input.value = input.value.replace(/\D/g, '').slice(0, 6);
        window.clearTimeout(lookupTimer);
        lookupTimer = window.setTimeout(validateMesa, 40);
    });

    typeOptions.forEach(option => {
        option.addEventListener('click', () => {
            const radio = option.querySelector('input');
            if (radio.disabled) return;

            typeOptions.forEach(item => item.classList.remove('selected'));
            option.classList.add('selected');
            radio.checked = true;
            continueButton.disabled = !mesaData;
        });
    });

    continueButton.addEventListener('click', () => {
        if (!mesaData) return;

        const selected = document.querySelector('input[name="tipo_acta_modal"]:checked');
        if (!selected) return;

        const numero = mesaData.mesa.numero_mesa;
        const tipo = selected.value;
        window.location.href = @json(route('digitar-acta.formulario', ['numero' => '__NUMERO__', 'tipo' => '__TIPO__']))
            .replace('__NUMERO__', encodeURIComponent(numero))
            .replace('__TIPO__', tipo);
    });

    [openButton, closeButton, cancelButton].forEach(button => {
        button?.addEventListener('click', () => {
            if (button === openButton) {
                openModal();
            } else {
                closeModal();
            }
        });
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-visible')) {
            closeModal();
        }
    });

    openModal();
})();
</script>
@endsection
