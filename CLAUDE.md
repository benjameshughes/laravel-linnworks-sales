# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Sales Insight Dashboard** for Linnworks integration built with Laravel 12.x, Livewire, and Flux UI components. The application provides comprehensive sales analytics and reporting capabilities, allowing users to:

- **Overview Dashboard**: Get high-level sales metrics and performance indicators
- **Product Analysis**: Drill down into specific product performance and trends
- **Channel Analytics**: Analyze sales performance across different sales channels
- **Linnworks Integration**: Connect to the Linnworks API in a strictly read-only fashion to pull sales analytics data (no writes)

The project is currently built on a Laravel Livewire starter kit foundation with authentication features. The sales dashboard functionality will be implemented on top of this foundation.

**Tech Stack:**
- Laravel 12.19.3 (PHP 8.2+)
- Livewire + Flux UI components
- Tailwind CSS v4
- SQLite database (development) / MySQL (production)
- Pest testing framework
- Vite for asset compilation
- Linnworks API integration (read-only data ingestion)
- Chart.js

### Authentication System
- Complete Livewire-based authentication (register, login, password reset, email verification)
- User settings pages (Profile, Password, Appearance with dark mode)
- Located in `app/Livewire/Auth/` and `app/Livewire/Settings/`

### Livewire Components
- Authentication components in `app/Livewire/Auth/`
- Settings components in `app/Livewire/Settings/`
- Corresponding views in `resources/views/livewire/`

## Testing

- **Framework**: Pest PHP
- **Configuration**: `phpunit.xml` and `tests/Pest.php`
- **Structure**: Feature tests in `tests/Feature/`, Unit tests in `tests/Unit/`
- **Test Database**: In-memory SQLite for fast execution
- **Coverage**: Authentication, Settings, Dashboard functionality

### Workflows
- **Lint** (`.github/workflows/lint.yml`): Runs Laravel Pint on develop/main branches
- **Tests** (`.github/workflows/tests.yml`): Full test suite with PHP 8.4 and Node 22

**Queue Worker Caching:**
- When updating job code, restart queue workers: `php artisan queue:restart`
- `composer dev` uses `queue:listen` which auto-reloads on changes
- Manual `queue:work` requires restart to pick up code changes

## Development Notes

### Flux UI Credentials
This project uses Flux UI components which require authentication. The credentials are configured in CI/CD via secrets, but for local development, you may need to set up Flux authentication.

## Code Style & Best Practices

### Laravel & PHP Standards
Follow Laravel and PHP best practices when working with this codebase:

**File Organization:**
- Use Laravel's standard directory structure
- Place models in `app/Models/`
- Place Livewire components in `app/Livewire/`
- Use singular names for models (e.g., `User`, `Product`)
- Use plural names for database tables (e.g., `users`, `products`)

**Naming Conventions:**
- Classes: `PascalCase` (e.g., `ProductController`, `OrderModel`)
- Methods/variables: `camelCase` (e.g., `getUserName()`, `$productPrice`)
- Constants: `SCREAMING_SNAKE_CASE` (e.g., `MAX_RETRY_ATTEMPTS`)
- Database columns: `snake_case` (e.g., `created_at`, `user_id`)
- Routes: `kebab-case` (e.g., `/user-profile`, `/order-history`)

**Laravel Conventions:**
- Use Eloquent relationships instead of manual joins
- Leverage Laravel's built-in features (validation, middleware, etc.)
- Use resource controllers for CRUD operations
- Implement proper request validation using Form Requests
- Use Laravel's dependency injection container
- Follow the repository pattern for complex data access

**Livewire Best Practices:**
- Keep component logic focused and single-purpose
- Use Livewire's lifecycle hooks appropriately
- Implement proper validation in Livewire components
- Use Livewire's event system for component communication
- Leverage Livewire's wire:model for two-way data binding

**Code Quality:**
- Write descriptive method and variable names
- Use type hints for method parameters and return types
- Implement proper error handling and logging
- Write comprehensive tests for all functionality
- Use Laravel's built-in validation rules
- Implement proper database transactions for complex operations

**Formatting:**
- Laravel Pint is used for PHP code formatting (runs automatically in CI)
- Run `vendor/bin/pint` before committing changes
- No specific JavaScript/CSS linting configured

## Memories

- `flux:option in a flux:select should be flux:select.option`
- Do not use `flux:card`
- Don't use `flux:table`, check for a reusable blade component for a table and use that
- Using zinc for the colour of dark mode
- Flux buttons have an icon prop. Use that instead of adding icons in tag
- Flux buttons have an icon prop, use that
- Don't use try catches. Use exceptions. Laravel's built-in exception handler is amazing
- When coding follow senior-level laravel and php standards

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.14
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/scout (SCOUT) - v10
- livewire/flux (FLUXUI_FREE) - v2
- livewire/livewire (LIVEWIRE) - v3
- livewire/volt (VOLT) - v1
- larastan/larastan (LARASTAN) - v3
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v4
- laravel-echo (ECHO) - v2

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== herd rules ===

## Laravel Herd

- The application is served by Laravel Herd and will be available at: https?://[kebab-case-project-dir].test. Use the `get-absolute-url` tool to generate URLs for the user to ensure valid URLs.
- You must not run any commands to make the site available via HTTP(s). It is _always_ available through Laravel Herd.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Flux UI Free

- This project is using the free edition of Flux UI. It has full access to the free components and variants, but does not have access to the Pro components.
- Flux UI is a component library for Livewire. Flux is a robust, hand-crafted, UI component library for your Livewire applications. It's built using Tailwind CSS and provides a set of components that are easy to use and customize.
- You should use Flux UI components when available.
- Fallback to standard Blade components if Flux is unavailable.
- If available, use Laravel Boost's `search-docs` tool to get the exact documentation and code snippets available for this project.
- Flux UI components look like this:

<code-snippet name="Flux UI Component Usage Example" lang="blade">
    <flux:button variant="primary"/>
</code-snippet>

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== pest/core rules ===

## Pest

### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest <name>`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.
</laravel-boost-guidelines>
