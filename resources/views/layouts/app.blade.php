<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $systemConfig = \App\Support\SystemConfig::all()
    @endphp
    @php
        $systemName = $systemConfig['nombre_sistema'] ?? 'Sistema Electoral'
    @endphp
    @php
        $logoPath = $systemConfig['logo_path'] ?? ''
    @endphp
    <title>{{ $title ?? $systemName }}</title>
    @if($logoPath)
        <link rel="icon" href="{{ asset($logoPath) }}">
        <link rel="apple-touch-icon" href="{{ asset($logoPath) }}">
    @endif
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style id="system-theme-vars">:root{--orange:{{ $systemConfig['theme_primary'] ?? '#f28c28' }};--orange-dark:{{ $systemConfig['theme_primary_dark'] ?? '#e87d15' }};--orange-light:{{ $systemConfig['theme_primary'] ?? '#f28c28' }};--orange-soft:{{ $systemConfig['theme_soft'] ?? '#fff3e8' }};--navy:{{ $systemConfig['theme_topbar'] ?? '#20242b' }};--navy2:{{ $systemConfig['theme_sidebar'] ?? '#18202d' }};--sidebar-end:{{ $systemConfig['theme_sidebar_end'] ?? '#101722' }};}</style>
</head>
<body class="app-shell">
    <script>
        try {
            if (localStorage.getItem('electoralSidebarCollapsed') === '1' && !window.matchMedia('(max-width: 760px)').matches) {
                document.body.classList.add('sidebar-collapsed');
            }
        } catch (_) {}
    </script>
    <header class="topbar">
        <div class="topbar-left">
            <button type="button" class="sidebar-toggle" id="sidebarToggle"
                    aria-label="Abrir o cerrar menú principal" aria-expanded="false" title="Menú principal">
                <span></span><span></span><span></span>
            </button>
            <a href="{{ route('dashboard') }}" class="brand">
                @if($logoPath)<img src="{{ asset($logoPath) }}" alt="Logo" class="brand-logo">@endif
                <span>{{ $systemName }}</span>
            </a>
        </div>

        @if(session('id_usuario'))
            <div class="topbar-right">
                <div class="profile-wrap">
                    <button type="button" class="profile-button" id="profileButton"
                            aria-expanded="false" aria-controls="profileMenu"
                            title="Menú de usuario" aria-label="Menú de usuario">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 12a4.25 4.25 0 1 0 0-8.5A4.25 4.25 0 0 0 12 12Zm0 2c-4.4 0-8 2.55-8 5.7 0 .72.58 1.3 1.3 1.3h13.4c.72 0 1.3-.58 1.3-1.3C20 16.55 16.4 14 12 14Z"/>
                        </svg>
                    </button>

                    <div class="profile-menu" id="profileMenu" hidden>
                        <a href="#" class="profile-menu-item">
                            <span class="profile-menu-icon">👤</span> Mi perfil
                        </a>
                        <a href="{{ route('password.change') }}" class="profile-menu-item">
                            <span class="profile-menu-icon">🔑</span> Cambiar contraseña
                        </a>
                        <div class="profile-menu-separator"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="profile-menu-item profile-logout">
                                <span class="profile-menu-icon">↪</span> Cerrar sesión
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </header>

    @if(session('id_usuario'))
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav" aria-label="Navegación principal">
                <a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                   href="{{ route('dashboard') }}" data-tooltip="Panel principal">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-5v-6h-5v6h-5A1.5 1.5 0 0 1 3 19.5v-9Z"/></svg>
                    </span>
                    <span class="nav-label">Panel principal</span>
                </a>

                @php
                    // Todos los módulos deben compartir el mismo menú lateral.
                    // Los controladores de cada módulo no necesitan volver a enviarlo.
                    $menuModules = $sidebarModules ?? \App\Http\Controllers\DashboardController::sidebarModules();
                    $access = \App\Support\AccessControl::menuAccess();
                    if ($access['modules'] !== null) {
                        $menuModules = array_values(array_filter($menuModules, function ($m) use ($access) {
                            // El nombre visible del módulo puede diferir del nombre del catálogo de permisos.
                            // Mantener un alias canónico evita ocultar Digitación de Actas a los roles autorizados.
                            $permissionModuleName = match ($m['slug']) {
                                'digitar-acta' => 'Digitar Acta',
                                default => $m['name'],
                            };
                            return in_array(mb_strtolower($permissionModuleName, 'UTF-8'), $access['modules'], true);
                        }));
                    }
                @endphp
                @php
                    /*
                     * Menú lateral jerárquico.
                     * Los hijos se muestran como Treeview y el padre se abre
                     * automáticamente cuando alguna de sus rutas está activa.
                     *
                     * Se agregan aquí solo opciones que ya existen en el sistema.
                     * Los futuros módulos pueden incorporar 'children' sin
                     * cambiar la estructura general del sidebar.
                     */
                    $menuChildren = [
                        'ambito' => [
                            ['label' => 'Región', 'url' => route('ambito.index', ['tab' => 'region']), 'active' => request()->routeIs('ambito.*') && request('tab', 'region') === 'region'],
                            ['label' => 'Provincia', 'url' => route('ambito.index', ['tab' => 'provincia']), 'active' => request()->routeIs('ambito.*') && request('tab') === 'provincia'],
                            ['label' => 'Distrito', 'url' => route('ambito.index', ['tab' => 'distrito']), 'active' => request()->routeIs('ambito.*') && request('tab') === 'distrito'],
                        ],
                        'personeros' => [
                            ['label' => 'Personero Regional', 'url' => route('personeros.index', ['tipo' => 'regional']), 'active' => request()->routeIs('personeros.*') && request('tipo', 'regional') === 'regional'],
                            ['label' => 'Personero de Provincia', 'url' => route('personeros.index', ['tipo' => 'provincia']), 'active' => request()->routeIs('personeros.*') && request('tipo') === 'provincia'],
                            ['label' => 'Personero de Distrito', 'url' => route('personeros.index', ['tipo' => 'distrito']), 'active' => request()->routeIs('personeros.*') && request('tipo') === 'distrito'],
                            ['label' => 'Personero de Local', 'url' => route('personeros.index', ['tipo' => 'local']), 'active' => request()->routeIs('personeros.*') && request('tipo') === 'local'],
                            ['label' => 'Personero de Mesa', 'url' => route('personeros.index', ['tipo' => 'mesa']), 'active' => request()->routeIs('personeros.*') && request('tipo') === 'mesa'],
                            ['label' => 'Avance', 'url' => route('personeros.index', ['tipo' => 'avance']), 'active' => request()->routeIs('personeros.*') && request('tipo') === 'avance'],
                        ],
                        'digitar-acta' => [
                            ['label' => 'Registrar Acta', 'url' => route('digitar-acta.registrar'), 'active' => request()->routeIs('digitar-acta.registrar', 'digitar-acta.formulario', 'digitar-acta.store') || request()->routeIs('digitar-acta.formulario')],
                            ['label' => 'Ver Actas Registradas', 'url' => route('digitar-acta.registradas'), 'active' => request()->routeIs('digitar-acta.registradas')],
                        ],
                        'reporte' => [
                            ['label' => 'Resultados', 'url' => route('reporte.resultados'), 'active' => request()->routeIs('reporte.resultados', 'reporte.export', 'reporte.dashboard-data')],
                            ['label' => 'Seguimiento', 'url' => route('reporte.seguimiento'), 'active' => request()->routeIs('reporte.seguimiento')],
                        ],
                    ];
                    if ($access['modules'] !== null) {
                        foreach ($menuChildren as $menuSlug => $children) {
                            $moduleName = match ($menuSlug) {
                                'ambito' => 'Ámbito',
                                'personeros' => 'Personeros',
                                'digitar-acta' => 'Digitar Acta',
                                'reporte' => 'Reporte',
                                default => null,
                            };
                            if ($moduleName !== null) {
                                $menuChildren[$menuSlug] = array_values(array_filter($children, function ($child) use ($moduleName) {
                                    $map = [
                                        'Región' => 'Región',
                                        'Provincia' => 'Provincia',
                                        'Distrito' => 'Distrito',
                                        'Personero Regional' => 'Personero Regional',
                                        'Personero de Provincia' => 'Personero Provincial',
                                        'Personero de Distrito' => 'Personero Distrital',
                                        'Personero de Local' => 'Personero de Local',
                                        'Personero de Mesa' => 'Personero de Mesa',
                                        'Avance' => 'Avance',
                                        'Registrar Acta' => 'Digitar',
                                        'Ver Actas Registradas' => 'Ver Acta',
                                        'Resultados' => 'Resultados',
                                        'Seguimiento' => 'Seguimiento',
                                    ];
                                    $option = $map[$child['label']] ?? null;
                                    return $option !== null && \App\Support\AccessControl::canOption($moduleName, $option);
                                }));
                            }
                        }
                    }

                @endphp

                @foreach($menuModules as $m)
                    @php
                        $moduleRoute = match($m['slug']) {
                            'ambito' => route('ambito.index'),
                            'personas' => route('personas.index'),
                            'usuarios' => route('usuarios.index'),
                            'roles-permisos' => route('roles.index'),
                            'partidos' => route('partidos.index'),
                            'locales' => route('locales.index'),
                            'mesas' => route('mesas.index'),
                            'personeros' => route('personeros.index'),
                            'digitar-acta' => route('digitar-acta.registrar'),
                            'reporte' => route('reporte.resultados'),
                            'auditoria' => route('auditoria.index'),
                            'configuracion' => route('configuracion.index'),
                            default => route('module.placeholder', $m['slug']),
                        };

                        $moduleActive = match($m['slug']) {
                            'ambito' => request()->routeIs('ambito.*'),
                            'personas' => request()->routeIs('personas.*'),
                            'usuarios' => request()->routeIs('usuarios.*'),
                            'roles-permisos' => request()->routeIs('roles.*'),
                            'partidos' => request()->routeIs('partidos.*'),
                            'locales' => request()->routeIs('locales.*'),
                            'mesas' => request()->routeIs('mesas.*'),
                            'personeros' => request()->routeIs('personeros.*'),
                            'digitar-acta' => request()->routeIs('digitar-acta.*'),
                            'reporte' => request()->routeIs('reporte.*'),
                            'auditoria' => request()->routeIs('auditoria.*'),
                            'configuracion' => request()->routeIs('configuracion.*'),
                            default => request()->route('slug') === $m['slug'],
                        };

                        $children = $menuChildren[$m['slug']] ?? [];
                        $hasChildren = count($children) > 0;
                        $childActive = collect($children)->contains(fn($child) => $child['active']);
                        $groupOpen = $moduleActive && $hasChildren;
                    @endphp

                    @if($hasChildren)
                        <div class="nav-group {{ $groupOpen ? 'is-open is-active' : '' }}" data-menu-group="{{ $m['slug'] }}">
                            <button type="button"
                                    class="nav-item nav-group-toggle {{ $moduleActive ? 'active' : '' }}"
                                    aria-expanded="{{ $groupOpen ? 'true' : 'false' }}"
                                    aria-controls="submenu-{{ $m['slug'] }}"
                                    data-tooltip="{{ $m['name'] }}">
                                <span class="nav-icon" aria-hidden="true">{!! $m['icon'] !!}</span>
                                <span class="nav-label">{{ $m['name'] }}</span>
                                <span class="nav-arrow" aria-hidden="true">›</span>
                            </button>

                            <div class="nav-submenu" id="submenu-{{ $m['slug'] }}">
                                @foreach($children as $child)
                                    <a href="{{ $child['url'] }}"
                                       class="nav-subitem {{ $child['active'] ? 'active' : '' }}">
                                        <span class="nav-subitem-dot" aria-hidden="true"></span>
                                        <span>{{ $child['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a class="nav-item {{ $moduleActive ? 'active' : '' }}"
                           href="{{ $moduleRoute }}"
                           data-tooltip="{{ $m['name'] }}">
                            <span class="nav-icon" aria-hidden="true">{!! $m['icon'] !!}</span>
                            <span class="nav-label">{{ $m['name'] }}</span>
                        </a>
                    @endif
                @endforeach
            </nav>
        </aside>
    @endif

    <main class="main-content {{ request()->routeIs('login') ? 'auth-main' : '' }}" id="mainContent">
        @if(session('success'))
            <div class="alert success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert error">{{ session('error') }}</div>
        @endif
        @if(session('warning'))
            <div class="alert warning">{{ session('warning') }}</div>
        @endif
        @yield('content')
    </main>

    @if(session('id_usuario'))
    <script>
        (() => {
            const body = document.body;
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('sidebarToggle');
            const profileButton = document.getElementById('profileButton');
            const profileMenu = document.getElementById('profileMenu');

            // Tooltip fijo para módulos simples cuando el sidebar está contraído.
            // Se agrega al body para evitar que el overflow del sidebar lo recorte.
            let sidebarTooltip = null;
            let sidebarTooltipTimer = null;
            const cancelSidebarTooltipHide = () => {
                if (sidebarTooltipTimer) {
                    clearTimeout(sidebarTooltipTimer);
                    sidebarTooltipTimer = null;
                }
            };
            const scheduleHideSidebarTooltip = (delay = 220) => {
                cancelSidebarTooltipHide();
                sidebarTooltipTimer = setTimeout(() => {
                    if (sidebarTooltip && !sidebarTooltip.matches(':hover')) {
                        sidebarTooltip.hidden = true;
                    }
                    sidebarTooltipTimer = null;
                }, delay);
            };
            const ensureSidebarTooltip = () => {
                if (sidebarTooltip) return sidebarTooltip;
                sidebarTooltip = document.createElement('a');
                sidebarTooltip.className = 'sidebar-simple-tooltip';
                sidebarTooltip.setAttribute('role', 'link');
                sidebarTooltip.setAttribute('tabindex', '0');
                sidebarTooltip.hidden = true;
                sidebarTooltip.addEventListener('mouseenter', cancelSidebarTooltipHide);
                sidebarTooltip.addEventListener('mouseleave', () => scheduleHideSidebarTooltip(180));
                sidebarTooltip.addEventListener('focus', cancelSidebarTooltipHide);
                sidebarTooltip.addEventListener('blur', () => scheduleHideSidebarTooltip(180));
                sidebarTooltip.addEventListener('click', (event) => {
                    if (!sidebarTooltip.href) return;
                    event.preventDefault();
                    event.stopPropagation();
                    const href = sidebarTooltip.href;
                    hideSidebarTooltip();
                    loadModule(href);
                });
                document.body.appendChild(sidebarTooltip);
                return sidebarTooltip;
            };
            const hideSidebarTooltip = () => {
                cancelSidebarTooltipHide();
                if (sidebarTooltip) sidebarTooltip.hidden = true;
            };
            const showSidebarTooltip = (link) => {
                if (!body.classList.contains('sidebar-collapsed') || !link || link.closest('.nav-group')) return;
                const label = link.dataset.tooltip || link.querySelector('.nav-label')?.textContent?.trim();
                if (!label) return;
                const tip = ensureSidebarTooltip();
                cancelSidebarTooltipHide();
                tip.textContent = label;
                tip.href = link.href;
                tip.setAttribute('aria-label', label);
                tip.hidden = false;
                const rect = link.getBoundingClientRect();
                const left = Math.min(rect.right + 8, window.innerWidth - 260);
                tip.style.left = `${Math.max(8, left)}px`;
                tip.style.top = `${Math.max(8, Math.min(rect.top + rect.height / 2, window.innerHeight - 8))}px`;
                tip.style.transform = 'translateY(-50%)';
            };


            /* Sidebar principal: el estado colapsado se conserva al cambiar de módulo,
               recargar la página o aplicar filtros. */
            const setDesktopSidebarCollapsed = (collapsed) => {
                body.classList.toggle('sidebar-collapsed', collapsed);
                toggle?.setAttribute('aria-expanded', String(!collapsed));
                try { localStorage.setItem('electoralSidebarCollapsed', collapsed ? '1' : '0'); } catch (_) {}
                if (!collapsed) { closeSidebarFlyout(); hideSidebarTooltip(); }
            };

            toggle?.addEventListener('click', () => {
                if (window.matchMedia('(max-width: 760px)').matches) {
                    const open = body.classList.toggle('mobile-nav-open');
                    toggle.setAttribute('aria-expanded', String(open));
                    return;
                }
                setDesktopSidebarCollapsed(!body.classList.contains('sidebar-collapsed'));
            });

            const closeMobileNav = () => {
                if (!window.matchMedia('(max-width: 760px)').matches) return;
                body.classList.remove('mobile-nav-open');
                toggle?.setAttribute('aria-expanded', 'false');
            };

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeMobileNav();
            });

            document.addEventListener('click', (event) => {
                if (!window.matchMedia('(max-width: 760px)').matches || !body.classList.contains('mobile-nav-open')) return;
                if (sidebar?.contains(event.target) || toggle?.contains(event.target)) return;
                closeMobileNav();
            });

            document.addEventListener('click', (event) => {
                if (!body.classList.contains('sidebar-collapsed')) return;
                if (sidebar?.contains(event.target) || sidebarFlyout?.contains(event.target) || sidebarTooltip?.contains(event.target)) return;
                closeSidebarFlyout();
                document.querySelectorAll('.nav-item.tooltip-open').forEach(item => item.classList.remove('tooltip-open'));
                hideSidebarTooltip();
            });

            /*
             * Menú en cascada del sidebar.
             * - Solo los módulos que tienen subopciones usan Treeview.
             * - Un solo grupo queda abierto a la vez.
             * - El clic en el padre NO cambia la página: solo abre/cierra.
             * - Si se navega a una subopción, Blade deja el grupo abierto.
             * - El scroll se aplica únicamente al sidebar.
             */
            const groups = Array.from(document.querySelectorAll('.nav-group'));
            let sidebarFlyout = null;
            let flyoutCloseTimer = null;

            const closeSidebarFlyout = () => {
                if (flyoutCloseTimer) clearTimeout(flyoutCloseTimer);
                flyoutCloseTimer = null;
                if (sidebarFlyout) sidebarFlyout.hidden = true;
                document.querySelectorAll('.nav-group.flyout-open').forEach(group => group.classList.remove('flyout-open'));
            };

            const scheduleCloseSidebarFlyout = () => {
                if (flyoutCloseTimer) clearTimeout(flyoutCloseTimer);
                flyoutCloseTimer = setTimeout(closeSidebarFlyout, 180);
            };

            const ensureSidebarFlyout = () => {
                if (sidebarFlyout) return sidebarFlyout;
                sidebarFlyout = document.createElement('div');
                sidebarFlyout.className = 'sidebar-flyout';
                sidebarFlyout.hidden = true;
                sidebarFlyout.addEventListener('mouseenter', () => {
                    if (flyoutCloseTimer) clearTimeout(flyoutCloseTimer);
                });
                sidebarFlyout.addEventListener('mouseleave', scheduleCloseSidebarFlyout);
                document.body.appendChild(sidebarFlyout);
                return sidebarFlyout;
            };

            const showSidebarFlyout = (group) => {
                if (!body.classList.contains('sidebar-collapsed') || !group) return;
                const button = group.querySelector('.nav-group-toggle');
                const submenu = group.querySelector('.nav-submenu');
                if (!button || !submenu) return;

                if (flyoutCloseTimer) clearTimeout(flyoutCloseTimer);
                document.querySelectorAll('.nav-group.flyout-open').forEach(other => {
                    if (other !== group) other.classList.remove('flyout-open');
                });
                group.classList.add('flyout-open');

                const flyout = ensureSidebarFlyout();
                const title = group.dataset.menuGroup || button.dataset.tooltip || '';
                const links = Array.from(submenu.querySelectorAll('.nav-subitem'));
                flyout.innerHTML = '';

                const heading = document.createElement('div');
                heading.className = 'sidebar-flyout-title';
                heading.textContent = button.dataset.tooltip || title;
                flyout.appendChild(heading);

                links.forEach(link => {
                    const item = document.createElement('a');
                    item.className = 'sidebar-flyout-link' + (link.classList.contains('active') ? ' active' : '');
                    item.href = link.href;
                    const dot = document.createElement('span');
                    dot.className = 'sidebar-flyout-dot';
                    const label = document.createElement('span');
                    label.textContent = link.textContent.trim();
                    item.append(dot, label);
                    flyout.appendChild(item);
                });

                const rect = button.getBoundingClientRect();
                const preferredLeft = rect.right + 8;
                const width = Math.min(280, Math.max(218, flyout.offsetWidth || 230));
                flyout.style.left = `${Math.min(preferredLeft, window.innerWidth - width - 8)}px`;
                flyout.style.top = `${Math.max(8, Math.min(rect.top, window.innerHeight - 8))}px`;
                flyout.hidden = false;

                // Ajusta verticalmente cuando el panel ya conoce su altura.
                const h = flyout.getBoundingClientRect().height;
                const top = Math.max(8, Math.min(rect.top, window.innerHeight - h - 8));
                flyout.style.top = `${top}px`;
            };

            const closeGroup = (group) => {
                group.classList.remove('is-open');
                const button = group.querySelector('.nav-group-toggle');
                button?.setAttribute('aria-expanded', 'false');
            };

            const keepItemVisible = (item) => {
                if (!sidebar || !item || body.classList.contains('sidebar-collapsed')) return;

                const sidebarRect = sidebar.getBoundingClientRect();
                const itemRect = item.getBoundingClientRect();
                const topLimit = sidebarRect.top + 8;
                const bottomLimit = sidebarRect.bottom - 8;

                if (itemRect.bottom > bottomLimit) {
                    sidebar.scrollTop += itemRect.bottom - bottomLimit;
                } else if (itemRect.top < topLimit) {
                    sidebar.scrollTop -= topLimit - itemRect.top;
                }
            };

            const openGroup = (group, ensureVisible = true) => {
                groups.forEach(other => {
                    if (other !== group) closeGroup(other);
                });

                group.classList.add('is-open');
                const button = group.querySelector('.nav-group-toggle');
                button?.setAttribute('aria-expanded', 'true');

                if (ensureVisible) {
                    window.requestAnimationFrame(() => {
                        const activeChild = group.querySelector('.nav-subitem.active');
                        keepItemVisible(activeChild || group);
                    });
                }
            };

            groups.forEach(group => {
                const button = group.querySelector('.nav-group-toggle');
                const activeChild = group.querySelector('.nav-subitem.active');

                if (group.classList.contains('is-open') && !body.classList.contains('sidebar-collapsed')) {
                    openGroup(group, false);
                    if (activeChild) {
                        window.requestAnimationFrame(() => keepItemVisible(activeChild));
                    }
                }

                button?.addEventListener('mouseenter', () => {
                    if (body.classList.contains('sidebar-collapsed') && window.matchMedia('(hover: hover)').matches) {
                        showSidebarFlyout(group);
                    }
                });
                button?.addEventListener('mouseleave', () => {
                    if (body.classList.contains('sidebar-collapsed')) scheduleCloseSidebarFlyout();
                });

                button?.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    if (body.classList.contains('sidebar-collapsed')) {
                        if (group.classList.contains('flyout-open') && sidebarFlyout && !sidebarFlyout.hidden) {
                            closeSidebarFlyout();
                        } else {
                            showSidebarFlyout(group);
                        }
                        return;
                    }

                    if (group.classList.contains('is-open')) {
                        closeGroup(group);
                    } else {
                        openGroup(group, true);
                    }
                });
            });

            /* Navegación parcial: solo se reemplaza #mainContent al cambiar de módulo. */
            const mainContent = document.getElementById('mainContent');
            let navigationBusy = false;
            const normalizeUrl = (href) => {
                const u = new URL(href, window.location.origin);
                return u.pathname + u.search;
            };
            const updateActiveMenu = (url) => {
                const current = normalizeUrl(url);
                document.querySelectorAll('.nav-item.active,.nav-subitem.active').forEach(el => el.classList.remove('active'));
                document.querySelectorAll('.nav-group').forEach(g => g.classList.remove('is-open','is-active'));
                let matched = null;
                document.querySelectorAll('.sidebar a[href]').forEach(link => {
                    try {
                        if (normalizeUrl(link.href) === current) {
                            link.classList.add('active');
                            matched = link;
                        }
                    } catch (_) {}
                });
                const group = matched?.closest('.nav-group');
                if (group) openGroup(group, true);
            };
            const executeScripts = (container) => {
                container.querySelectorAll('script').forEach(oldScript => {
                    const script = document.createElement('script');
                    for (const attr of oldScript.attributes) script.setAttribute(attr.name, attr.value);
                    script.textContent = oldScript.textContent;
                    oldScript.replaceWith(script);
                });
            };
            const loadModule = async (href, push = true) => {
                if (navigationBusy) return;
                const target = normalizeUrl(href);
                if (target === normalizeUrl(window.location.href)) return;
                navigationBusy = true;
                mainContent?.classList.add('module-loading');
                const savedScroll = sidebar?.scrollTop || 0;
                try {
                    const response = await fetch(href, {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'}, credentials:'same-origin'});
                    if (!response.ok) throw new Error('HTTP '+response.status);
                    const html = await response.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const nextMain = doc.getElementById('mainContent');
                    if (!nextMain || !mainContent) throw new Error('No se encontró el contenido principal.');
                    mainContent.innerHTML = nextMain.innerHTML;
                    if (doc.title) document.title = doc.title;
                    if (push) history.pushState({url: href}, '', href);
                    updateActiveMenu(href);
                    executeScripts(mainContent);
                    if (sidebar) sidebar.scrollTop = savedScroll;
                    const active = sidebar?.querySelector('.nav-item.active, .nav-subitem.active');
                    if (active) window.requestAnimationFrame(() => keepItemVisible(active));
                } catch (error) {
                    window.location.href = href;
                } finally {
                    mainContent?.classList.remove('module-loading');
                    navigationBusy = false;
                }
            };
            document.querySelectorAll('.sidebar a[href]').forEach(link => {
                if (!link.closest('.nav-group')) {
                    link.addEventListener('mouseenter', () => {
                        if (window.matchMedia('(hover: hover)').matches) showSidebarTooltip(link);
                    });
                    link.addEventListener('mouseleave', (event) => {
                        // El tooltip vive fuera del sidebar; damos tiempo para que
                        // el puntero pueda cruzar el pequeño espacio hasta el nombre.
                        if (event.relatedTarget === sidebarTooltip || sidebarTooltip?.contains(event.relatedTarget)) {
                            cancelSidebarTooltipHide();
                            return;
                        }
                        scheduleHideSidebarTooltip();
                    });
                    link.addEventListener('focus', () => showSidebarTooltip(link));
                    link.addEventListener('blur', hideSidebarTooltip);
                }

                link.addEventListener('click', (event) => {
                    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                    const url = new URL(link.href, window.location.origin);
                    if (url.origin !== window.location.origin) return;

                    // En modo colapsado/táctil, el primer toque en un módulo sin
                    // submódulos muestra su nombre; el siguiente toque navega.
                    if (body.classList.contains('sidebar-collapsed') && link.classList.contains('nav-item') && window.matchMedia('(pointer: coarse)').matches) {
                        if (!link.classList.contains('tooltip-open')) {
                            event.preventDefault();
                            document.querySelectorAll('.nav-item.tooltip-open').forEach(item => item.classList.remove('tooltip-open'));
                            link.classList.add('tooltip-open');
                            showSidebarTooltip(link);
                            return;
                        }
                        link.classList.remove('tooltip-open');
                        hideSidebarTooltip();
                    }

                    if (link.closest('.nav-submenu') || link.classList.contains('nav-item')) {
                        event.preventDefault();
                        loadModule(url.href);
                        closeMobileNav();
                        closeSidebarFlyout();
                    }
                });
            });
            window.addEventListener('popstate', () => loadModule(window.location.href, false));

            profileButton?.addEventListener('click', (e) => {
                e.stopPropagation();
                const isHidden = profileMenu.hasAttribute('hidden');
                if (isHidden) {
                    profileMenu.removeAttribute('hidden');
                } else {
                    profileMenu.setAttribute('hidden', '');
                }
                profileButton.setAttribute('aria-expanded', String(isHidden));
            });

            document.addEventListener('click', (e) => {
                if (profileMenu && !profileMenu.contains(e.target) && e.target !== profileButton) {
                    profileMenu.setAttribute('hidden', '');
                    profileButton?.setAttribute('aria-expanded', 'false');
                }
            });
        })();
    </script>
    @endif
</body>
</html>
