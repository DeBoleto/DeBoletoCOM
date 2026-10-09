# AGENTS.md

Guidance for AI coding agents working in this repository.

## Project

**DeBoletoMX** — a Mexican ticket-selling platform ("deboleto"), a v2 rewrite of the legacy `deboletox` system that keeps the same MySQL schema. Domain naming is Spanish; code conventions follow standard Laravel.

Stack:

- **Backend:** Laravel 13.8 (`^13.8`), PHP ^8.3, Jetstream 5 + Fortify, Sanctum, Horizon (Redis queues), Spatie Permission + ActivityLog, OpenPay + Stripe, Twilio, DomPDF, Maatwebsite Excel.
- **Frontend:** Inertia v2 + Vue 3 + Tailwind CSS 4 + Pinia + Ziggy. Vite 8. TypeScript is installed for tooling only — components are plain JS.
- **Data:** Redis (Predis) is the public-site read layer; MySQL holds the legacy domain schema.

## Key commands

- **Dev:** `composer dev` (artisan serve + queue:listen + pail + vite concurrently), or `make start` / `make stop`.
- **Build:** `npm run dev` (vite watch) / `npm run build`.
- **Tests:** `composer test` or `php artisan test` (PHPUnit, SQLite `:memory:`, see `phpunit.xml`).
- **Sync Redis:** `php artisan events:sync-search` — syncs active events from legacy MySQL → Redis key `eventos_activos_app`.
- **Format:** `vendor/bin/pint` (defaults; no `pint.json` exists).
- **Setup:** `composer setup` (install + env + migrate + build).

## Architecture & conventions

### Modules (modular monolith)

- Each domain lives in `app/Modules/<Domain>/` with a `<Name>ServiceProvider.php` and `Models/`.
- Providers register routes via `loadRoutesFrom(base_path('routes/modules/<name>.php'))` in `boot()` and are listed in `bootstrap/providers.php`.
- Modules: `Events`, `Tickets`, `Orders`, `Venues`, `Users`, `BoxOffice`, `Payments`, `Notifications`, `Reports`. Only `events.php` and `venues.php` define routes today; the rest are empty stubs.
- HTTP controllers live centrally in `app/Http/Controllers/Api/` (not inside modules).

### Redis read-model (CQRS-lite)

- The public site reads pre-baked JSON from Redis keys, not Eloquent: `eventos_activos_app`, `eventos_sidebar_app`, `detalle_evento:{slug}`.
- `app/Console/Commands/SyncSearchData.php` populates the main key from the `view_eventos` DB view (fallback: `eventos` table).
- `routes/web.php` renders Inertia pages (`Home`, `EventDetail`, `Dashboard`) from these keys; `abort_unless($data, 404)` guards detail pages.
- API controllers (`SearchController`, `CategoriesController`, `VenuesController`) are thin readers of the same blob, with hand-built arrays (no API Resources / Form Requests / Services layer).
- Recintos pages (`/recinto/{slug}`, `/api/venues`) read the upstream keys `escenarios_unicos` (venue catalog) + `eventos_por_escenario` (events grouped by venue name) directly — no sync step. `VenuesController` joins them on exact normalized name (`app/Support/VenueNameNormalizer`) and lists only recintos with at least one active event; `/recinto/{slug}` 404s for event-less venues.

### Domain models (legacy style)

- Active-record style: heavy raw SQL helpers, overridden `save()`/`create()`/`delete()` with audit stamps (`modificacion_pk_usuario`, `creacion_fecha`, soft-delete via `eliminado = 1`).
- DB-view convention: `static view()` / `viewFind()` query `view_*` views (e.g. `view_eventos`, `view_usuarios`).
- Tables/columns are Spanish. Two user systems exist: public `App\Models\User` (Jetstream/Sanctum, `users`) vs back-office `App\Modules\Users\Models\Usuarios` (`usuarios`, phone OTP login).

### Routing

- Public URLs are Spanish: `/evento/{slug}`, `/recinto/{slug}`, `/buscar`. Data endpoints use `/api/*` and return JSON.
- Route names: lowercase, dot-separated (`home`, `event.detail`, `venue.detail`, `api.search`, `api.categories`, `api.venues`, `buscar`).
- No route model binding; `{slug}` params are matched against Redis JSON.

### Frontend

- Pages in `resources/js/Pages/`; app pages compose their own shell (`SiteHeader`, `SiteFooter`, `MobileBottomNav`) + auth modals — they do not use `AppLayout.vue`.
- All `.vue` files use plain `<script setup>` (no `lang="ts"`). Alias `@` → `resources/js` (tsconfig/jsconfig).
- Backend access: Ziggy `route()` (heaviest usage), Inertia `useForm`/`router`, and native `fetch()` for `/api/search`, `/api/venues`, `/api/categories`.
- Design tokens and Tailwind 4 CSS-first config live in `resources/css/app.css` (brand `#4ecba0` / `#22c55e`); `tailwind.config.js` is largely vestigial.
- SEO is client-side: schema.org JSON-LD + OpenGraph/Twitter meta emitted in page components.

## Testing

- PHPUnit (Pest is NOT installed). Layout: `tests/Unit` + `tests/Feature` per `phpunit.xml`.
- Tests use `RefreshDatabase`, SQLite `:memory:`, snake_case methods (`test_*`), classes end in `Test`.
- Most current tests are stock Jetstream scaffolding; no domain tests exist yet.

## Known gotchas / dangling references

- `routes/web.php` imports non-existent `App\Http\Controllers\HomeController` (unused import); `SitemapController` exists but is not routed.
- Missing referenced classes: `App\Library\*` (`FormatValidation`, `Errors`, `DateTimeBuilder`, `Encryption`, `XMLConverter`, `DataTable`), `App\Exceptions\ApplicationException`, `App\Notifications\UserResetPasswordNotification`.
- `AppServiceProvider` gates Horizon with `hasRole('admin')`, but `App\Models\User` does not use the Spatie `HasRoles` trait.
- `belongTo()` typo (instead of `belongsTo()`) appears in many models — would throw if invoked.
- Duplicate `ActivityLog` model: `app/Shared/Models/ActivityLog.php` and `app/Modules/Shared/Models/ActivityLog.php` (same table `activity_logs`).
- `app/Shared/Cache/CacheStrategy.php` uses `Cache::tags()` (Redis/Memcached only), but `.env` sets `CACHE_STORE=database` — tagged caching will fail under the database store.
- Module routes are loaded via `loadRoutesFrom()` on a plain `ServiceProvider`, so they get NO middleware group (no Inertia/CSRF middleware).
- Domain tables have no Laravel migrations (legacy MySQL schema pre-exists). Only framework tables (users, cache, jobs, Sanctum, Spatie, activity_log) are migrated.
- `config/modules.php` is declared but never referenced — `bootstrap/providers.php` is the source of truth.
- `USE_LOCAL_JSON=true` swaps event image URLs to local placeholders for offline dev; `localdev/*.json` files are offline data fixtures.

## Style

- `.editorconfig`: 4-space indent, LF endings, final newline, trim trailing whitespace.
- Pint available with defaults (no config file). No enforced CI lint step.
- Do not add code comments unless asked. Keep changes consistent with surrounding code style.
