# Findings priorizados del proyecto FaithAssist

Fecha de revisión: 2026-09-30

Este documento resume oportunidades de mejora detectadas en el proyecto, ordenadas por prioridad de resolución. La prioridad considera impacto en seguridad, riesgo funcional, facilidad de explotación, mantenibilidad y valor para el equipo.

## P0 - Crítico

### 1. Cerrar autorización en gestión de usuarios y roles

**Ubicación principal:**

- `app/Http/Controllers/Security/UserController.php`
- `app/Http/Controllers/Security/RoleController.php`
- `tests/Feature/Security/UserControllerTest.php`
- `tests/Feature/Security/RoleControllerTest.php`

**Hallazgo:**

Los controladores de usuarios y roles no aplican una política completa ni `authorizeResource(...)`. En los tests actuales incluso se documenta que `RoleController` y `UserController` no tienen autorización explícita y que cualquier usuario autenticado puede acceder a esas pantallas.

Esto es especialmente sensible porque estas pantallas permiten administrar roles, permisos, usuarios y scopes. Aunque parte de la lógica de `UserRequest` limita qué permisos puede delegar un editor, el acceso inicial a index, create, edit, store y update sigue quedando demasiado abierto.

**Riesgo:**

- Usuarios autenticados sin permisos administrativos podrían listar usuarios o roles.
- Podrían abrir formularios de edición/creación.
- Si alguna validación posterior falla o cambia, el controlador no tiene una barrera de autorización fuerte.
- El módulo de seguridad depende demasiado de validaciones indirectas en requests/servicios.

**Recomendación:**

1. Crear `UserPolicy` y `RolePolicy`.
2. Registrar ambas políticas en `AppServiceProvider`.
3. Aplicar autorización en los controladores, idealmente con:
   - `$this->authorizeResource(User::class, 'usuario')`
   - `$this->authorizeResource(Role::class, 'role')`
4. Alinear permisos esperados:
   - `usuarios.read`, `usuarios.create`, `usuarios.update`, `usuarios.delete`
   - `roles.read`, `roles.create`, `roles.update`, `roles.delete` si se agrega eliminación
5. Actualizar tests para cubrir:
   - Usuario sin permiso recibe 403.
   - Usuario con permiso correcto puede acceder.
   - Usuario no puede asignar roles/permisos fuera de su alcance.

**Prioridad sugerida:** resolver primero.

## P1 - Alta

### 2. Agregar rate limiting al login

**Ubicación principal:**

- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Http/Controllers/Auth/AuthController.php`
- `app/Services/Auth/ForgotPasswordService.php`

**Hallazgo:**

El flujo de recuperación de contraseña ya usa `RateLimiter`, pero el login no tiene protección equivalente. `LoginRequest::authenticate()` hace `Auth::attempt()` directamente y devuelve error de validación si falla.

**Riesgo:**

- Mayor exposición a fuerza bruta de credenciales.
- Ataques repetitivos por IP/email no quedan limitados.
- El endpoint de login es uno de los puntos públicos más importantes de la aplicación.

**Recomendación:**

1. Implementar throttling por combinación de email normalizado + IP.
2. Bloquear temporalmente después de varios intentos fallidos.
3. Limpiar contador al autenticar correctamente.
4. Agregar tests para:
   - Intentos fallidos incrementan contador.
   - Exceso de intentos devuelve error de throttle.
   - Login exitoso limpia el contador.

**Prioridad sugerida:** inmediatamente después de cerrar autorización en seguridad.

### 3. Aplicar throttling a endpoints sensibles

**Ubicación principal:**

- `routes/web.php`
- `app/Http/Controllers/WhatsappMessageController.php`
- `app/Http/Controllers/Masses/MassAttendanceController.php`
- `app/Http/Controllers/Catechism/ChildController.php`

**Hallazgo:**

Hay endpoints autenticados con impacto operativo alto que no muestran límites explícitos por frecuencia. Ejemplos:

- Envío de WhatsApp.
- Escaneo/registro de asistencias.
- Exportaciones masivas de niños.
- Exportación PDF por lote.
- Descargas y consultas de estado de lotes.

**Riesgo:**

- Un usuario autorizado, pero comprometido o automatizado, podría disparar demasiadas acciones.
- Puede generar carga excesiva en colas, almacenamiento, WhatsApp/Baileys o generación de PDF/Excel.
- Riesgo de abuso accidental por doble click, reintentos del navegador o scripts.

**Recomendación:**

1. Definir rate limiters nombrados por caso de uso.
2. Aplicarlos en rutas o middleware:
   - `throttle:whatsapp-send`
   - `throttle:attendance-scan`
   - `throttle:exports`
3. Considerar límites por usuario autenticado, no solo por IP.
4. Agregar respuestas claras para 429.
5. Cubrir con tests de Feature.

**Prioridad sugerida:** alta, especialmente para WhatsApp y exportaciones.

## P2 - Media alta

### 4. Crear pipeline de CI

**Ubicación principal:**

- `.github/workflows/`
- `composer.json`
- `package.json`
- `phpunit.xml`

**Hallazgo:**

No hay workflows de GitHub Actions detectados. El proyecto sí tiene comandos claros para pruebas backend, formato PHP y build frontend, pero no están automatizados.

**Riesgo:**

- Cambios con regresiones pueden llegar a la rama principal sin ser detectados.
- El equipo depende de ejecución manual.
- Errores de build frontend o tests PHP pueden descubrirse tarde.

**Recomendación:**

Crear un workflow inicial, por ejemplo `.github/workflows/ci.yml`, que ejecute:

1. `composer install --no-interaction --prefer-dist`
2. `php artisan test`
3. `vendor/bin/pint --test`
4. `pnpm install --frozen-lockfile`
5. `pnpm run build`

Si el tiempo de CI crece, separar backend/frontend en jobs independientes.

**Prioridad sugerida:** después de los ajustes de seguridad principales.

### 5. Endurecer renderizado de paginación para evitar `v-html`

**Ubicación principal:**

- `resources/js/components/AppPagination.vue`

**Hallazgo:**

El componente usa `v-html="link.label"` para mostrar etiquetas de paginación. Normalmente estas etiquetas vienen de Laravel y contienen valores como números o entidades HTML para flechas, pero `v-html` siempre aumenta la superficie de XSS si alguna entrada llega contaminada.

**Riesgo:**

- Riesgo bajo si el origen de `links` siempre es Laravel paginator.
- Riesgo mayor si el componente se reutiliza con datos no confiables.
- El patrón puede ser copiado a otros componentes.

**Recomendación:**

1. Evitar `v-html` y renderizar texto normal.
2. Normalizar etiquetas como `&laquo;`, `&raquo;` o usar iconos dedicados como ya se hace para anterior/siguiente.
3. Si se requiere HTML, sanitizar explícitamente o limitar a una lista segura.

**Prioridad sugerida:** media alta por ser cambio pequeño con buen beneficio preventivo.

### 6. Mejorar manejo de errores en `useCatalogCrud`

**Ubicación principal:**

- `resources/js/composables/useCatalogCrud.js`

**Hallazgo:**

`apiFetch` llama directamente a `response.json()`. Si el backend devuelve HTML, una respuesta vacía, un 500 no JSON o un error de proxy, el parsing puede fallar y evitar que se muestre un mensaje amigable.

**Riesgo:**

- La UI puede romper el flujo de edición/creación/eliminación ante errores inesperados.
- Los usuarios pueden quedarse sin feedback claro.
- Dificulta depurar errores reales en catálogos.

**Recomendación:**

1. Leer primero `Content-Type`.
2. Parsear JSON solo si corresponde.
3. Usar fallback seguro:
   - `message: 'Ocurrió un error inesperado.'`
   - `errors: {}`
4. Mantener compatibilidad con errores de validación de Laravel.

**Prioridad sugerida:** media alta porque afecta muchos catálogos.

## P3 - Media

### 7. Agregar tooling frontend de calidad

**Ubicación principal:**

- `package.json`
- `resources/js/**/*.vue`
- `resources/js/**/*.js`

**Hallazgo:**

El frontend tiene build y formato, pero no tiene lint, type-check ni pruebas frontend configuradas. El proyecto ya contiene bastante lógica en composables y componentes compartidos, por ejemplo temas, CRUD de catálogos, formularios y selección de permisos.

**Riesgo:**

- Errores de props, eventos o cambios de contrato con Inertia pueden detectarse tarde.
- Refactors frontend son más riesgosos.
- La lógica compartida puede crecer sin cobertura.

**Recomendación:**

1. Agregar ESLint para Vue.
2. Considerar migración gradual a TypeScript o usar `vue-tsc` si se adopta TS.
3. Agregar Vitest para composables críticos:
   - `useCatalogCrud`
   - `useTheme`
   - helpers de permisos/scope si se extraen
4. Incluir esos checks en CI.

**Prioridad sugerida:** después de CI básico y fixes de seguridad.

### 8. Centralizar reglas especiales de permisos

**Ubicación principal:**

- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Services/Security/UserService.php`
- `app/Http/Requests/Security/UserRequest.php`
- Seeders de permisos

**Hallazgo:**

Existen reglas especiales hardcoded, por ejemplo el tratamiento particular de `asistencias_manuales.*` y permisos de exportación que se agregan bajo condiciones específicas. La lógica funciona, pero está distribuida entre middleware, requests y servicios.

**Riesgo:**

- Cambios futuros pueden romper consistencia entre UI, permisos efectivos y permisos delegables.
- Es fácil olvidar actualizar una de las superficies.
- La intención de negocio queda implícita en código disperso.

**Recomendación:**

1. Crear una clase o config de permisos especiales, por ejemplo:
   - `config/permissions.php`
   - `App\Support\PermissionRules`
2. Centralizar:
   - Permisos no heredables.
   - Permisos delegables especiales.
   - Permisos de exportación.
   - Overrides por módulo.
3. Reutilizarla en middleware, requests, servicios y tests.

**Prioridad sugerida:** media, ideal antes de seguir creciendo el módulo de seguridad.

### 9. Documentar operación y despliegue

**Ubicación principal:**

- `README.md`
- `.env.example`
- `composer.json`
- `package.json`
- `config/baileys.php`

**Hallazgo:**

`composer.json` conserva nombre y descripción del skeleton Laravel. Además, el proyecto tiene varias piezas operativas importantes: Horizon, colas, scheduler, Baileys, permisos, seeders, imports, exports y configuración de ajustes.

**Riesgo:**

- Nuevos colaboradores tardan más en levantar el entorno.
- Despliegues pueden omitir pasos críticos como permisos, colas o variables de Baileys.
- La operación de WhatsApp/Baileys depende de conocimiento tácito.

**Recomendación:**

Actualizar documentación con:

1. Requisitos de PHP, Composer, Node, pnpm y base de datos.
2. Instalación local completa.
3. Comandos comunes:
   - `composer run dev`
   - `php artisan test`
   - `pnpm run build`
   - seeders de permisos
4. Variables `.env` relevantes.
5. Operación de Horizon/colas/scheduler.
6. Operación de Baileys:
   - `BAILEYS_ENABLED`
   - `BAILEYS_BASE_URL`
   - `BAILEYS_INTERNAL_TOKEN`
   - auth dir
   - vinculación de WhatsApp
7. Pasos de despliegue y troubleshooting.

**Prioridad sugerida:** media.

## P4 - Media baja

### 10. Revisar límites y validaciones de archivos

**Ubicación principal:**

- `app/Http/Controllers/Settings/SettingsController.php`
- `app/Http/Requests/Catechism/*Import*`
- Servicios de import/export

**Hallazgo:**

Hay carga de imágenes en ajustes y carga/importación de archivos Excel. Algunas validaciones ya existen, pero conviene revisar de forma transversal límites de tamaño, extensiones, MIME real, rutas de almacenamiento y limpieza de archivos temporales.

**Riesgo:**

- Archivos grandes pueden impactar almacenamiento o memoria.
- Archivos temporales pueden acumularse.
- Validaciones inconsistentes entre módulos.

**Recomendación:**

1. Documentar límites por tipo de archivo.
2. Confirmar validación por MIME/extensión.
3. Agregar limpieza programada para temporales si aplica.
4. Agregar tests de archivos inválidos y límites máximos.

**Prioridad sugerida:** media baja, salvo que el sistema ya esté recibiendo muchos imports.

### 11. Revisar uso de logs y errores operativos

**Ubicación principal:**

- `app/Http/Controllers/Catechism/ChildController.php`
- `servers/baileys-server.js`
- Jobs de WhatsApp/import/export

**Hallazgo:**

Hay manejo de errores y logs en varias partes, pero conviene estandarizar estructura y contexto. En procesos asíncronos como WhatsApp, importación y PDFs, los logs son clave para soporte.

**Riesgo:**

- Dificultad para diagnosticar fallos en producción.
- Mensajes inconsistentes entre jobs y controladores.
- Errores de terceros pueden quedar con poco contexto.

**Recomendación:**

1. Estandarizar claves de contexto:
   - `user_id`
   - `batch_id`
   - `child_id`
   - `message_id`
   - `job_id`
2. Evitar registrar datos sensibles completos.
3. Agregar estados visibles para lotes fallidos.
4. Documentar mensajes operativos frecuentes.

**Prioridad sugerida:** media baja.

## Orden recomendado de resolución

1. Cerrar autorización en usuarios y roles.
2. Agregar rate limiting al login.
3. Aplicar throttling a WhatsApp, asistencias y exportaciones.
4. Crear CI con tests, Pint y build frontend.
5. Eliminar `v-html` en paginación.
6. Hacer robusto el parser de errores de `useCatalogCrud`.
7. Agregar lint/type-check/tests frontend.
8. Centralizar reglas especiales de permisos.
9. Mejorar documentación operativa y metadata del proyecto.
10. Revisar validaciones/límites de archivos.
11. Estandarizar logs de procesos asíncronos.

## Notas finales

La base del proyecto es sólida: existe separación por dominio, servicios, repositorios, políticas para muchos catálogos y pruebas Feature amplias. Las mejoras más urgentes no requieren rediseñar la arquitectura; se enfocan en cerrar superficies sensibles, automatizar validaciones y reducir riesgos de mantenimiento conforme el sistema crezca.
