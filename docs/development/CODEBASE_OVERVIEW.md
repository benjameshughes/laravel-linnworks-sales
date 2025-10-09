# Codebase Overview

## Domain Focus
The application centralises ecommerce sales analytics for Linnworks-driven catalogues. Core business workflows run through actions in `app/Actions` (notably `app/Actions/Linnworks` and `Orders/MarkOrderAsProcessed.php`) and are orchestrated by services under `app/Services`. Services expose high-level entry points for Livewire components or console jobs but delegate calculations, sync routines, and state changes to actions to keep logic reusable and testable.

## HTTP & Livewire
Authenticated dashboards are provided by Livewire Volt components in `app/Livewire/Dashboard`, `Analytics`, and `Charts`, with supporting form/state classes under `app/Livewire/Components`. Route definitions live in `routes/web.php` and gate access via Laravel’s `auth` and `verified` middleware. API callbacks for Linnworks are handled in `routes/api.php` and `App\Http\Controllers\LinnworksCallbackController`, bridging incoming webhook traffic to action-driven sync processes.

## Data & Persistence
Database migrations and seeds inside `database/` define product, channel, and sales tables, while `database/factories` power Pest-based tests. `app/Repositories/ProductRepository.php` encapsulates query logic for analytics views. Background jobs in `app/Jobs` coordinate queued syncs using Laravel queues. Configuration for third-party integrations resides in `config/services.php`, with secrets provided through `.env`. Storage of generated assets or imports is handled via Laravel’s storage abstractions under `storage/`.

## Integrations & Services
`app/Services/Linnworks*` classes wrap external API calls, converting responses into domain DTOs defined in `app/DataTransferObjects`. Cross-cutting orchestrations live in `app/Services/LinnworksApiService`, which now delegates to those modular services via actions such as `FetchOrdersWithDetails`, `CheckAndUpdateProcessedOrders`, and `SyncRecentOrders`. All Linnworks interactions are read-only—we only pull data, never push or mutate remote state. `SyncRecentOrders` leans on `OpenOrders/GetOpenOrderIds` plus detail lookups, auto-detecting the default view and fulfilment location when `.env` overrides are absent. Flux and Volt dependencies in `composer.json` enable reactive Livewire experiences, and HTTP clients leverage Laravel’s built-in `Http` facade. Tailwind and Vite power the frontend build chain located in `resources/js` and `resources/css`; compiled assets are emitted into `public/build`.

## Testing & Tooling
Pest (`tests/Pest.php`) is configured as the primary harness with shared bootstrapping in `tests/TestCase.php`. Feature scenarios cover Livewire endpoints in `tests/Feature`, while `tests/Unit` homes in on repositories, actions, and services. Continuous quality relies on `./vendor/bin/pint` for PHP style compliance and `npm run build` for validating Vite bundling. Custom test doubles live alongside tests to mirror Linnworks payloads.

## Deployment Considerations
The project assumes SQLite for local usage via `database/database.sqlite`, created automatically through Composer scripts. Production deployments should swap the queue connection and cache drivers in `.env` to match the target environment. `composer run dev` launches an integrated dev stack (queue listener, scheduler, log tailing) making it easy to emulate scheduled syncs locally before promoting changes.
