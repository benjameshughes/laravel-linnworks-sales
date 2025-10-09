# Sales Insight Dashboard

> **A comprehensive sales analytics and reporting platform built with Laravel 12, Livewire, and Linnworks integration.**

![Laravel](https://img.shields.io/badge/Laravel-12.x-red?logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)
![Livewire](https://img.shields.io/badge/Livewire-3.x-FB70A9)
![License](https://img.shields.io/badge/License-MIT-green)

## Overview

Sales Insight Dashboard is a powerful analytics platform that provides comprehensive sales reporting, product performance analysis, and channel analytics. Built on Laravel 12 with Livewire for reactive UI components, it integrates seamlessly with Linnworks to provide real-time sales insights.

### Key Features

- **📊 Real-time Dashboard**: Live sales metrics with auto-refreshing data
- **🔍 Product Analytics**: Detailed product performance tracking with badges and filters
- **📈 Channel Comparison**: Multi-channel performance analysis (Amazon, eBay, etc.)
- **🔄 Linnworks Integration**: Read-only API integration for order and product data
- **⚡ Queue System**: Efficient background processing with priority queues
- **🎨 Modern UI**: Built with Flux UI components and Tailwind CSS
- **🔐 Authentication**: Complete user authentication and settings management

## Tech Stack

- **Backend**: Laravel 12.19.3, PHP 8.4
- **Frontend**: Livewire 3.x, Flux UI, Tailwind CSS v4, Alpine.js
- **Database**: SQLite (development) / MySQL (production)
- **Testing**: Pest PHP
- **Build Tools**: Vite
- **API Integration**: Linnworks (read-only)

## Requirements

- PHP 8.4 or higher
- Composer
- Node.js & NPM
- SQLite (development) or MySQL (production)
- Linnworks account with API credentials

## Quick Start

### Installation

```bash
# Clone the repository
git clone <repository-url>
cd sales

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Activate Flux UI (requires license)
php artisan flux:activate

# Build assets
npm run build
```

### Development

Start all development services with a single command:

```bash
composer dev
```

This runs:
- Laravel development server
- High priority queue worker
- Medium priority queue worker
- Low priority queue worker
- Laravel scheduler
- Log tailing (pail)
- Vite development server

### Linnworks Setup

1. Add your Linnworks credentials to `.env`:
```env
LINNWORKS_APPLICATION_ID=your_app_id
LINNWORKS_APPLICATION_SECRET=your_app_secret
LINNWORKS_TOKEN=your_token
```

2. Test the connection:
```bash
php artisan linnworks:test-connection
```

3. Run initial sync:
```bash
php artisan sync:open-orders
```

For detailed setup instructions, see [Import Setup Guide](docs/getting-started/IMPORT_SETUP.md).

## Key Console Commands

### Scheduled Commands (run automatically)
- `sync:open-orders` - Sync open orders from Linnworks (every 15min)
- `sync:check-processed` - Check for processed orders (every 30min)
- `analytics:refresh-cache` - Refresh analytics cache (every 5min)
- `metrics:refresh-cache` - Refresh metrics cache (every 15min)

### Manual Commands
- `linnworks:sync-detailed-products` - Full product sync with details
- `import:historical-orders` - Import historical order data
- `channel:update-unknown` - Fix unknown channel names
- `safety:check` - Pre-deployment safety checks
- `cache:analytics-status` - View cache status

See [Console Commands](docs/development/CODEBASE_OVERVIEW.md#console-commands) for full details.

## Queue System

The application uses a three-tier priority queue system:

- **High Priority** (5min timeout, 3 retries): Order syncs, metrics refresh
- **Medium Priority** (10min timeout, 3 retries): Product syncs, inventory updates
- **Low Priority** (1hr timeout, 2 retries): Historical imports, batch processing

Queue workers are automatically started with `composer dev`.

## Testing

```bash
# Run all tests
composer test

# Run specific test suite
vendor/bin/pest --filter=Linnworks

# Run with coverage
vendor/bin/pest --coverage
```

See [Testing Safety](docs/development/TESTING_SAFETY.md) for testing guidelines.

## Documentation

- **[Documentation Index](docs/README.md)** - Complete documentation overview
- **[Getting Started](docs/getting-started/)** - Setup and import guides
- **[Linnworks Integration](docs/linnworks/)** - API integration details
- **[Development Guides](docs/development/)** - Architecture and development
- **[CLAUDE.md](CLAUDE.md)** - Claude Code instructions and project context

## Architecture

### Service Layer
- `LinnworksApiService`: Main API facade
- `ProductMetricsService`: Product analytics calculations
- `ChannelAnalyticsService`: Channel performance analysis

### Job Queue
- Master jobs: `GetOpenOrderIdsJob`, `GetAllProductsJob`
- Worker jobs: `GetOpenOrderDetailJob`, `ProcessProductJob`
- Rate limiting: `RateLimitLinnworks` middleware

### Key Models
- `Order`: Sales orders with items and totals
- `Product`: Product catalog with pricing and stock
- `OrderItem`: Individual line items
- `SyncLog`: Sync tracking and monitoring

For detailed architecture, see [Codebase Overview](docs/development/CODEBASE_OVERVIEW.md).

## Performance

- **Response Caching**: 15-minute cache for API responses
- **Rate Limiting**: 120 req/min (buffer under Linnworks 150/min limit)
- **Circuit Breaker**: Automatic failure detection and recovery
- **Queue Batching**: Efficient batch processing with delays
- **Database Indexing**: Optimized queries for analytics

## Contributing

1. Follow Laravel coding standards
2. Use Laravel Pint for code formatting: `vendor/bin/pint`
3. Write tests for new features
4. Update documentation for significant changes

## License

This project is proprietary software.

## Support

For issues or questions:
- Check the [documentation](docs/README.md)
- Review [Linnworks guides](docs/linnworks/)
- Contact: [Your contact info]

---

## Version History

### v1.0 (2025-10-09)
- Initial release
- Complete Linnworks integration
- Product analytics with badges and filtering
- Channel comparison dashboard
- Queue system optimization
- Comprehensive documentation

---

**Built with ❤️ using Laravel, Livewire, and Claude Code**
