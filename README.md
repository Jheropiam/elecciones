# Sistema Electoral – Laravel 13

## Requisitos
- PHP 8.3+
- MySQL 8.x
- Composer 2.x
- Node.js/npm

## Instalación en Windows con Laragon
1. Crear la BD `sistema_electoral` en MySQL.
2. Copiar `.env.example` a `.env`.
3. Configurar usuario/contraseña de MySQL.
4. Ejecutar `composer install`.
5. Ejecutar `php artisan key:generate`.
6. Ejecutar `php artisan migrate --seed`.
7. Ejecutar `php artisan serve`.
8. Abrir http://127.0.0.1:8000

## Administrador inicial
DNI/usuario: 70750380
Contraseña inicial: 70750380

La primera entrada obliga a cambiar la contraseña.

## Estado
Esta versión es una base ejecutable y organizada del proyecto. Contiene la autenticación, seguridad inicial, estructura de los 17 módulos, esquema de base de datos y seeder del administrador. Las pantallas CRUD y reglas específicas de cada módulo se implementan progresivamente según la especificación funcional definitiva.

## Interfaz actualizada
- Barra lateral oculta por defecto y desplegable con el botón ☰.
- Menú de ajustes en la esquina superior derecha: Mi perfil, Cambiar contraseña y Cerrar sesión.
- El Panel principal muestra solo bienvenida, rol y el bloque Elecciones Regionales y Municipales 2026 con mapa del Perú.
- Los 17 módulos se encuentran en la barra lateral.
