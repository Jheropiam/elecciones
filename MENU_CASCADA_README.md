# Menú lateral en cascada

Esta versión modifica únicamente la navegación lateral.

## Comportamiento
- Menú principal expandido por defecto.
- Ámbito y Personeros usan submenú tipo Treeview.
- Solo un grupo puede permanecer abierto a la vez.
- El grupo correspondiente a la página actual se abre automáticamente.
- Al abrir un grupo cerca del final del sidebar, el sidebar se desplaza suavemente para hacer visible el submenú.
- Al contraer el sidebar se mantienen los iconos y tooltips.
- No se instala AdminLTE.
- No modifica la base de datos ni las reglas de negocio.

## Archivos modificados
- `resources/views/layouts/app.blade.php`
- `public/css/app.css`

## Prueba
1. Copiar/reemplazar el proyecto de prueba.
2. Ejecutar:
   `php artisan optimize:clear`
3. Ingresar al sistema.
4. Hacer clic en `Ámbito`.
5. Hacer clic en `Personeros`.
6. Probar `Personero de Mesa` y `Avance`.
7. Contraer y volver a expandir el sidebar.
