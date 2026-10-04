<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AmbitoController;
use App\Http\Controllers\PersonasController;
use App\Http\Controllers\UsuariosController;
use App\Http\Controllers\RolesPermisosController;
use App\Http\Controllers\LocalesController;
use App\Http\Controllers\MesasController;
use App\Http\Controllers\PersonerosController;
use App\Http\Controllers\PartidosController;
use App\Http\Controllers\DigitarActaController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\ConfiguracionController;

Route::get('/', fn()=>redirect()->route('login'));
Route::get('/login',[AuthController::class,'show'])->name('login');
Route::post('/login',[AuthController::class,'login'])->name('login.post');
Route::post('/logout',[AuthController::class,'logout'])->name('logout');

Route::middleware('sistema.auth')->group(function(){
    Route::get('/password/change',[AuthController::class,'changeForm'])->name('password.change');
    Route::post('/password/change',[AuthController::class,'changePassword'])->name('password.change.post');
    Route::get('/dashboard',[DashboardController::class,'index'])->name('dashboard');

    // Módulo Ámbito
    Route::get('/ambito', [AmbitoController::class, 'index'])->name('ambito.index');
    Route::post('/ambito/region', [AmbitoController::class, 'storeRegion'])->name('ambito.region.store');
    Route::get('/ambito/region/exportar', [AmbitoController::class, 'exportRegiones'])->name('ambito.region.export');
    Route::get('/ambito/region/plantilla', [AmbitoController::class, 'plantillaRegiones'])->name('ambito.region.template');
    Route::post('/ambito/region/importar', [AmbitoController::class, 'importRegiones'])->name('ambito.region.import');
    Route::get('/ambito/region/importar/errores/{filename}', [AmbitoController::class, 'downloadImportRegionErrors'])->name('ambito.region.import.errors');
    Route::put('/ambito/region/{id}', [AmbitoController::class, 'updateRegion'])->name('ambito.region.update');
    Route::patch('/ambito/region/{id}/estado', [AmbitoController::class, 'toggleRegion'])->name('ambito.region.toggle');
    Route::post('/ambito/provincia', [AmbitoController::class, 'storeProvincia'])->name('ambito.provincia.store');
    Route::put('/ambito/provincia/{id}', [AmbitoController::class, 'updateProvincia'])->name('ambito.provincia.update');
    Route::patch('/ambito/provincia/{id}/estado', [AmbitoController::class, 'toggleProvincia'])->name('ambito.provincia.toggle');
    Route::get('/ambito/provincia/exportar', [AmbitoController::class, 'exportProvincias'])->name('ambito.provincia.export');
    Route::get('/ambito/provincia/plantilla', [AmbitoController::class, 'plantillaProvincias'])->name('ambito.provincia.template');
    Route::post('/ambito/provincia/importar', [AmbitoController::class, 'importProvincias'])->name('ambito.provincia.import');
    Route::get('/ambito/provincia/importar/errores/{filename}', [AmbitoController::class, 'downloadImportProvinciaErrors'])->name('ambito.provincia.import.errors');
    Route::post('/ambito/distrito', [AmbitoController::class, 'storeDistrito'])->name('ambito.distrito.store');
    Route::put('/ambito/distrito/{id}', [AmbitoController::class, 'updateDistrito'])->name('ambito.distrito.update');
    Route::patch('/ambito/distrito/{id}/estado', [AmbitoController::class, 'toggleDistrito'])->name('ambito.distrito.toggle');
    Route::get('/ambito/distrito/exportar', [AmbitoController::class, 'exportDistritos'])->name('ambito.distrito.export');
    Route::get('/ambito/distrito/plantilla', [AmbitoController::class, 'plantillaDistritos'])->name('ambito.distrito.template');
    Route::post('/ambito/distrito/importar', [AmbitoController::class, 'importDistritos'])->name('ambito.distrito.import');
    Route::get('/ambito/distrito/importar/errores/{filename}', [AmbitoController::class, 'downloadImportDistritoErrors'])->name('ambito.distrito.import.errors');
    Route::get('/ambito/provincias/{region}', [AmbitoController::class, 'provincesByRegion'])->name('ambito.provincias.region');

    // Módulo Personas
    Route::get('/personas', [PersonasController::class, 'index'])->name('personas.index');
    Route::post('/personas', [PersonasController::class, 'store'])->name('personas.store');
    Route::put('/personas/{id}', [PersonasController::class, 'update'])->name('personas.update');
    Route::patch('/personas/{id}/estado', [PersonasController::class, 'toggle'])->name('personas.toggle');
    Route::get('/personas/buscar-dni/{dni}', [PersonasController::class, 'buscarDni'])->name('personas.buscar.dni');
    Route::get('/personas/provincias/{region}', [PersonasController::class, 'provincias'])->name('personas.provincias');
    Route::get('/personas/distritos/{provincia}', [PersonasController::class, 'distritos'])->name('personas.distritos');
    Route::get('/personas/exportar', [PersonasController::class, 'export'])->name('personas.export');
    Route::get('/personas/plantilla', [PersonasController::class, 'template'])->name('personas.template');
    Route::post('/personas/importar', [PersonasController::class, 'import'])->name('personas.import');
    Route::get('/personas/importar/errores/{filename}', [PersonasController::class, 'errors'])->name('personas.import.errors');

    // Módulo Partidos Políticos
    Route::get('/partidos', [PartidosController::class, 'index'])->name('partidos.index');
    Route::post('/partidos', [PartidosController::class, 'store'])->name('partidos.store');
    Route::put('/partidos/{id}', [PartidosController::class, 'update'])->name('partidos.update');
    Route::patch('/partidos/{id}/estado', [PartidosController::class, 'toggle'])->name('partidos.toggle');
    Route::get('/partidos/exportar', [PartidosController::class, 'export'])->name('partidos.export');
    Route::get('/partidos/plantilla', [PartidosController::class, 'template'])->name('partidos.template');
     Route::get('/partidos/plantilla-zip', [PartidosController::class, 'templateZip'])->name('partidos.template.zip');
    Route::post('/partidos/importar', [PartidosController::class, 'import'])->name('partidos.import');
    Route::get('/partidos/importar/errores/{filename}', [PartidosController::class, 'errors'])->name('partidos.import.errors');

    // Módulo Personeros
    Route::get('/personeros', [PersonerosController::class, 'index'])->name('personeros.index');
    Route::post('/personeros/{tipo}', [PersonerosController::class, 'store'])->name('personeros.store');
    Route::put('/personeros/{tipo}/{id}', [PersonerosController::class, 'update'])->name('personeros.update');
    Route::patch('/personeros/{tipo}/{id}/estado', [PersonerosController::class, 'toggle'])->name('personeros.toggle');
    Route::get('/personeros/persona/{dni}', [PersonerosController::class, 'buscarPersona'])->name('personeros.buscar.persona');
    Route::get('/personeros/provincias/{region}', [PersonerosController::class, 'provincias'])->name('personeros.provincias');
    Route::get('/personeros/distritos/{provincia}', [PersonerosController::class, 'distritos'])->name('personeros.distritos');
    Route::get('/personeros/locales/{distrito}', [PersonerosController::class, 'locales'])->name('personeros.locales');
    Route::get('/personeros/mesas/{local}', [PersonerosController::class, 'mesas'])->name('personeros.mesas');
    Route::get('/personeros/exportar/{tipo}', [PersonerosController::class, 'export'])->name('personeros.export');
    Route::get('/personeros/plantilla/{tipo}', [PersonerosController::class, 'template'])->name('personeros.template');
    Route::post('/personeros/importar/{tipo}', [PersonerosController::class, 'import'])->name('personeros.import');
    Route::get('/personeros/importar/errores/{filename}', [PersonerosController::class, 'errors'])->name('personeros.import.errors');

    // Módulo Roles y Permisos
    Route::get('/roles', [RolesPermisosController::class, 'index'])->name('roles.index');
    Route::post('/roles', [RolesPermisosController::class, 'store'])->name('roles.store');
    Route::put('/roles/{id}', [RolesPermisosController::class, 'update'])->name('roles.update');
    Route::patch('/roles/{id}/estado', [RolesPermisosController::class, 'toggle'])->name('roles.toggle');
    Route::get('/roles/{id}/permisos', [RolesPermisosController::class, 'permissions'])->name('roles.permissions');
    Route::post('/roles/{id}/permisos', [RolesPermisosController::class, 'savePermissions'])->name('roles.permissions.save');

    // Módulo Usuarios
    Route::get('/usuarios', [UsuariosController::class, 'index'])->name('usuarios.index');
    Route::post('/usuarios', [UsuariosController::class, 'store'])->name('usuarios.store');
    Route::get('/usuarios/buscar-persona/{dni}', [UsuariosController::class, 'buscarPersona'])->name('usuarios.buscar.persona');
    Route::put('/usuarios/{id}', [UsuariosController::class, 'update'])->name('usuarios.update');
    Route::get('/usuarios/data', [UsuariosController::class, 'data'])->name('usuarios.data');
    Route::get('/usuarios/provincias/{region}', [UsuariosController::class, 'scopeProvincias'])->name('usuarios.provincias');
    Route::get('/usuarios/distritos/{provincia}', [UsuariosController::class, 'scopeDistritos'])->name('usuarios.distritos');
    Route::get('/usuarios/locales/{distrito}', [UsuariosController::class, 'scopeLocales'])->name('usuarios.locales');
    Route::patch('/usuarios/{id}/estado', [UsuariosController::class, 'toggle'])->name('usuarios.toggle');
    Route::patch('/usuarios/{id}/reset-password', [UsuariosController::class, 'resetPassword'])->name('usuarios.reset');
    Route::get('/usuarios/exportar', [UsuariosController::class, 'export'])->name('usuarios.export');
    Route::get('/usuarios/plantilla', [UsuariosController::class, 'template'])->name('usuarios.template');
    Route::post('/usuarios/importar', [UsuariosController::class, 'import'])->name('usuarios.import');
    Route::get('/usuarios/importar/errores/{filename}', [UsuariosController::class, 'errors'])->name('usuarios.import.errors');

    // Módulo Locales
    Route::get('/locales', [LocalesController::class, 'index'])->name('locales.index');
    Route::post('/locales', [LocalesController::class, 'store'])->name('locales.store');
    Route::put('/locales/{id}', [LocalesController::class, 'update'])->name('locales.update');
    Route::patch('/locales/{id}/estado', [LocalesController::class, 'toggle'])->name('locales.toggle');
    Route::get('/locales/provincias/{region}', [LocalesController::class, 'provincias'])->name('locales.provincias');
    Route::get('/locales/distritos/{provincia}', [LocalesController::class, 'distritos'])->name('locales.distritos');
    Route::get('/locales/exportar', [LocalesController::class, 'export'])->name('locales.export');
    Route::get('/locales/plantilla', [LocalesController::class, 'template'])->name('locales.template');
    Route::post('/locales/importar', [LocalesController::class, 'import'])->name('locales.import');
    Route::get('/locales/importar/errores/{filename}', [LocalesController::class, 'errors'])->name('locales.import.errors');

    // Módulo Mesas
    Route::get('/mesas', [MesasController::class, 'index'])->name('mesas.index');
    Route::post('/mesas', [MesasController::class, 'store'])->name('mesas.store');
    Route::put('/mesas/{id}', [MesasController::class, 'update'])->name('mesas.update');
    Route::patch('/mesas/{id}/estado', [MesasController::class, 'toggle'])->name('mesas.toggle');
    Route::get('/mesas/provincias/{region}', [MesasController::class, 'provincias'])->name('mesas.provincias');
    Route::get('/mesas/distritos/{provincia}', [MesasController::class, 'distritos'])->name('mesas.distritos');
    Route::get('/mesas/locales/{distrito}', [MesasController::class, 'locales'])->name('mesas.locales');
    Route::get('/mesas/exportar', [MesasController::class, 'export'])->name('mesas.export');
    Route::get('/mesas/plantilla', [MesasController::class, 'template'])->name('mesas.template');
    Route::post('/mesas/importar', [MesasController::class, 'import'])->name('mesas.import');
    Route::get('/mesas/importar/errores/{filename}', [MesasController::class, 'downloadImportErrors'])->name('mesas.import.errors');
    // Módulo Digitación de Actas
    Route::get('/digitar-acta/registrar', [DigitarActaController::class, 'registrar'])->name('digitar-acta.registrar');
    Route::get('/digitar-acta/registradas', [DigitarActaController::class, 'registradas'])->name('digitar-acta.registradas');
    Route::get('/digitar-acta/registradas/exportar', [DigitarActaController::class, 'exportRegistradas'])->name('digitar-acta.registradas.export');
    Route::get('/digitar-acta/registradas/editar/{numero}/{tipo}', [DigitarActaController::class, 'editar'])->name('digitar-acta.editar');
    Route::put('/digitar-acta/registradas/{numero}/{tipo}', [DigitarActaController::class, 'actualizar'])->name('digitar-acta.actualizar');
    Route::get('/digitar-acta/buscar-mesa/{numero}', [DigitarActaController::class, 'buscarMesa'])
        ->where('numero', '[0-9]{6}')
        ->name('digitar-acta.buscar.mesa');
    Route::get('/digitar-acta/formulario/{numero}/{tipo}', [DigitarActaController::class, 'formulario'])
        ->where(['numero' => '[0-9]{6}', 'tipo' => '[12]'])
        ->name('digitar-acta.formulario');
    Route::post('/digitar-acta', [DigitarActaController::class, 'store'])->name('digitar-acta.store');

    // Módulo Reporte
    Route::get('/reporte', [ReporteController::class, 'index'])->name('reporte.resultados');
    Route::get('/reporte/resultados', [ReporteController::class, 'index'])->name('reporte.resultados.alt');
    Route::get('/reporte/seguimiento', [ReporteController::class, 'seguimiento'])->name('reporte.seguimiento');
    Route::get('/reporte/seguimiento/exportar', [ReporteController::class, 'exportSeguimiento'])->name('reporte.seguimiento.export');
    Route::get('/reporte/exportar', [ReporteController::class, 'export'])->name('reporte.export');
    Route::get('/reporte/dashboard-data', [ReporteController::class, 'dashboardData'])->name('reporte.dashboard-data');

    // Módulo Auditoría y Trazabilidad
    Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
    Route::get('/auditoria/exportar', [AuditoriaController::class, 'export'])->name('auditoria.export');
    Route::get('/auditoria/{id}', [AuditoriaController::class, 'detail'])->whereNumber('id')->name('auditoria.detail');

    // Módulo Configuración General
    Route::get('/configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');
    Route::post('/configuracion', [ConfiguracionController::class, 'update'])->name('configuracion.update');
    Route::post('/configuracion/puesta-en-cero', [ConfiguracionController::class, 'puestaEnCero'])->name('configuracion.puesta-cero');
    Route::delete('/configuracion/logo', [ConfiguracionController::class, 'removeLogo'])->name('configuracion.logo.remove');

    // Placeholder para módulos aún no desarrollados.
    Route::get('/modulo/{slug}', function($slug){
        $modules = DashboardController::sidebarModules();
        if ($slug === 'ambito') return redirect()->route('ambito.index');
        $module = collect($modules)->firstWhere('slug', $slug);
        return view('modules.placeholder', [
            'slug' => $slug,
            'moduleName' => $module['name'] ?? ucwords(str_replace('-', ' ', $slug)),
            'sidebarModules' => $modules,
        ]);
    })->name('module.placeholder');
});
