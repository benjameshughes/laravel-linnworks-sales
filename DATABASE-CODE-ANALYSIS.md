# Database-Code Alignment Analysis

**Branch:** `refactor/database-code-alignment`
**Date:** 2025-10-13
**Status:** Analysis Complete

## Executive Summary

A comprehensive analysis of the sales application revealed significant disconnects between:
1. Database migrations (the intended schema)
2. Actual MySQL database schema (what's deployed)
3. Eloquent models (PHP code expectations)
4. DTOs (data structures used in imports)

The primary issue stems from evolving data requirements during migration from SQLite to MySQL, with forgotten columns and inconsistent field mappings between the DTO, Model, and database layers.

---

## Critical Findings

### 1. OrderItem Table - Major Field Mapping Issues

#### Database Schema (Actual MySQL)
```
- id
- order_id (FK)
- item_id (nullable) ← Added later
- linnworks_item_id (nullable)
- sku (nullable)
- title (nullable)
- description (nullable)
- category (nullable)
- quantity
- unit_price
- total_price
- cost_price (nullable)
- profit_margin (nullable)
- tax_rate (default 0)
- discount_amount (default 0)
- bin_rack (nullable)
- is_service (default false)
- item_attributes (json, nullable)
- timestamps
```

#### OrderItem Model Fillable
```php
'order_id',
'item_id',
'sku',
'quantity',
'unit_cost',        // ❌ MISMATCH: DB has 'unit_price'
'price_per_unit',   // ❌ MISMATCH: DB has 'unit_price'
'line_total',       // ❌ MISMATCH: DB has 'total_price'
'discount_amount',
'tax_amount',       // ❌ MISSING: DB has 'tax_rate' not 'tax_amount'
'metadata',         // ❌ MISMATCH: DB has 'item_attributes'
```

#### OrderImportDTO (Used for Bulk Imports)
```php
'item_id',
'sku',
'quantity',
'unit_cost',        // Maps to DB 'unit_price'?
'price_per_unit',   // Maps to DB 'unit_price'?
'line_total',       // Maps to DB 'total_price'
'metadata',         // Maps to DB 'item_attributes'
```

#### ❌ CRITICAL ISSUES:
1. **`unit_cost` vs `unit_price`**: Model uses `unit_cost`, DB has `unit_price`
2. **`price_per_unit` vs `unit_price`**: Model uses `price_per_unit`, DB has `unit_price`
3. **`line_total` vs `total_price`**: Model uses `line_total`, DB has `total_price`
4. **`metadata` vs `item_attributes`**: Model uses `metadata`, DB has `item_attributes`
5. **`tax_amount` vs `tax_rate`**: Model references `tax_amount`, DB has `tax_rate`
6. **Missing fields in Model**: DB has `title`, `description`, `category`, `cost_price`, `profit_margin`, `bin_rack`, `is_service` but Model doesn't include them in fillable

---

### 2. Orders Table - Field Name Inconsistencies

#### Database Schema (50 columns!)
The orders table has accumulated 50 columns through multiple migrations, creating a massive monolith.

**Duplicate/Redundant Fields:**
- `source` + `order_source` (both exist, serve same purpose)
- `sub_source` + `subsource` (both exist, serve same purpose)
- `dispatched_date` + `dispatched_at` (both exist, timestamps for same event)
- `total_charge` was renamed from `total_value` but both concepts exist

#### Model Fillable vs Database Reality
The Order model's `$fillable` array includes all 50+ fields, but there are subtle mismatches:

**Model uses:** `items` (JSON column)
**Reality:** Items should be in `order_items` table (normalized)
**Issue:** Hybrid approach where JSON column is used temporarily during import, then synced to table

---

### 3. OrderImportDTO - The "Data Hungry" Problem

The `OrderImportDTO::fromLinnworks()` method at `/app/DataTransferObjects/OrderImportDTO.php:44` attempts to flatten the entire Linnworks order structure into database-ready arrays.

#### Issues:
1. **Hard-coded field mappings** that don't match database column names
2. **Assumes column names** that were later changed in migrations
3. **Creates arrays with keys** that don't exist in actual tables
4. **No validation** that mapped fields exist in database

**Example Problem:**
```php
// OrderImportDTO line 108-120 (order_items array)
$itemsData = $linnworks->items->map(fn ($item) => [
    'order_id' => null,
    'item_id' => $item->itemId,
    'sku' => $item->sku,
    'quantity' => $item->quantity,
    'unit_cost' => $item->unitCost,      // ❌ DB expects 'cost_price'
    'price_per_unit' => $item->pricePerUnit, // ❌ DB expects 'unit_price'
    'line_total' => $item->lineTotal,    // ❌ DB expects 'total_price'
    'metadata' => json_encode([...]),    // ❌ DB expects 'item_attributes'
]);
```

This creates arrays with keys that **DON'T MATCH** the actual database columns!

---

## Detailed Field Mapping Analysis

### Order Items - Complete Breakdown

| DTO Field | Model Field | DB Column | Status |
|-----------|-------------|-----------|--------|
| `item_id` | `item_id` | `item_id` | ✅ Match |
| `sku` | `sku` | `sku` | ✅ Match |
| `quantity` | `quantity` | `quantity` | ✅ Match |
| `unit_cost` | `unit_cost` | `unit_price` | ❌ Mismatch |
| `price_per_unit` | `price_per_unit` | `unit_price` | ❌ Mismatch |
| `line_total` | `line_total` | `total_price` | ❌ Mismatch |
| `metadata` | `metadata` | `item_attributes` | ❌ Mismatch |
| - | - | `linnworks_item_id` | ⚠️ Missing from DTO |
| - | - | `title` | ⚠️ Missing from Model fillable |
| - | - | `description` | ⚠️ Missing from Model fillable |
| - | - | `category` | ⚠️ Missing from Model fillable |
| - | - | `cost_price` | ⚠️ Missing from Model fillable |
| - | - | `profit_margin` | ⚠️ Missing from Model fillable |
| - | `tax_amount` | `tax_rate` | ❌ Mismatch |
| - | `discount_amount` | `discount_amount` | ✅ Match |
| - | - | `bin_rack` | ⚠️ Missing from Model fillable |
| - | - | `is_service` | ⚠️ Missing from Model fillable |

### Orders Table - Key Issues

| DTO Field | Model Field | DB Column | Status |
|-----------|-------------|-----------|--------|
| `order_id` | `order_id` | `order_id` | ✅ Match |
| `order_id` | `linnworks_order_id` | `linnworks_order_id` | ✅ Match |
| `source` | `source` | `source` | ✅ Match |
| `source` | `order_source` | `order_source` | ⚠️ Duplicate |
| `sub_source` | `sub_source` | `sub_source` | ✅ Match |
| `subsource` | `subsource` | `subsource` | ⚠️ Duplicate |
| - | `dispatched_date` | `dispatched_date` | ⚠️ Deprecated? |
| - | `dispatched_at` | `dispatched_at` | ✅ Preferred |
| - | - | `deleted_at` | ⚠️ Soft deletes enabled |

---

## Why This Happened

### Root Cause Analysis

1. **Evolutionary Development**: Schema evolved organically through multiple migration files
2. **SQLite → MySQL Migration**: Column compatibility issues weren't fully resolved
3. **Naming Convention Changes**: Team switched between `snake_case` conventions midway
4. **DTO Created Before Final Schema**: OrderImportDTO was written assuming certain column names
5. **Bulk Import Optimization**: Bypassing Eloquent for performance meant losing validation
6. **Missing Schema Validation**: No automated tests comparing DTO fields to actual database columns

### How It's Currently "Working"

The application works despite these issues because:
1. **Eloquent's attribute casting** handles some field name differences
2. **Model accessors/mutators** provide alternative field names
3. **JSON columns** (`items`, `metadata`) store data that doesn't fit normalized schema
4. **Bulk inserts use DB::table()** which bypass Eloquent validation
5. **The Order model has `syncOrderItems()`** method that manually maps fields

---

## Impact Assessment

### Current State
- ✅ **Application Functions**: Data flows through despite mismatches
- ⚠️ **Performance Hit**: Extra JSON encoding/decoding and sync operations
- ⚠️ **Data Integrity**: Some fields may be silently dropped
- ❌ **Maintainability**: Future developers will struggle with these inconsistencies
- ❌ **Testing**: Unit tests can't rely on fillable arrays
- ❌ **Debugging**: Field name confusion makes troubleshooting difficult

### Potential Data Loss
Fields that exist in database but are never populated:
- `order_items.title`
- `order_items.description`
- `order_items.category`
- `order_items.cost_price`
- `order_items.profit_margin`
- `order_items.bin_rack`
- `order_items.is_service`
- `order_items.linnworks_item_id`

---

## Migration History Timeline

Understanding how we got here:

1. **2025-07-04**: Created `orders` and `order_items` tables with initial schema
2. **2025-07-04**: Updated orders for Linnworks (added `order_source`, `subsource`, renamed `total_value` → `total_charge`)
3. **2025-07-05**: Added sync tracking (`is_open`, `is_processed`, etc.)
4. **2025-07-06**: Added `item_id` to order_items (historical import compatibility)
5. **2025-07-06**: Added `order_id` column to orders (duplicate of `linnworks_order_id`)
6. **2025-07-07**: Added `is_processed` to orders
7. **2025-10-06**: Created Linnworks locations and views tables
8. **2025-10-07**: Added order status tracking fields
9. **2025-10-07**: Made `sku` nullable in order_items
10. **2025-10-11**: Added `is_paid` + `paid_date` to orders
11. **2025-10-12**: Created normalized tables (`order_shipping`, `order_notes`, `order_properties`, `order_identifiers`)
12. **2025-10-12**: Added extended fields to orders (`marker`, `is_parked`, `despatch_by_date`, etc.)

**Key Issue**: Each migration added fields without updating the DTO or validating field name consistency.

---

## Database Size Analysis

Current MySQL database:
- **Total Size**: 131.47 MB
- **orders table**: 79.98 MB (60% of total!)
- **order_items**: 3.55 MB
- **order_properties**: 1.55 MB
- **order_shipping**: 2.17 MB
- **order_notes**: 528 KB

The orders table is massive with 50 columns, indicating a need for better normalization.

---

## Recommendations Summary

### Immediate Actions Required

1. **Align Field Names**: Standardize on consistent naming between DTO, Model, and Database
2. **Add Missing Fields**: Include all database columns in Model fillable arrays
3. **Fix OrderImportDTO**: Update field mappings to match actual database columns
4. **Add Validation**: Create tests that validate DTO fields match database schema
5. **Consolidate Duplicate Fields**: Decide on `source` vs `order_source`, etc.
6. **Document Field Mappings**: Create a data dictionary showing DTO → Model → DB mappings

### Medium-term Improvements

1. **Refactor Orders Table**: Consider breaking into smaller tables (addresses, totals, etc.)
2. **Remove JSON Columns**: Migrate `items` JSON column data to normalized `order_items` table
3. **Standardize Timestamps**: Consolidate `dispatched_date` and `dispatched_at`
4. **Add Database Constraints**: Enforce data integrity at database level
5. **Create Migration Tests**: Automated tests that verify schema matches code expectations

### Long-term Architecture

1. **Introduce Data Layer**: Abstract database operations behind repository pattern
2. **Use Doctrine for Schema**: Consider schema-first approach with automatic validation
3. **Implement Event Sourcing**: Track all changes to orders for audit trail
4. **Split Hot/Cold Data**: Archive old orders to separate table for performance

---

## Next Steps

See `REFACTORING-PLAN.md` for detailed step-by-step refactoring strategy.
