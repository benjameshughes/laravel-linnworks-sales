# Repository Guidelines

## Project Structure & Module Organization
The application follows Laravel conventions: domain code lives under `app/` (notably `Services`, `Repositories`, and Livewire features in `Livewire`). Linnworks-specific orchestration now lives in `app/Services/Linnworks` with executable flows extracted into `app/Actions/Linnworks` (for example, `SyncRecentOrders` powers the dashboard sync). Every integration is strictly read-only—we only pull analytics data from Linnworks and never mutate remote state. HTTP entry points are defined in `routes/web.php` for browser flows and `routes/api.php` for integrations. UI assets are managed in `resources/views` (Blade/Volt templates), `resources/js`, and `resources/css`, bundled by Vite into `public/`. Database schema changes sit in `database/migrations`, with seeders and factories alongside. Automated tests reside in `tests/Feature` and `tests/Unit`; shared scaffolding is in `tests/TestCase.php`.

## Build, Test, and Development Commands
- `composer install && npm install` prepares PHP and Node dependencies.
- `php artisan migrate --seed` provisions the SQLite database defined in `.env`.
- `composer run dev` starts the full local stack (app server, scheduler, queue listener, log tail, Vite).
- `npm run dev` runs the Vite asset watcher when you only need the frontend pipeline.
- `npm run build` produces production-ready assets under `public/build`.

## Coding Style & Naming Conventions
Adhere to PSR-12/PSR-4: classes are `StudlyCase`, methods `camelCase`, configuration keys `snake_case`. Use 4-space indentation in PHP and Tailwind-first utility classes in Blade templates. Keep business logic inside the action pattern—every workflow lives in `app/Actions`, grouped by domain (e.g., `app/Actions/Orders/SyncOrder.php`). Services in `app/Services` only orchestrate actions; avoid embedding logic directly in a service. Linnworks toggles live in `config/linnworks.php` (open-order view/location defaults, page sizes, max order caps); update `.env` rather than hard-coding. Before opening a PR, run `./vendor/bin/pint` to auto-format PHP; Vite and Tailwind handle asset linting when the dev server runs.

## Testing Guidelines
Pest is the primary test runner. Place behaviour-focused specs in `tests/Feature` (naming them `Feature/<Subject>Test.php`) and isolated logic in `tests/Unit`. Use Pest’s descriptive test names (`it('updates totals correctly')`). Execute `php artisan test` for the default suite or `./vendor/bin/pest --filter=<name>` to target a scenario. Keep factories up to date so seeded data mirrors production defaults; add assertions for emitted events when touching Livewire components.

## Additional References
Review the architectural walkthrough in [`CODEBASE_OVERVIEW.md`](CODEBASE_OVERVIEW.md) for a deeper look at domains, integrations, and tooling expectations.

## Commit & Pull Request Guidelines
Commits in history use concise, Title Case summaries that start with an imperative verb (e.g., “Implement order sync handler”). Follow the same style and keep subject lines under ~70 characters. For pull requests, include: the problem statement, the solution outline, relevant issue links, and screenshots/GIFs for UI or Volt changes. Confirm `php artisan test` and `npm run build` succeed before requesting review, and call out any follow-up work explicitly in the PR body.
