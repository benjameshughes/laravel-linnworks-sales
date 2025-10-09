# Archived Console Commands

This directory contains console commands that were used during development but are no longer needed for production.

## One-Time Migration Commands

These commands were used to migrate or transform data during development. They have been archived but kept for reference in case similar migrations are needed in the future.

### Commands:

- **MigrateOrderItemsToTable.php** - Migrated order items from JSON column to `order_items` table
- **CreateProductsFromOrders.php** - Created product records from order data
- **EnrichOrderItems.php** - Enriched order items with additional product data
- **RecalculateOrderTotals.php** - Recalculated order totals (one-time fix)
- **ExtractOrderAnalytics.php** - Extracted analytics data from orders

## Status

All migrations have been completed. These commands are kept for historical reference only.

**Date Archived:** 2025-10-09
**Archived By:** Claude Code
