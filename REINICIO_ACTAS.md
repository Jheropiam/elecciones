# Reinicio de actas para pruebas y Día D

El proyecto incluye el comando Artisan:

```powershell
php artisan electoral:reset-actas
```

Su objetivo es reiniciar **solo las actas digitadas y sus resultados**, sin eliminar la configuración electoral que debe permanecer para una nueva jornada.

## Qué elimina

- Todas las filas de `digitacion_acta`.
- Por consecuencia, el módulo **Reporte** vuelve a mostrar 0 actas contabilizadas y 0 resultados.
- Reinicia el `AUTO_INCREMENT` de `digitacion_acta` al usar `TRUNCATE TABLE`.

## Qué conserva

- Regiones, provincias y distritos.
- Locales.
- Mesas.
- Partidos políticos.
- Candidatos.
- Personas y usuarios.
- Roles y permisos.
- Personeros.
- Configuración general.
- Auditoría y trazabilidad.

## Uso recomendado

Primero se puede revisar qué se reiniciaría sin tocar datos:

```powershell
php artisan electoral:reset-actas --dry-run
```

Para ejecutar el reinicio, el comando solicita confirmación:

```powershell
php artisan electoral:reset-actas
```

En automatizaciones o scripts donde se desea omitir la pregunta:

```powershell
php artisan electoral:reset-actas --force
```

El comando registra en `auditoria` una operación `REINICIO_ACTAS`, conservando la trazabilidad del reinicio.

> **Importante:** este comando no reemplaza un respaldo. Antes del primer reinicio de una base con datos de prueba importantes, realice un backup de MySQL.
