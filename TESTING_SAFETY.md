# Testing Safety Guidelines

## 🚨 NEVER run these commands without `--env=testing`:

```bash
# DANGEROUS - Will delete your production database:
php artisan migrate:fresh
php artisan migrate:reset  
php artisan db:wipe

# SAFE - Uses testing environment:
php artisan migrate:fresh --env=testing
php artisan migrate:reset --env=testing
php artisan db:wipe --env=testing
```

## ✅ Safe Testing Commands:

```bash
# Run tests (automatically uses testing config)
php artisan test
vendor/bin/pest

# Run migrations for testing
php artisan migrate --env=testing
php artisan migrate:fresh --env=testing

# Seed test data
php artisan db:seed --env=testing
```

## 🛡️ Protection Mechanisms:

1. **Separate .env.testing file** - Isolated test environment
2. **In-memory SQLite** - No file database for tests  
3. **RefreshDatabase trait** - Safely resets test database
4. **phpunit.xml configuration** - Forces testing environment

## 📋 Development Workflow:

```bash
# 1. Run tests safely
php artisan test

# 2. If you need to run migrations for testing
php artisan migrate:fresh --env=testing

# 3. For production changes, be explicit
php artisan migrate --env=local
# or
php artisan migrate --env=production
```