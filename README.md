<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## FaithAssist - Levantar en local

### Requisitos

- PHP 8.3+
- Composer 2+
- Node.js 20+ y npm
- SQLite (configuracion por defecto del proyecto)

### Pasos de instalacion

1. Clonar el repositorio

```bash
git clone <url-del-repo>
cd FaithAssist
```

2. Instalar dependencias de PHP

```bash
composer install
```

3. Configurar variables de entorno

```bash
cp .env.example .env
php artisan key:generate
```

4. Ejecutar migraciones y seeders

```bash
php artisan migrate --seed
```

5. Instalar dependencias de frontend

```bash
pnpm install
```

6. Iniciar entorno de desarrollo

```bash
composer run dev
```

Este comando levanta:

- Servidor Laravel
- Worker de colas
- Logs con pail
- Vite en modo desarrollo

### Usuarios de prueba

Generados por el seeder de usuarios:

- superadmin@faithassistqr.test / password
- coordinador@faithassistqr.test / password
- catequista@faithassistqr.test / password
- capturista@faithassistqr.test / password

### Comandos utiles

Ejecutar pruebas:

```bash
composer test
```

Ejecutar pruebas con Pest directamente:

```bash
composer test:pest
```

Ejecutar un archivo o filtro específico con Pest:

```bash
./vendor/bin/pest tests/Feature/Auth/ForgotPasswordWhatsappTest.php
./vendor/bin/pest --filter="confirms phone"
```

Validar formato PHP y pruebas antes de confirmar cambios:

```bash
composer precommit
```

El proyecto usa Husky para ejecutar `composer precommit` antes de cada commit. Si necesitas omitir el hook por una emergencia puntual, puedes usar `git commit --no-verify`, pero lo recomendado es corregir el fallo antes de confirmar.

Compilar frontend para produccion:

```bash
pnpm run build
```

Si ajustas permisos/roles y quieres refrescarlos:

```bash
php artisan db:seed --class=PermissionsSeeder
php artisan db:seed --class=SyncRolePermissionsSeeder
php artisan permission:cache-reset
```

### Solucion de problemas

Si no se reflejan cambios de permisos/policies:

```bash
php artisan optimize:clear
php artisan permission:cache-reset
```

### WhatsApp con Baileys

La integración usa una sesión persistente de Baileys y no requiere Meta Cloud API.
Configura `BAILEYS_ENABLED=true` y un `BAILEYS_INTERNAL_TOKEN` en `.env`. Ejecuta
`composer run dev` para iniciar Laravel, el worker, el scheduler y el servidor Baileys.
La primera ejecución mostrará un código QR en la terminal para vincular el teléfono;
las credenciales quedan guardadas en `storage/app/baileys/auth`.
