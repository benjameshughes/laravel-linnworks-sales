# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Sales Insight Dashboard** for Linnworks integration built with Laravel 12.x, Livewire, and Flux UI components. The application provides comprehensive sales analytics and reporting capabilities, allowing users to:

- **Overview Dashboard**: Get high-level sales metrics and performance indicators
- **Product Analysis**: Drill down into specific product performance and trends
- **Channel Analytics**: Analyze sales performance across different sales channels
- **Linnworks Integration**: Connect to Linnworks API for real-time sales data synchronization

The project is currently built on a Laravel Livewire starter kit foundation with authentication features. The sales dashboard functionality will be implemented on top of this foundation.

**Tech Stack:**
- Laravel 12.19.3 (PHP 8.2+)
- Livewire + Flux UI components
- Tailwind CSS v4
- SQLite database (development) / MySQL (production)
- Pest testing framework
- Vite for asset compilation
- Linnworks API integration (planned)

## Development Commands

### Quick Start
```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
composer dev  # Starts all development services
```

### Primary Development Command
```bash
composer dev
```
This single command runs all development services in parallel with color-coded output:
- Laravel development server (`php artisan serve`)
- Queue listener (`php artisan queue:listen --tries=1`)
- Log tailing (`php artisan pail --timeout=0`)
- Vite development server (`npm run dev`)

### Individual Commands
```bash
# Laravel
php artisan serve           # Development server
php artisan migrate         # Run migrations
php artisan db:seed         # Seed database
php artisan tinker          # Interactive REPL
php artisan pail            # Tail logs
php artisan queue:listen    # Process queues
php artisan livewire:make   # Create Livewire components
php artisan flux:activate   # Activate Flux UI components

# Frontend
npm run dev                 # Vite development server
npm run build              # Build production assets

# Testing & Code Quality
php artisan test           # Run tests
composer test              # Clear config and run tests
vendor/bin/pest            # Run Pest tests directly
vendor/bin/pint            # Run Laravel Pint (code formatter)
```

## Architecture

### Authentication System
- Complete Livewire-based authentication (register, login, password reset, email verification)
- User settings pages (Profile, Password, Appearance with dark mode)
- Located in `app/Livewire/Auth/` and `app/Livewire/Settings/`

### Livewire Components
- Authentication components in `app/Livewire/Auth/`
- Settings components in `app/Livewire/Settings/`
- Corresponding views in `resources/views/livewire/`

### Flux UI Integration
- Flux UI components are used throughout the application
- Custom Flux components in `resources/views/flux/`
- Flux must be activated with `php artisan flux:activate`

### Database
- SQLite database (default configuration)
- Standard Laravel migrations for users, cache, and jobs tables
- Database file: `database/database.sqlite`

## Testing

- **Framework**: Pest PHP
- **Configuration**: `phpunit.xml` and `tests/Pest.php`
- **Structure**: Feature tests in `tests/Feature/`, Unit tests in `tests/Unit/`
- **Test Database**: In-memory SQLite for fast execution
- **Coverage**: Authentication, Settings, Dashboard functionality

## GitHub Actions CI/CD

### Workflows
- **Lint** (`.github/workflows/lint.yml`): Runs Laravel Pint on develop/main branches
- **Tests** (`.github/workflows/tests.yml`): Full test suite with PHP 8.4 and Node 22

### Requirements
- **Flux License**: Requires `FLUX_USERNAME` and `FLUX_LICENSE_KEY` secrets for Flux UI access
- **Environment**: Uses "Testing" environment in GitHub Actions

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

## Sales Dashboard Implementation

### Core Features to Implement

**Dashboard Overview:**
- Total sales metrics (revenue, order count, average order value)
- Sales trends and performance charts
- Top performing products and channels
- Recent sales activity feed

**Product Analysis:**
- Individual product performance metrics
- Product sales trends over time
- Stock level indicators (from Linnworks)
- Product profitability analysis
- Category-based performance comparisons

**Channel Analytics:**
- Sales performance by channel (Amazon, eBay, website, etc.)
- Channel-specific metrics and trends
- Channel profitability analysis
- Cross-channel product performance

**Linnworks Integration:**
- API authentication and connection management
- Real-time data synchronization
- Order import and processing
- Product catalog synchronization
- Inventory level monitoring

### Data Models to Create
- `Sale` - Individual sales transactions
- `Product` - Product catalog from Linnworks
- `Channel` - Sales channels (Amazon, eBay, etc.)
- `LinnworksConnection` - API connection configuration
- `SalesMetric` - Calculated metrics and KPIs
- `SyncLog` - Track data synchronization activities

### Livewire Components to Build
- `SalesDashboard` - Main dashboard overview
- `ProductAnalytics` - Product performance drill-down
- `ChannelAnalytics` - Channel performance analysis
- `LinnworksSettings` - API connection management
- `SalesChart` - Reusable chart components
- `MetricsCard` - Dashboard metric display cards

### Key Files to Understand
- `composer.json`: Contains the powerful `composer dev` script
- `routes/web.php`: Main application routes
- `app/Livewire/`: All Livewire components
- `resources/views/components/layouts/app.blade.php`: Main layout
- `resources/views/livewire/`: Livewire component views

## Memories

- `flux:option in a flux:select should be flux:select.option`
- Do not use `flux:card`
- Don't use `flux:table`, check for a reusable blade component for a table and use that
- Using zinc for the colour of dark mode
- Flux buttons have an icon prop. Use that instead of adding icons in tag