# Copilot Instructions

## Commands

- **Install dependencies:** `composer install && cp .env.example .env && php artisan key:generate && php artisan migrate --seed && pnpm install`
- **Run the full local stack:** `composer run dev`
- **Run backend tests:** `composer test` (runs `config:clear` first, then PHPUnit via `php artisan test`). **Never run `php artisan test` or `vendor/bin/phpunit` directly.**
- **Run a single test file:** `composer test -- tests/Feature/DataScopeTest.php`
- **Run a single test/method:** `composer test -- --filter=test_chapel_index_uses_community_or_church_scope`
- **DATABASE SAFETY (critical):** the local `.env` may point to a remote Hostinger database. Feature tests use `RefreshDatabase` (`migrate:fresh`). If `bootstrap/cache/config.php` exists, Laravel ignores the `phpunit.xml` overrides (SQLite `:memory:`) and the tests will **wipe the remote database**. Always run `php artisan config:clear` before tests, never create a config cache locally (`config:cache` / `optimize`), and never run `migrate:fresh`, `migrate:refresh`, `migrate:reset` or `db:wipe` unless `DB_HOST` is local. `tests/TestCase.php` and `AppServiceProvider` enforce these guards; do not weaken them.
- **Build frontend assets:** `pnpm run build`
- **Format PHP:** `vendor/bin/pint`
- **Format frontend and shared files:** `pnpm run format`
- **Refresh permissions after security changes:** `php artisan db:seed --class=PermissionsSeeder && php artisan db:seed --class=SyncRolePermissionsSeeder && php artisan permission:cache-reset`

## High-level architecture

- This is a **Laravel 13 + Inertia.js + Vue 3** app. `routes/web.php` maps authenticated web routes to controller namespaces under `app/Http/Controllers`, and those controllers render matching Vue pages under `resources/js/Pages`.
- The application is split into three main business areas that stay mirrored across routes, controllers, pages, policies, and permissions:
  - **Regions:** states, municipalities, communities
  - **Ecclesiastes:** dioceses, deaneries, churches, chapels
  - **Security:** modules, permissions, roles, users
- The main data hierarchy is cross-cutting and matters for both UI options and authorization:
  - `state -> municipality`
  - `diocese -> municipality`
  - `municipality -> community`
  - `diocese -> deanery -> church`
  - `municipality -> church`
  - `community + church -> chapel`
- Frontend auth state is centralized in `HandleInertiaRequests`. Every page receives `page.props.auth` with the current user, profile-derived display data, flattened permission names, role names, and scope data (`municipality_ids`, `church_ids`, `full_access`).
- Most catalog screens use the same CRUD flow: the controller `index()` returns paginated Inertia props plus lookup options, while `store`, `update`, and `destroy` return JSON consumed by the shared `resources/js/components/catalogs/CatalogTable.vue`. **Users** and **roles** are exceptions and use dedicated Inertia forms plus redirects.
- User identity data is split across `users` and `profiles`; names shown in the UI are usually assembled from the related `profile`, not just `users.name`.

## Key conventions

- Permission names use **Spanish module identifiers** plus actions: `<module>.create`, `.read`, `.update`, `.delete`, `.show`. Scope overrides use `<module>.scope.all`. Keep these names aligned with policies, dashboard links, and seeders.
- The UI currently uses `.show` permissions to decide whether dashboard/navigation entries appear, while policy `viewAny()` checks usually use `.read`. When adding new modules, seed and wire both actions consistently.
- Policies extend `BasePermissionPolicy`, are registered manually in `AppServiceProvider`, and catalog controllers typically rely on `$this->authorizeResource(...)` instead of per-action authorization inside request classes.
- Data visibility is **scope-driven**. Users are assigned municipalities through `municipality_user` and churches through `church_user`. Community visibility is derived from assigned municipalities; it is not the primary scope source anymore. Chapel access is allowed when either the chapel's community is in scope or its church is in scope, unless the user has the module's `scope.all` permission.
- Many `FormRequest` classes use `UppercasesFields`, so validated text inputs for catalog entities are trimmed and uppercased before persistence. Preserve that behavior when adding new catalog requests.
- Domain models commonly use PHP attributes such as `#[Fillable]` and `#[Hidden]`, plus `SoftDeletes` and the shared `LogsActivityTrail` trait. Catalog/domain tables also include `created_by`, `updated_by`, and `deleted_by` audit columns.
- Permission grouping depends on `permissions.module_key` values (`core`, `regions`, `ecclesiastes`, `security`). Role and user forms group permissions by that field, and the seeders assume those exact buckets.
- Tests run against **in-memory SQLite** (`phpunit.xml`) and many feature tests create catalog records directly with Eloquent instead of factories. Follow that style for small authorization and scope tests.
