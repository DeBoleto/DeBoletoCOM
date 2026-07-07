# DeBoletoMX v2 — Design Spec
**Date:** 2026-06-30  
**Project:** DeBoletoMX v2 (`deboletov2`)  
**Predecessor:** `deboletox` (Laravel 10 + Jetstream/Inertia)  
**Author:** Claude + Juan

---

## 1. Objetivo

Reescritura limpia de la plataforma de venta de boletos DeBoletoMX usando Laravel 13, conservando la misma base de datos MySQL del proyecto anterior (`deboletox`). El objetivo es una arquitectura modular, escalable y de alto rendimiento con Redis como capa central de caché, sesiones y colas.

---

## 2. Stack Tecnológico

### Backend
| Paquete | Versión |
|---------|---------|
| `laravel/framework` | v13.17.0 |
| `laravel/jetstream` | v5.5.3 |
| `laravel/horizon` | v5.47.2 |
| `predis/predis` | v3.5.1 |
| `openpay/sdk` | v3.1.1 |
| `stripe/stripe-php` | v20.3.0 |
| `spatie/laravel-permission` | v8.1.0 |
| `spatie/laravel-activitylog` | v5.0.0 |
| `barryvdh/laravel-dompdf` | v3.1.2 |
| `maatwebsite/excel` | v3.1.69 |
| `twilio/sdk` | v8.11.6 |

### Frontend
| Paquete | Versión |
|---------|---------|
| `vue` | 3.5.39 |
| `@inertiajs/vue3` | 3.5.0 |
| `typescript` | 6.0.3 |
| `pinia` | 3.0.4 |
| `@vueuse/core` | 14.3.0 |
| `tailwindcss` | 4.3.2 |

---

## 3. Arquitectura — Monolito Modular

### Principio
Cada dominio de negocio es un módulo autocontenido con sus propios Controllers, Models, Services, Repositories, Jobs y Requests. Todos comparten la misma infraestructura (DB, Redis, Mail).

### Módulos

| Módulo | Responsabilidad |
|--------|-----------------|
| `Events` | Eventos, Funciones, Localidades, Categorías, Períodos de venta |
| `Tickets` | Boletos, Formatos, Tipos, generación QR, validación |
| `Orders` | Compras, Reservas, Checkout, Contracargos, reembolsos |
| `Venues` | Escenarios, Formatos de escenario, Asientos |
| `Users` | Usuarios, Tipos, Documentos, Privilegios |
| `BoxOffice` | Cortes de caja, Puntos de venta, Escáner QR |
| `Payments` | Stripe, Openpay, lógica de cobros, webhooks |
| `Notifications` | Twilio SMS/WhatsApp, Mail, notificaciones push |
| `Reports` | Dashboards, exports Excel/PDF, totales |

### Estructura de carpetas

```
deboletov2/
├── app/
│   ├── Modules/
│   │   ├── Events/
│   │   │   ├── Controllers/
│   │   │   ├── Models/
│   │   │   ├── Services/
│   │   │   ├── Repositories/
│   │   │   ├── Jobs/
│   │   │   ├── Requests/
│   │   │   └── EventServiceProvider.php
│   │   ├── Tickets/
│   │   │   ├── Controllers/
│   │   │   ├── Models/
│   │   │   ├── Services/
│   │   │   ├── Repositories/
│   │   │   ├── Jobs/
│   │   │   └── Requests/
│   │   ├── Orders/
│   │   ├── Venues/
│   │   ├── Users/
│   │   ├── BoxOffice/
│   │   ├── Payments/
│   │   ├── Notifications/
│   │   └── Reports/
│   └── Shared/
│       ├── Cache/           # Estrategias de caché Redis compartidas
│       ├── Traits/
│       └── Helpers/
│
├── resources/
│   └── js/
│       ├── Pages/           # Vistas Inertia organizadas por módulo
│       │   ├── Events/
│       │   ├── Tickets/
│       │   ├── Orders/
│       │   ├── Users/
│       │   ├── BoxOffice/
│       │   └── Reports/
│       ├── Components/      # Componentes Vue reutilizables
│       ├── Composables/     # useAuth, useEvents, useCart...
│       ├── Layouts/         # AppLayout, GuestLayout, BoxOfficeLayout
│       ├── Types/           # TypeScript interfaces y types
│       └── Stores/          # Pinia stores por módulo
│
├── routes/
│   ├── web.php              # Entry point + auth Jetstream
│   └── modules/
│       ├── events.php
│       ├── tickets.php
│       ├── orders.php
│       ├── venues.php
│       ├── users.php
│       ├── boxoffice.php
│       ├── payments.php
│       └── reports.php
│
└── config/
    ├── modules.php          # Registro de módulos activos
    └── (configs estándar Laravel 13)
```

---

## 4. Redis — Estrategia de Uso

Redis cumple 4 roles en la aplicación:

| Rol | Variable `.env` | Uso |
|-----|-----------------|-----|
| Cache | `CACHE_STORE=redis` | Catálogo eventos, aforos, localidades |
| Session | `SESSION_DRIVER=redis` | Sesiones de usuario |
| Queue | `QUEUE_CONNECTION=redis` | Emails, PDFs, SMS, exports |
| Broadcast | `BROADCAST_CONNECTION=redis` | Notificaciones tiempo real (futuro) |

### Estrategia de caché con tags

```php
// Repository lee de Redis primero
Cache::tags(['events'])->remember("event:{$id}", 3600, fn() =>
    Evento::with(['funciones', 'localidades'])->find($id)
);

// Al modificar, invalida solo el tag del módulo
Cache::tags(['events'])->flush();
```

### Colas Redis (Horizon)

| Cola | Jobs |
|------|------|
| `emails` | Notificaciones post-compra |
| `tickets` | Generación PDF/QR boletos |
| `reports` | Exports Excel pesados |
| `sms` | Twilio WhatsApp/SMS |

Dashboard disponible en `/horizon`.

---

## 5. Flujo de Request Inertia

```
Browser → Inertia Request
  → Middleware (Auth, Privilege check vía Redis)
  → Controller (delgado — solo orquesta)
  → Repository::find($id)
      → Redis HIT  → retorna datos cacheados
      → Redis MISS → consulta MySQL → guarda en Redis → retorna
  → Resource/DTO (transforma para el frontend)
  → Inertia::render('Events/Show', $data)
  → Vue 3 + TypeScript recibe props tipadas
  → Pinia store actualiza estado local
```

---

## 6. Configuración de Base de Datos

Misma conexión que `deboletox`. No hay migración de datos — se conecta a la DB existente.

```ini
DB_CONNECTION=mysql
DB_HOST=50.62.223.84
DB_PORT=3306
DB_DATABASE=deboleto
DB_USERNAME=mtdeboleto
```

Los Models de cada tabla residen en `app/Modules/{Dominio}/Models/`. Ejemplo de mapeo:

| Tabla DB | Model | Módulo |
|----------|-------|--------|
| `eventos` | `Evento.php` | Events |
| `eventos_funciones` | `EventoFuncion.php` | Events |
| `eventos_localidades` | `EventoLocalidad.php` | Events |
| `boletos` | `Boleto.php` | Tickets |
| `compras` | `Compra.php` | Orders |
| `compras_boletos` | `CompraBoleto.php` | Orders |
| `escenarios` | `Escenario.php` | Venues |
| `usuarios` | `Usuario.php` | Users |
| `puntos_ventas` | `PuntoVenta.php` | BoxOffice |
| `cortes_cajas` | `CortesCajas.php` | BoxOffice |

---

## 7. Autenticación

- **Laravel Jetstream v5** con stack Inertia
- **Spatie Permission v8** reemplaza el sistema custom de privilegios de `deboletox`
- **Spatie ActivityLog v5** reemplaza `ActivityLogger.php` custom

---

## 8. Variables de Entorno Completas

```ini
APP_NAME=DeBoletoMX
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/deboletov2/public

LOG_CHANNEL=custom_daily
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=50.62.223.84
DB_PORT=3306
DB_DATABASE=deboleto
DB_USERNAME=mtdeboleto
DB_PASSWORD=

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=redis

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

MAIL_MAILER=smtp
MAIL_HOST=mail.deboleto.mx
MAIL_PORT=587
MAIL_USERNAME=notificaciones@deboleto.mx
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=notificaciones@deboleto.mx
MAIL_FROM_NAME="${APP_NAME}"

STRIPE_KEY=
STRIPE_SECRET=
OPENPAY_ID=
OPENPAY_PRIVATE_KEY=
OPENPAY_PRODUCTION=false

TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=
```

---

## 9. Decisiones Clave

- **Sin migración de código** — reescritura limpia desde cero
- **DB compartida** — `deboletov2` lee la misma DB que `deboletox` durante desarrollo paralelo
- **Models por dominio** — no existe `app/Models/` global, cada módulo es dueño de sus modelos
- **Redis como first-class citizen** — cache, session, queue y broadcast todos en Redis
- **predis/predis** como cliente Redis (no phpredis extension) para portabilidad
- **Horizon** para visibilidad y control de colas en producción
