# Orders Update Command Guide

## Overview

The `orders:update` command provides a flexible way to update existing orders from Linnworks, fixing data quality issues and keeping orders in sync.

## Command

```bash
php artisan orders:update [options]
```

## Options

| Option | Description | Example |
|--------|-------------|---------|
| `--from=DATE` | Start date (YYYY-MM-DD) | `--from=2024-01-01` |
| `--to=DATE` | End date (YYYY-MM-DD) | `--to=2024-12-31` |
| `--days=N` | Number of days back (default: 90) | `--days=180` |
| `--batch-size=N` | Orders per API batch (max 200) | `--batch-size=100` |
| `--only-missing` | Only update fields with missing data | See examples |
| `--reimport` | ⚠️ Delete and re-import (destructive) | Use with caution |
| `--dry-run` | Preview changes without saving | Always test first |
| `--force` | Skip confirmation prompt | For automation |

## Use Cases

### 1. Fix Missing `processed_date` Fields

After fixing the DTO parsing, update orders to populate the missing `processed_date`:

```bash
# Dry run first (safe)
php artisan orders:update --days=729 --only-missing --dry-run

# Apply updates
php artisan orders:update --days=729 --only-missing --force
```

**What it does:**
- Fetches orders from Linnworks API
- Updates `processed_date` where NULL
- Updates `profit_margin` where 0
- Leaves other fields unchanged

### 2. Update Recent Orders

Keep recent orders up-to-date with latest Linnworks data:

```bash
# Last 30 days
php artisan orders:update --days=30 --force

# Specific date range
php artisan orders:update --from=2024-10-01 --to=2024-10-07 --force
```

### 3. Fix Data Quality Issues

Update specific fields that might be missing or incorrect:

```bash
# Update only orders with missing data
php artisan orders:update --days=365 --only-missing --force

# Update all fields (merge with existing)
php artisan orders:update --days=90 --force
```

### 4. Re-import Orders (Destructive)

If orders are severely corrupted, delete and re-import:

```bash
# ⚠️ WARNING: This deletes existing orders!
php artisan orders:update --from=2024-01-01 --to=2024-01-31 --reimport --dry-run

# Apply (only if you're sure!)
php artisan orders:update --from=2024-01-01 --to=2024-01-31 --reimport --force
```

## Update Strategy

### Merge Update (default)

Merges new data with existing records:

- Only updates fields that are missing or empty (with `--only-missing`)
- Preserves existing data
- Safe for routine updates
- Updates `last_synced_at` timestamp

**Fields updated:**
- `processed_date` (if NULL or `--only-missing` not used)
- `profit_margin` (if 0 or `--only-missing` not used)
- `tax` (if 0 or `--only-missing` not used)
- `postage_cost` (if 0 or `--only-missing` not used)
- `channel_name` (if NULL/"Unknown" or `--only-missing` not used)
- `sub_source` (if NULL or `--only-missing` not used)

### Re-import (--reimport)

Completely replaces orders:

- ⚠️ **Deletes existing order and items**
- Re-creates from API data
- Use only when data is corrupted
- Cannot be undone (always test with --dry-run first!)

## Best Practices

### 1. Always Dry Run First

```bash
php artisan orders:update --days=90 --dry-run
```

Review the output before applying changes.

### 2. Start Small

```bash
# Test with 7 days first
php artisan orders:update --days=7 --force

# Then expand
php artisan orders:update --days=90 --force
```

### 3. Use --only-missing for Safety

```bash
php artisan orders:update --days=729 --only-missing --force
```

This prevents overwriting good data with potentially incorrect data.

### 4. Monitor Logs

Check `storage/logs/laravel.log` for detailed error information.

### 5. Backup Before Destructive Operations

```bash
# Before using --reimport
php artisan db:backup  # (if you have backup command)
# or export database manually
```

## Scheduling

Add to `routes/console.php` for automated updates:

```php
use Illuminate\Support\Facades\Schedule;

// Update recent orders daily
Schedule::command('orders:update --days=7 --only-missing --force')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Weekly full update
Schedule::command('orders:update --days=90 --only-missing --force')
    ->weeklyOn(1, '03:00')
    ->withoutOverlapping();
```

## Troubleshooting

### API Returns 0 Orders

**Problem:** Command shows "No orders returned from API"

**Possible causes:**
1. Orders archived in Linnworks
2. API date range limits
3. Connection issues

**Solution:**
```bash
# Try shorter date range
php artisan orders:update --days=30 --force

# Check API connection
php artisan linnworks:test-connection
```

### Orders Not Found in Database

**Problem:** Command shows "Skipped (not in database)"

**Solution:**
```bash
# Import orders first
php artisan import:historical-orders --days=90 --force

# Then update
php artisan orders:update --days=90 --force
```

### Performance Issues

**Problem:** Command runs slowly

**Solution:**
```bash
# Reduce batch size
php artisan orders:update --days=90 --batch-size=50 --force

# Process in smaller date ranges
php artisan orders:update --from=2024-01-01 --to=2024-03-31 --force
php artisan orders:update --from=2024-04-01 --to=2024-06-30 --force
```

## Examples

### Fix All Missing Data (Recommended)

```bash
# Preview
php artisan orders:update --days=729 --only-missing --dry-run

# Apply
php artisan orders:update --days=729 --only-missing --force
```

### Update Last Quarter

```bash
php artisan orders:update --days=90 --force
```

### Update Specific Month

```bash
php artisan orders:update \
  --from=2024-09-01 \
  --to=2024-09-30 \
  --only-missing \
  --force
```

### Emergency Re-import (Last Resort)

```bash
# Test first!
php artisan orders:update \
  --from=2024-10-01 \
  --to=2024-10-07 \
  --reimport \
  --dry-run

# Apply if output looks correct
php artisan orders:update \
  --from=2024-10-01 \
  --to=2024-10-07 \
  --reimport \
  --force
```

## Output Example

```
🔄 Updating orders from Linnworks...
+------------+------------+
| Setting    | Value      |
+------------+------------+
| From Date  | 2023-10-09 |
| To Date    | 2025-10-07 |
| Batch Size | 200        |
| Mode       | LIVE UPDATE|
| Strategy   | UPDATE     |
| Filter     | Only missing|
+------------+------------+

📥 Fetching orders from Linnworks API...
📄 Processing page 1 (200 orders)...
📈 Progress: 100 fetched, 87 updated
📈 Progress: 200 fetched, 175 updated
✅ Completed processing 200 orders from API


📊 Update Summary
+-------------------------+-------+
| Metric                  | Count |
+-------------------------+-------+
| Orders Fetched from API | 200   |
| Orders Updated          | 175   |
| Orders Re-imported      | 0     |
| Orders Skipped          | 25    |
| Errors                  | 0     |
+-------------------------+-------+
✅ Orders updated successfully!
```

## Related Commands

- `php artisan import:historical-orders` - Import new historical orders
- `php artisan orders:update-processed-details` - Alternative focused update command
- `php artisan orders:sync` - Sync recent orders from Linnworks

## Support

If you encounter issues:
1. Check logs: `storage/logs/laravel.log`
2. Test with `--dry-run` first
3. Try smaller date ranges
4. Verify API connection: `php artisan linnworks:test-connection`
