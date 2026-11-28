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
- Modules: `Events`, `Tickets`, `Orders`, `Venues`, `Users`, `BoxOffice`, `Dashboard`, `Payments`, `Checkout`, `Notifications`, `Reports` (+ `Shared`, no provider). Routes live in `events.php`, `venues.php`, `users.php`, `checkout.php`, `boxoffice.php`, `dashboard.php`; `tickets`, `orders`, `payments`, `notifications`, `reports` are empty stubs.
- Public-site/API controllers live in `app/Http/Controllers/Api/`; the ported modules (`BoxOffice`, `Dashboard`, `Checkout`, `Users`) keep their own `Http/{Controllers,Middleware,Requests}` plus `Services/`, `Queries/`, `Support/`.
- Areas and guards (`config/auth.php`, provider `usuarios` for all three):
  - **Taquilla** `/boxoffice/*` (`boxoffice.*`, guard `boxoffice`): seat-map sales, ~30 ticket print formats (Blade/DomPDF, mm layouts calibrated to physical printers — do not change), cash cut, cortesías, canje, reports. Menu access via `EnsureMenuPrivilege`, Gate abilities in `BoxOfficeAbilities`.
  - **Admin** `/dashboard/*` (`admin.*`, guard `admin`, module `Dashboard`): users/privileges, events, localities, venues, formats, tickets, refunds, monitors. Jetstream's customer account page is at `/admin` (route `dashboard`).
  - **Checkout web** (guard `customer`, phone OTP via Stytch or email/password in `Users`): `/eventos/mapa/{evento}` (full venue map), `/checkoutprocess/{slug}` (multi-zone flow; `/evento/{slug}/asientos` 301s here), `/compra/{evento}/pago` (card 3DS, MSI 3/6, SPEI, coupons, Ticket Defender), OpenPay webhook + scheduled `checkout:reconcile` (every 5 min).
- Event-ID-specific legacy business rules are isolated in `config/boxoffice.php` and `config/checkout.php`; keep them there.
- Checkout writes order + tickets as Pending (status 4) before charging and issues them only on confirmed payment. v2 purchases are tagged `"source":"web-v2"` in `eventos_compras_logs.json_compra`; webhook/reconcile ignore legacy purchases.
- Seat holds: Redis (atomic) mirrored into `eventos_reserva_boleto` (`pk_seccion` = locality name), and reads merge both. Legacy deboletox still sells against the same DB, so never drop the mirror. Availability is broadcast in realtime over Laravel Reverb; hold TTL is 7 min (web and taquilla).

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
- SEO is server-rendered: routes/controllers pass an `seo` prop built with `App\Support\Seo\SeoMeta` (+ `SchemaOrg` for JSON-LD); `resources/views/partials/seo.blade.php` prints title/description/canonical/OG/Twitter/JSON-LD into the HTML, and `components/SeoHead.vue` keeps them in sync on client navigation. `HandleInertiaRequests` shares a default `seo` with `noindex` for every route outside `home`, `event.detail`, `venue.detail`, `buscar`.
- Meta pixel: `App\Modules\Checkout\Tracking\MetaTracker::track()` queues each event twice with one `event_id` — in the session (shared prop `metaPixel`, fired by `resources/js/support/metaPixel.js` on Inertia `navigate` with `fbq('trackSingle')`) and to the Conversions API (`SendConversionEvent`). Pixel IDs come from `eventos.pixel` (browser only) + `eventos_pixel` (browser + API with token). `_fbp`/`_fbc` cookies are excluded from encryption in `bootstrap/app.php`.
- `/sitemap.xml` and `/robots.txt` are dynamic routes (`SitemapController`, cached 1h) built from Redis: home, active events, categories, venues with events. There is no `public/robots.txt`.
- Test charges: `pk_usuario`s listed in `CHECKOUT_TEST_CHARGE_USERS` (comma-separated) are charged `CHECKOUT_TEST_CHARGE_AMOUNT` (default 1 MXN) on OpenPay card payments, without installments; the purchase keeps real totals and `json_compra.test_charge = true`. Test purchases skip Meta conversions.

## Testing

- PHPUnit (Pest is NOT installed). Layout: `tests/Unit` + `tests/Feature` per `phpunit.xml`.
- Tests use `RefreshDatabase`, SQLite `:memory:`, snake_case methods (`test_*`), classes end in `Test`.
- Besides Jetstream scaffolding, domain tests live in `tests/{Feature,Unit}/{BoxOffice,Checkout}`, `tests/Feature/Admin`, `SeoMetaTagsTest`, `SitemapTest`, `StageMapParserTest`. None run against the real legacy MySQL schema.
- `ExampleTest` fails without a reachable Redis.

## Known gotchas / dangling references

- `routes/web.php` imports non-existent `App\Http\Controllers\HomeController` (unused import).
- Missing referenced classes: `App\Library\*` (`FormatValidation`, `Errors`, `DateTimeBuilder`, `Encryption`, `XMLConverter`, `DataTable`), `App\Exceptions\ApplicationException`, `App\Notifications\UserResetPasswordNotification`.
- `AppServiceProvider` gates Horizon with `hasRole('admin')`, but `App\Models\User` does not use the Spatie `HasRoles` trait.
- `belongTo()` typo (instead of `belongsTo()`) appears in many models — would throw if invoked.
- Duplicate `ActivityLog` model: `app/Shared/Models/ActivityLog.php` and `app/Modules/Shared/Models/ActivityLog.php` (same table `activity_logs`).
- `app/Shared/Cache/CacheStrategy.php` uses `Cache::tags()` (Redis/Memcached only), but `.env` sets `CACHE_STORE=database` — tagged caching will fail under the database store.
- Module routes are loaded via `loadRoutesFrom()` on a plain `ServiceProvider`, so they get no middleware group by default. The ported modules (`boxoffice`, `dashboard`, `checkout`, `users`) wrap their routes in `Route::middleware('web')`; `events.php` and `venues.php` still have none (no session/CSRF/Inertia middleware).
- Domain tables have no Laravel migrations (legacy MySQL schema pre-exists). Only framework tables (users, cache, jobs, Sanctum, Spatie, activity_log) are migrated.
- `config/modules.php` is declared but never referenced — `bootstrap/providers.php` is the source of truth.
- `USE_LOCAL_JSON=true` swaps event image URLs to local placeholders for offline dev; `localdev/*.json` files are offline data fixtures.

## Style

- `.editorconfig`: 4-space indent, LF endings, final newline, trim trailing whitespace.
- Pint available with defaults (no config file). No enforced CI lint step.
- Do not add code comments unless asked. Keep changes consistent with surrounding code style.
