# Dispatch — Order / Dispatch Management

A conventional Laravel JSON API and an independent Vue SPA in one repository. Operators enter one-time or recurring orders, attach items, assign drivers, and organize daily rides. Drivers get a mobile interface with their own stops and a guided status workflow.

## Requirements

- PHP **8.4+**, with PDO MySQL, mbstring, XML, cURL, OpenSSL, and the usual Laravel extensions. Laravel 13 itself supports PHP 8.3, but this lockfile was resolved on PHP 8.4 and includes Symfony 8 packages that require PHP 8.4.
- Composer 2
- Node.js **20.19+** and npm
- MySQL **8.0.16+** (enforced check constraints); tested on MySQL 8.0.46

Installed dependencies are pinned in `composer.lock` and `package-lock.json`. No Inertia, Livewire, Docker, Redis, paid map service, or Git initialization is required.

## Installation

```sh
composer install
cp .env.example .env
php artisan key:generate
```

Create your MySQL database and a database user with access to it. Configure `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=delivery_app
DB_USERNAME=root
DB_PASSWORD=
```

Then:

```sh
php artisan migrate --seed
npm ci
```

The required administrator account is seeded separately from demo data:

| Email | Password | Role |
| --- | --- | --- |
| admin@example.com | password123 | superadministrator |

`AdministratorSeeder` uses `Hash::make` and `firstOrCreate`, so rerunning it does not reset a changed password. Change the seeded password before production use.

Optional demo data (idempotent for the current day):

```sh
php artisan db:seed --class=DemoSeeder
```

This adds `operator@example.com` and `driver1@example.com`, `driver2@example.com`, `driver3@example.com`, all with `password123`, plus Tbilisi customers, addresses, items, orders, and rides. The default seeder creates only the required administrator.

## Running locally

Terminal 1:

```sh
php artisan serve
```

Terminal 2:

```sh
npm run dev
```

- Backend: **http://localhost:8000**
- Frontend: **http://localhost:5173**
- Open the frontend and sign in. The backend root identifies the API; it does not serve the Vue application.

Use **localhost consistently** for both services. Mixing `127.0.0.1` with `localhost` breaks shared cookie behavior. Vite uses a strict port so it does not silently move to a different origin.

Relevant configuration:

```dotenv
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
VITE_API_URL=http://localhost:8000
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000
SESSION_DOMAIN=localhost
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=false
APP_TIMEZONE=Asia/Tbilisi
VITE_APP_TIMEZONE=Asia/Tbilisi
```

Axios sends credentials and XSRF headers. Login first requests `/sanctum/csrf-cookie`. Login/logout use Laravel's web middleware for session regeneration and CSRF protection; API routes use Sanctum's stateful middleware and `auth:sanctum`. Refresh restores `/api/me`. There are no tokens stored in local storage and no public registration endpoint. Deactivated accounts are rejected on every authenticated request.

Keep backend and frontend timezones aligned; daily statistics use `APP_TIMEZONE`. After editing environment variables, run `php artisan config:clear` and restart Vite. In production, use HTTPS, secure cookies, a strong app key, `APP_DEBUG=false`, a real restricted database user, and a SPA web server that falls back to `index.html` for client routes. `npm run build` writes the static SPA to `dist/`; Laravel and the SPA can be deployed separately on the same parent domain with correctly configured CORS/Sanctum domains.

## First end-to-end workflow

1. Sign in as the administrator. In **Operators** and **Drivers**, create accounts and passwords.
2. Create a **Customer**, open the customer detail, and add a saved address. Create reusable **Items**.
3. In **Orders**, create an order. Select an optional customer/address, or enter a one-time recipient. Add multiple item rows with positive quantities. Address edits create an order snapshot without changing saved addresses.
4. Assign a driver during creation or on the order detail screen.
5. In **Rides**, create a ride for that driver/date and select storage. Add eligible orders, plan the route, then drag stops or use the up/down buttons to change the order and recalculate directions.
6. Sign out, then sign in as the driver. **Today**, **My rides**, and **My orders** contain only their own work.
7. Start an order, mark arrival, and confirm order. Add notes on the order detail; failures require a reason. Phone links use `tel:` and navigation opens an external maps navigation to the saved pin (or written address when no pin is set).
8. Sign in as the operator or administrator and refresh the dashboard or order detail. Status timestamps and actor-attributed history reflect driver updates.

## Language and storage locations

The interface defaults to **Georgian**. Use the **ქართული / English** selector on the login page or workspace header to change languages immediately; the choice is remembered in the browser. Navigation, forms, statuses, confirmations, notifications, map controls, and displayed dates are localized. Requests send `Accept-Language` so Laravel also returns Georgian or English validation and authentication errors. Addresses, names, and other entered data are preserved as entered.

Frontend translations live in `resources/js/locales/ka.json`; English source phrases serve as translation keys. Backend translations live in `lang/ka.json` and `lang/ka/validation.php`. The backend defaults to Georgian when no supported request language is supplied.

Administrators can open **Storage locations / საწყობები** to add a name, address, city, map pin, and access instructions. Address suggestions and manual map pinning work as they do on orders. Locations can be edited, deactivated, or soft-deleted. This is an admin directory; storage locations are not attached to orders. Storage-directory management requires administrator access; authenticated ride planners can read active pinned storage choices through `/api/ride-storage-locations`.

Run `php artisan migrate` when upgrading to add the storage directory table.

## Order scheduling and deletion

Orders have inclusive `date_from` and `date_to` dates, plus editable daily hours that default to **09:00–18:00**. A ride can select an unallocated pending/assigned order when its date falls anywhere inside that range and the driver matches (or is unassigned). Adding the order assigns it to that driver. Each order still belongs to at most one ride. Changing an allocated order's dates is allowed while its range continues to cover the ride date; otherwise remove it from the ride first.

Administrators can open an order and choose **Delete order**, then confirm. This soft-deletes the order: active lists, dashboard totals, driver work, and ride stops exclude it, while its record, items, original ride association, and actor-attributed history remain in the database. Operators and drivers cannot delete orders.

Run `php artisan migrate` when updating an existing installation. The schedule migration preserves existing dates as single-day ranges. `/orders` and `/api/orders` are the canonical routes; old delivery URLs/API endpoints remain compatible. Existing database/model names and `delivery_date` are retained for compatibility, with `delivery_date` mirroring the start date.

Administrators can also open a ride and choose **Delete ride**. The ride is soft-deleted and records who deleted it. Orders are retained, released from the ride, and keep their driver/status. Each order's history records the removed ride ID; pending/assigned orders can be placed on another eligible route. Historical driver-role protection includes archived rides.

## Ride route planning

Admins and operators plan rides for any driver. Drivers can create and plan their own rides using only orders already assigned to them. Choose a starting storage location, add eligible orders, then use **Plan ride automatically**. Initial planning fixes storage as the start, uses road travel times to improve stop order, and finishes at the last order without adding a return journey. The first addition of stops automatically plans when storage is selected.

Drag stops by their handles on desktop or touch devices, or use the up/down buttons. The new order is saved and the driving route recalculates while preserving that order. Storage changes and stop additions/removals also recalculate. Starting the ride requires a current plan; stop order and storage remain fixed after departure. A started ride can recalculate directions in its existing order.

The ride map shows storage, numbered order pins, and the road route. Click an order pin to view recipient/contact details, items, instructions, status actions, and driver notes; dispatchers can open the full order editor. Shared-location pins list all orders at that address. Distance, estimated driving time, and per-stop turn directions are available. Missing pins are listed with links to their order panels. Provider failures keep the saved stops and allow recalculation; stale geometry is hidden when locations or order change.

Routing supports up to 50 stops. It uses a road-time nearest-neighbour route followed by bounded route improvements, rather than guaranteeing a globally optimal solution. Estimates exclude live traffic, stop service time, and delivery time-window scheduling.

```dotenv
ROUTING_URL=https://router.project-osrm.org
ROUTING_PROFILE=driving
```

The default uses an [OSRM-compatible routing API](https://project-osrm.org/docs/v5.24.0/api/) with no API key. Configure a dedicated OSRM-compatible provider or self-hosted endpoint for production. Road matrices and directions are cached for an hour; authenticated plan requests are limited to 20 per minute. Storage coordinates are snapshotted onto each ride. Provider requests finish before transactional route persistence, which rejects concurrent changes.

## Addresses and destination maps

Order forms and saved customer addresses support address suggestions and an editable Leaflet map. Type at least three characters and choose a suggestion by clicking it or pressing Enter. Selection fills the address/city and moves the pin. Drag the pin or click the map to refine the destination; moving the pin keeps the written address unchanged. You can also use your current location, clear the pin, or enter coordinates manually. Latitude and longitude must be supplied together.

Order coordinates are a snapshot: changing an order pin does not modify the customer's saved address. Drivers see their assigned order's read-only destination map and can open navigation to the exact coordinates. Orders without coordinates still navigate using the written address.

Defaults require no API key. Address suggestions use [Photon](https://github.com/komoot/photon), with OpenStreetMap data; maps use OpenStreetMap tiles with visible attribution. Searches pass through the authenticated Laravel API, are debounced, cached for 15 minutes, and limited to 30 requests per minute per user. Manual address entry and pinning remain available when suggestions fail. Map tile failures display a retry action.

```dotenv
GEOCODING_URL=https://photon.komoot.io/api/
VITE_MAP_CENTER_LATITUDE=41.7151
VITE_MAP_CENTER_LONGITUDE=44.8271
VITE_MAP_TILE_URL=https://tile.openstreetmap.org/{z}/{x}/{y}.png
```

The center also biases address searches. Set `VITE_MAP_TILE_ATTRIBUTION` when changing tile providers. For production traffic, configure a suitable Photon-compatible geocoding endpoint and tile provider: the public Photon instance has no availability guarantee, and community tiles must follow the [OpenStreetMap tile usage policy](https://operations.osmfoundation.org/policies/tiles/). Clear Laravel configuration and restart/rebuild Vite after changing these settings.

## Architecture

### Backend

- `app/Models`: User, Customer, CustomerAddress, Item, DeliveryTask, Ride, DeliveryTaskStatusHistory, with ordinary Eloquent relationships.
- `app/Enums`: Role and DeliveryStatus.
- `app/Http/Controllers/Api`: thin resource/auth/dashboard controllers.
- `app/Http/Requests`: authoritative input validation, including address ownership, active driver role, quantity, time windows, and bounded pagination.
- `app/Http/Resources`: explicit JSON representations; lists use Laravel's `data`, `links`, and `meta` pagination envelope.
- `app/Policies`: order/ride ownership and directory permissions. Gates restrict dispatch and administrator functionality. Query scopes ensure drivers cannot list another driver's work.
- `app/Services/DeliveryService.php`: transactional creation, daily reference numbering, item syncing, assignment, transitions, timestamps, and history.
- `app/Services/RideService.php`: locked ride membership changes, ordering, and start/complete rules.
- `app/Http/Middleware/EnsureActive.php`: denies access to inactive authenticated users.

Authorization is enforced server-side. Administrators create/manage all accounts. Operators manage orders, rides, customers, addresses, and items, and may view driver/operator directories. Drivers can read only their own tasks/rides and use permitted progress actions. No repository wrappers or permission package.

### Database tables

| Table | Purpose |
| --- | --- |
| users | Shared authentication plus role, phone, active flag |
| customers | Optional recurring recipients |
| customer_addresses | Multiple saved destinations; soft-deleted on removal |
| items | Reusable definitions with active flag and optional unique SKU |
| delivery_tasks | Destination snapshot, schedule, driver, optional ride, status, priority, order and lifecycle timestamps |
| delivery_task_items | Item quantities and order-specific notes; unique task/item pair |
| rides | Driver/day group, creator and lifecycle timestamps |
| delivery_task_status_history | Every initial status/transition, actor, notes and timestamp |
| delivery_reference_sequences | Transactionally allocated per-day reference counter |
| sessions, password_reset_tokens, personal_access_tokens, cache, cache_locks, migrations | Laravel/Sanctum infrastructure |

Foreign keys prevent hard deletion of historically referenced users/customers/items/addresses/rides. Directory DELETE endpoints deactivate records; address DELETE soft-deletes the saved address. Order snapshots and history remain. Date, status, driver, customer, ride/order and reference indexes support ordinary dispatch queries. Task item quantities are constrained positive in both validation and MySQL.

References are generated as `DEL-YYYYMMDD-0001` using the creation date and a locked per-day counter, not the order ID. Concurrent creation retries deadlocks and enforces unique references.

### Workflow rules

- Without a driver: `pending`. With a driver: `assigned`.
- Driver progress: `assigned → started → arrived → delivered`.
- A driver may mark an assigned/in-progress order `failed`, with a required reason.
- Dispatchers can also cancel any nonterminal order, including a pending one.
- Delivered, failed, and cancelled statuses are terminal. Corrections/retries use a new order in this version.
- Reassign/unassign is allowed only while pending/assigned. Remove a task from its ride before changing driver or excluding the ride date from its range.
- Ride stops must have a date range covering the ride's date and share its driver, be pending/assigned, and belong to no other ride. Adding a pending stop assigns the driver automatically.
- Manual order must include every current ride task exactly once. Up/down controls persist it immediately.
- Only planned rides can change their stops/order. Start requires at least one stop and a current route from storage. Completion requires a started ride and every stop to be terminal.
- A driver's role cannot be changed after it has order/ride history. Deactivate instead. An administrator cannot deactivate or demote their own account.

Row locks and transactions serialize sensitive updates and roll back partial changes. Order status authorization is rechecked after locking the current task. Eager loading avoids obvious N+1 queries.

### Frontend

Vue 3 Composition API and `<script setup>`, Vue Router, Pinia, Axios, Tailwind 4, Vite. Source is in `resources/js`; `index.html` is the independent SPA entry point. Shared components cover page headers, modal accessibility, field errors, status/driver badges, loading/empty/error states, tables, pagination, status actions, and driver cards. A simple Pinia toast store supplies action feedback.

Pages:

- `/login`
- `/dashboard`
- `/orders`, `/orders/create`, `/orders/:id`, `/orders/:id/edit`
- `/customers`, `/customers/:id`
- `/items`, `/drivers`, `/drivers/:id`, `/operators`, `/users` (administrator only)
- `/rides`, `/rides/:id`
- `/driver/today`, `/driver/rides`, `/driver/rides/:id`, `/driver/orders`, `/driver/orders/:id`

Status presentation is centralized. `resources/js/utils.js` generates external navigation URLs using the saved coordinates when present. Dashboard lists up to 12 stops; customer/driver detail pages show recent history with links to the complete paginated order listing. Dashboard values are current on load/refresh; there is no real-time polling or websocket layer.

## API

All endpoints below use the `/api` prefix. Login is public and rate-limited; others require an active authenticated session.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | /auth/login, /auth/logout | Session login/logout |
| GET | /me | Restore current user |
| GET, POST | /storage-locations | Admin storage directory/list/create |
| GET, PUT/PATCH, DELETE | /storage-locations/{storageLocation} | Admin storage detail/update/soft-delete |
| GET | /dashboard | Today's summary and first 12 orders |
| GET, POST | /users | Admin account listing/creation |
| GET, PUT/PATCH, DELETE | /users/{user} | Admin account detail/update/deactivate |
| GET | /drivers, /operators | Dispatch directories |
| GET | /drivers/{user} | Driver assignments/recent history |
| GET, POST | /customers, /items | Paginated lists/create |
| GET, PUT/PATCH, DELETE | /customers/{customer}, /items/{item} | Detail/update/deactivate |
| GET | /addresses/search | Dispatcher-only address autocomplete (`q`, optional `city`) |
| GET, POST | /customers/{customer}/addresses | Saved addresses |
| PUT, DELETE | /customers/{customer}/addresses/{address} | Update/soft-delete saved address |
| GET, POST | /orders | Paginated tasks/create |
| GET, PUT/PATCH, DELETE | /orders/{deliveryTask} | Detail/update; admin soft deletion |
| POST | /orders/{deliveryTask}/assign-driver | Assign/unassign |
| POST | /orders/{deliveryTask}/status | Validated status transition |
| PATCH | /orders/{deliveryTask}/notes | Driver notes |
| GET, POST | /rides | Paginated list/create |
| GET, PUT/PATCH, DELETE | /rides/{ride} | Detail/update planned ride; admin soft deletion |
| POST | /rides/{ride}/tasks | Add `task_ids` |
| DELETE | /rides/{ride}/tasks/{deliveryTask} | Remove stop |
| PUT | /rides/{ride}/reorder | Ordered complete `task_ids` list |
| POST | /rides/{ride}/plan | Plan/optimize or recalculate (`storage_location_id`, `optimize`) |
| GET | /ride-storage-locations | Active pinned storage choices for ride planning |
| POST | /rides/{ride}/status | Start/complete |
| GET | /driver/today, /driver/rides, /driver/tasks/{deliveryTask} | Driver-only aliases |

Task filters: `search` (reference/name/phone/address), `date`, `date_from`, `date_to`, `status`, `driver_id`, `customer_id`, `priority`. Lists support `page` and `per_page` (1–100, default 20). Directories support `search` and `active` (`1`/`0`); rides support date/driver/status.

Creation returns 201; updates return 200; logout/deactivation/removal return 204 where appropriate. Standard Laravel JSON errors include `message` and field-indexed `errors` for 422 validation/invalid transitions. Unauthenticated is 401, forbidden is 403, missing resources are 404, and throttled login is 429. Production 500 responses use Laravel's generic error output. Frontend screens/toasts show meaningful messages and per-field form errors.

## Tests and formatting

Tests use **MySQL**, never SQLite. Create a separate empty database named `delivery_app_test`, then:

```sh
cp .env.testing.example .env.testing
# Set a test APP_KEY (copy your local APP_KEY) and test MySQL credentials.
php artisan config:clear
php artisan test
```

The test database is rebuilt by `RefreshDatabase`. Do not point it at a database with data you want to keep. `phpunit.xml` defaults `DB_DATABASE` to `delivery_app_test`.

Feature tests cover login/logout/current user, invalid credentials, unauthenticated requests, administrator/operator/driver permissions, driver query/record isolation, order create/update/items/assignment/filtering, transition rejection, timestamps/history/notes, failure/cancellation, ride creation/membership/order/removal/lifecycle, address snapshots/defaults, deactivation, and administrator self-protection.

```sh
vendor/bin/pint --test
npm run lint
npm run build
```

Formatting:

```sh
vendor/bin/pint
npm run format
```

Optional reproducible browser workflow (creates records; use a disposable development database, with both servers running):

```sh
npx playwright install chromium
npm run test:browser
# Or use an installed Chrome: CHROME_PATH=/usr/bin/google-chrome npm run test:browser
```

The additional map workflow uses a deterministic local Photon fixture and mocked map tiles:

```sh
npm run test:geocoder
# Start the disposable backend with GEOCODING_URL=http://localhost:2323/api/
npm run test:maps
```

It checks keyboard/click selection, empty/error/stale responses, draggable/clickable pins, persistence, order snapshots, and the mobile driver's read-only destination/navigation.

For different verification ports, set `BROWSER_BASE_URL` and `BROWSER_API_URL`; align CORS/Sanctum configuration with those origins. Screenshots go to `test-results/` by default.

Browser checks additionally exercised administrator session persistence and the create-account → customer/address/item → order → ride/order → mobile driver progress → administrator history workflow. Screenshots and execution results from this build are recorded in `docs/verification.md`.

## Intentionally deferred

Time-window scheduling, live traffic, tenant/company isolation, GPS tracking, proof-of-delivery uploads/signatures, notifications/SMS/WhatsApp, barcode/QR scanning, pricing/COD, exports, PWA, and real-time updates. The ride service, destination snapshots, and the navigation helper leave practical extension points without implementing speculative infrastructure.

### Client addresses and ride attachment

The Georgian customer directory is labeled კლიენტები. Creating a client requires a default address; creating and editing clients supports address autocomplete, a map pin, entrance, floor, apartment, and delivery instructions. Existing clients without addresses can be updated through the same form. The final saved address cannot be removed.

Administrators and operators can use **Add client** on a planned ride to create and attach an order from the client's default address (or first saved address for legacy records). The new order uses the ride date, 09:00–18:00, the ride driver, and saved address details. Its address is a snapshot, and its details/items can be edited through the order. Existing orders remain available through **Add stops**. Duplicate direct client attachment is blocked; additional existing orders can still be added. Route recalculation follows attachment when storage is selected.

### Saburtalo sample clients and drivers

Run `php artisan db:seed --class=SaburtaloSeeder` to add 10 active sample clients with default addresses and map pins in Saburtalo, plus 5 active drivers. It is additive and safe to rerun: existing sample profiles, passwords, and address edits are preserved. Address pins come from Photon/OpenStreetMap; names, phone numbers, apartment details, and instructions are fictional.

Driver logins: `saburtalo.driver1@example.com` through `saburtalo.driver5@example.com`, initial password `SaburtaloDemo2026!`. This seeder is explicit and is not part of the default administrator seed.

### Orders require a client

Every newly created or edited order requires a client and uses that client's single saved address automatically. There is no one-time recipient option or manual address/pin entry on the order form. **Create client** opens a modal with contact information, address details, autocomplete, and an editable map; saving selects the new client on the order without losing the schedule or items. Address changes are made on the client record.

The API resolves the client's address and copies contact/address fields into an order snapshot on save, ignoring supplied manual destination/contact fields. Existing order records are retained. A second client address cannot be added; the underlying address relation remains available for a future multi-address change.

### Mobile workspace

Phone navigation has a fixed bottom bar for primary tasks and a scrollable menu for the rest. Orders, rides, and directories use cards below 768px, and filters can be expanded when needed. Dialogs fill the phone screen, retain a visible close button, respond to the visual viewport when the keyboard opens, and respect device safe areas. Controls have larger touch targets and mobile form fields use 16px text.

Touch maps default to page scrolling; tap **Move map** to pan, and **Scroll page** to return. Pinch zoom and zoom buttons remain available. Ride stops keep drag handles and up/down alternatives. Pages load on demand to reduce the initial download. Desktop tables and navigation remain available.

### Free hosted deployment

Docker and `render.yaml` are configured for a free Render web service with persistent Neon PostgreSQL. Follow [the deployment guide](docs/deployment.md), or start with [Deploy to Render](https://render.com/deploy?repo=https://github.com/blackfrankwhite/delivery). Production requires your own administrator credentials; local/demo accounts are not deployed.
