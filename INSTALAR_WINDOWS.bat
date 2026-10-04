@echo off
setlocal
cd /d %~dp0
where php >nul 2>nul || (echo PHP no esta instalado o no esta en PATH.& pause & exit /b 1)
where composer >nul 2>nul || (echo Composer no esta instalado o no esta en PATH.& pause & exit /b 1)
if not exist .env copy .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
