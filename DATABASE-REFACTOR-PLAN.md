# Database Refactoring Plan

## Executive Summary

This plan refactors the `orders` and `order_items` tables to eliminate bloat, remove duplicate data, and align 100% with the DTOs that are the source of truth. The DTOs (`LinnworksOrder` and `LinnworksOrderItem`) define what data we pull from Linnworks - the database should mirror this exactly.

**Goals:**
1. ✅ Remove ALL duplicate/redundant columns
2. ✅ Standardize timestamp naming (use `_at` suffix consistently)
3. ✅ Eliminate JSON storage duplication (items, raw_data)
4. ✅ Clean up source/channel naming mess
5. ✅ Align 100% with DTO property names
6. ✅ Keep ONLY pull-only data (no computed fields)
7. ✅ Maintain related tables (shipping, notes, properties, identifiers)

---

## Current State Analysis

### Issues Found

#### Orders Table (78 columns!)
- **Duplicate columns**: `source`/`order_source`, `sub_source`/`subsource`, `order_id`/`linnworks_order_id`
- **Timestamp chaos**: Mixing `_date` and `_at` suffixes
- **Redundant JSON**: `items` JSON duplicates `order_items` table, `raw_data` bloat
- **Shipping duplication**: Shipping fields in both `orders` and `order_shipping` tables
- **31 indexes**: Many overlapping and redundant

#### Order Items Table
- **Incomplete mapping**: Missing many DTO fields
- **Wrong names**: Using `title` instead of DTO's `item_title`
- **Missing fields**: No `row_id`, `stock_item_int_id`, `item_source`, etc.

---

## DTO → Database Mapping

### LinnworksOrder DTO Properties

```php
// DTO Property → Database Column (Clean Names)

// Core Order Identity
id                      → linnworks_order_id (string, unique, indexed)
number                  → order_number (int, indexed)
channelReferenceNumber  → channel_reference_number (string, nullable)
secondaryReference      → secondary_reference (string, nullable)
externalReferenceNum    → external_reference_num (string, nullable)

// Source/Channel (CLEAN UP THE MESS!)
source                  → source (string) - ONLY THIS ONE
subsource               → subsource (string) - ONLY THIS ONE
// REMOVE: order_source, sub_source, channel_name (use source instead)

// Timestamps (STANDARDIZE TO _at)
receivedDate            → received_at (timestamp, indexed)
processedDate           → processed_at (timestamp, nullable, indexed)
paidDate                → paid_at (timestamp, nullable)
despatchByDate          → despatch_by_at (timestamp, nullable)
// REMOVE: received_date, processed_date, dispatched_date

// Money & Tax
totalCharge             → total_charge (decimal 12,2)
totalDiscount           → total_discount (decimal 12,2)
postageCost             → postage_cost (decimal 12,2)
postageCostExTax        → postage_cost_ex_tax (decimal 12,2)
tax                     → tax (decimal 12,2)
profitMargin            → profit_margin (decimal 12,2)
countryTaxRate          → country_tax_rate (decimal 5,4, nullable)
currency                → currency (char 3)
conversionRate          → conversion_rate (decimal 10,4)

// Status & Flags
status                  → status (int) - Linnworks status code
isPaid                  → is_paid (boolean)
isCancelled             → is_cancelled (boolean)
isParked                → is_parked (boolean)
partShipped             → part_shipped (boolean)

// Location & Fulfillment
locationId              → location_id (string, nullable)

// Printing & Labeling
marker                  → marker (int)
labelPrinted            → label_printed (boolean)
labelError              → label_error (string, nullable)
invoicePrinted          → invoice_printed (boolean)
pickListPrinted         → pick_list_printed (boolean)
isRuleRun               → is_rule_run (boolean)

// Delivery
hasScheduledDelivery    → has_scheduled_delivery (boolean)
pickwaveIds             → pickwave_ids (json, nullable)

// Payment
paymentMethod           → payment_method (string, nullable)
paymentMethodId         → payment_method_id (string, nullable)

// Calculated (from DTO)
numItems                → num_items (int, nullable) - Calculated from items collection

// Related Data (KEEP IN SEPARATE TABLES)
items                   → order_items table (1-to-many)
shippingInfo            → order_shipping table (1-to-1)
notes                   → order_notes table (1-to-many)
extendedProperties      → order_properties table (1-to-many)
identifiers             → order_identifiers table (1-to-many)

// REMOVE ENTIRELY
addresses               → Not in DTO (addresses come from shippingInfo)
raw_data                → BLOAT - remove this
items (json)            → DUPLICATE - remove this
dispatched_date         → Not in DTO directly
```

### LinnworksOrderItem DTO Properties

```php
// DTO Property → Database Column

// Core Identity
itemId                  → item_id (string, nullable)
stockItemId             → linnworks_item_id (string)
stockItemIntId          → stock_item_int_id (int, nullable)
rowId                   → row_id (string, nullable)
itemNumber              → item_number (string, nullable)

// SKU & Product Info
sku                     → sku (string, indexed)
itemTitle               → title (string) - From API 'Title' field
itemSource              → item_source (string, nullable)
channelSku              → channel_sku (string, nullable, indexed)
channelTitle            → channel_title (text, nullable)
barcodeNumber           → barcode_number (string, nullable)

// Quantity
quantity                → quantity (int)
partShippedQty          → part_shipped_qty (int, nullable)

// Category
categoryName            → category (string, nullable, indexed)

// Pricing (ALIGN WITH DTO NAMES!)
pricePerUnit            → unit_price (decimal 12,2)
unitCost                → cost_price (decimal 12,2)
lineTotal               → total_price (decimal 12,2)
cost                    → cost (decimal 12,2)
costIncTax              → cost_inc_tax (decimal 12,2)
despatchStockUnitCost   → despatch_stock_unit_cost (decimal 12,2)
discount                → discount (decimal 12,2)
discountValue           → discount_amount (decimal 12,2)

// Tax
tax                     → item_tax (decimal 12,2)
taxRate                 → tax_rate (decimal 5,2)
salesTax                → sales_tax (decimal 12,2)
taxCostInclusive        → tax_cost_inclusive (boolean)

// Stock Levels
stockLevelsSpecified    → stock_levels_specified (boolean)
stockLevel              → stock_level (int, nullable)
availableStock          → available_stock (int, nullable)
onOrder                 → on_order (int, nullable)
stockLevelIndicator     → stock_level_indicator (int, nullable)

// Inventory Tracking
inventoryTrackingType   → inventory_tracking_type (int, nullable)
isBatchedStockItem      → is_batched_stock_item (boolean)
isWarehouseManaged      → is_warehouse_managed (boolean)
isUnlinked              → is_unlinked (boolean)
batchNumberScanRequired → batch_number_scan_required (boolean)
serialNumberScanRequired → serial_number_scan_required (boolean)

// Shipping
partShipped             → part_shipped (boolean)
weight                  → weight (decimal 10,3)
shippingCost            → shipping_cost (decimal 12,2)
binRack                 → bin_rack (string, nullable)
binRacks                → bin_racks (json, nullable)

// Product Attributes
isService               → is_service (boolean)
hasImage                → has_image (boolean)
imageId                 → image_id (string, nullable)
market                  → market (int, nullable)

// Composite & Additional
compositeSubItems       → composite_sub_items (json, nullable)
additionalInfo          → additional_info (json, nullable)

// Metadata
addedDate               → added_at (timestamp, nullable) - STANDARDIZE TO _at

// REMOVE ENTIRELY
description             → Not in DTO
profit_margin           → CALCULATED - don't store
item_attributes         → Redundant with composite_sub_items/additional_info
```

---

## Refactored Schema

### Orders Table (Clean - ~40 columns)

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();

    // Core Identity
    $table->string('linnworks_order_id')->unique();
    $table->integer('order_number');
    $table->string('channel_reference_number')->nullable();
    $table->string('secondary_reference')->nullable();
    $table->string('external_reference_num')->nullable();

    // Source/Channel (CLEAN!)
    $table->string('source')->nullable(); // ONLY source column
    $table->string('subsource')->nullable(); // ONLY subsource column

    // Timestamps (STANDARDIZED!)
    $table->timestamp('received_at')->index();
    $table->timestamp('processed_at')->nullable()->index();
    $table->date('paid_at')->nullable();
    $table->timestamp('despatch_by_at')->nullable();

    // Money & Tax
    $table->decimal('total_charge', 12, 2);
    $table->decimal('total_discount', 12, 2)->default(0);
    $table->decimal('postage_cost', 12, 2)->default(0);
    $table->decimal('postage_cost_ex_tax', 12, 2)->default(0);
    $table->decimal('tax', 12, 2)->default(0);
    $table->decimal('profit_margin', 12, 2)->nullable();
    $table->decimal('country_tax_rate', 5, 4)->nullable();
    $table->char('currency', 3);
    $table->decimal('conversion_rate', 10, 4)->default(1);

    // Status & Flags
    $table->integer('status'); // Linnworks status code
    $table->boolean('is_paid')->default(false);
    $table->boolean('is_cancelled')->default(false);
    $table->boolean('is_parked')->default(false);
    $table->boolean('part_shipped')->default(false);

    // Location & Fulfillment
    $table->string('location_id')->nullable();

    // Printing & Labeling
    $table->integer('marker')->default(0);
    $table->boolean('label_printed')->default(false);
    $table->string('label_error')->nullable();
    $table->boolean('invoice_printed')->default(false);
    $table->boolean('pick_list_printed')->default(false);
    $table->boolean('is_rule_run')->default(false);

    // Delivery
    $table->boolean('has_scheduled_delivery')->default(false);
    $table->json('pickwave_ids')->nullable();

    // Payment
    $table->string('payment_method')->nullable();
    $table->string('payment_method_id')->nullable();

    // Calculated from items
    $table->integer('num_items')->nullable();

    // Laravel conventions
    $table->timestamps();
    $table->timestamp('last_synced_at')->nullable();
    $table->softDeletes();

    // Strategic Indexes (reduce from 31 to ~10)
    $table->index('linnworks_order_id'); // Already unique but explicit
    $table->index('order_number');
    $table->index(['received_at', 'source']); // Dashboard queries
    $table->index(['received_at', 'total_charge']); // Revenue queries
    $table->index(['status', 'received_at']); // Status filtering
    $table->index('processed_at'); // Processing queries
    $table->index('deleted_at'); // Soft deletes
});
```

### Order Items Table (Complete DTO Mapping)

```php
Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained()->onDelete('cascade');

    // Core Identity
    $table->string('item_id')->nullable();
    $table->string('linnworks_item_id');
    $table->integer('stock_item_int_id')->nullable();
    $table->string('row_id')->nullable();
    $table->string('item_number')->nullable();

    // SKU & Product Info
    $table->string('sku')->index();
    $table->string('title');
    $table->string('item_source')->nullable();
    $table->string('channel_sku')->nullable();
    $table->text('channel_title')->nullable();
    $table->string('barcode_number')->nullable();

    // Quantity
    $table->integer('quantity');
    $table->integer('part_shipped_qty')->nullable();

    // Category
    $table->string('category')->nullable()->index();

    // Pricing (COMPLETE DTO MAPPING!)
    $table->decimal('unit_price', 12, 2);
    $table->decimal('cost_price', 12, 2);
    $table->decimal('total_price', 12, 2);
    $table->decimal('cost', 12, 2);
    $table->decimal('cost_inc_tax', 12, 2);
    $table->decimal('despatch_stock_unit_cost', 12, 2);
    $table->decimal('discount', 12, 2)->default(0);
    $table->decimal('discount_amount', 12, 2)->default(0);

    // Tax
    $table->decimal('item_tax', 12, 2)->default(0);
    $table->decimal('tax_rate', 5, 2)->default(0);
    $table->decimal('sales_tax', 12, 2)->default(0);
    $table->boolean('tax_cost_inclusive')->default(false);

    // Stock Levels
    $table->boolean('stock_levels_specified')->default(false);
    $table->integer('stock_level')->nullable();
    $table->integer('available_stock')->nullable();
    $table->integer('on_order')->nullable();
    $table->integer('stock_level_indicator')->nullable();

    // Inventory Tracking
    $table->integer('inventory_tracking_type')->nullable();
    $table->boolean('is_batched_stock_item')->default(false);
    $table->boolean('is_warehouse_managed')->default(false);
    $table->boolean('is_unlinked')->default(false);
    $table->boolean('batch_number_scan_required')->default(false);
    $table->boolean('serial_number_scan_required')->default(false);

    // Shipping
    $table->boolean('part_shipped')->default(false);
    $table->decimal('weight', 10, 3)->default(0);
    $table->decimal('shipping_cost', 12, 2)->default(0);
    $table->string('bin_rack')->nullable();
    $table->json('bin_racks')->nullable();

    // Product Attributes
    $table->boolean('is_service')->default(false);
    $table->boolean('has_image')->default(false);
    $table->string('image_id')->nullable();
    $table->integer('market')->nullable();

    // Composite & Additional Data
    $table->json('composite_sub_items')->nullable();
    $table->json('additional_info')->nullable();

    // Metadata
    $table->timestamp('added_at')->nullable();

    // Laravel conventions
    $table->timestamps();

    // Strategic Indexes
    $table->index(['order_id', 'sku']); // Composite for order item lookups
    $table->index('sku'); // Individual SKU queries
    $table->index('channel_sku'); // Channel-specific lookups
    $table->index('linnworks_item_id'); // Linnworks correlation
    $table->index('item_source'); // Source filtering
    $table->index('category'); // Category filtering
});
```

---

## Migration Strategy

### Phase 1: Create New "Clean" Migration

Create a single comprehensive migration that:
1. Drops ALL order-related tables
2. Recreates them with clean schema
3. Preserves related tables (shipping, notes, properties, identifiers)

**File**: `database/migrations/YYYY_MM_DD_refactor_orders_schema.php`

### Phase 2: Update Models

Update `Order` and `OrderItem` models:
- Fix `$fillable` to match new column names
- Update `$casts` for proper type handling
- Remove any computed attributes that reference removed columns
- Update relationships

### Phase 3: Update Sync Logic

Update order syncing code:
- Map DTO properties to new column names
- Remove any code referencing `raw_data` or `items` JSON
- Update timestamp handling to use `_at` suffix

### Phase 4: Update Queries

Search codebase for:
- `order_source`, `sub_source`, `order_id` → Replace with `source`, `subsource`
- `received_date`, `processed_date`, `dispatched_date` → Replace with `_at` versions
- Any references to removed columns

---

## Breaking Changes Checklist

### Removed Columns from Orders

```
✗ order_id (use linnworks_order_id)
✗ channel_name (use source)
✗ order_source (use source)
✗ sub_source (use subsource)
✗ total_value (renamed to total_charge)
✗ total_paid (removed - same as total_charge)
✗ external_reference (renamed to external_reference_num)
✗ addresses (moved to shipping/billing tables)
✗ received_date (renamed to received_at)
✗ processed_date (renamed to processed_at)
✗ dispatched_date (not in DTO)
✗ is_resend (not in DTO)
✗ is_exchange (not in DTO)
✗ notes (use order_notes table)
✗ raw_data (REMOVED - bloat)
✗ items (use order_items table)
```

### Removed Columns from Order Items

```
✗ description (not in DTO)
✗ profit_margin (calculated, don't store)
✗ item_attributes (use composite_sub_items/additional_info)
```

### Renamed Columns

**Orders:**
- `total_value` → `total_charge`
- `external_reference` → `external_reference_num`
- `received_date` → `received_at`
- `processed_date` → `processed_at`

**Order Items:**
- `linnworks_item_id` stays the same (good!)
- All timestamp fields get `_at` suffix

---

## Code Search & Replace Guide

### Step 1: Find and Replace Column References

```bash
# Orders table
'order_source' → 'source'
'sub_source' → 'subsource'
'total_value' → 'total_charge'
'received_date' → 'received_at'
'processed_date' → 'processed_at'
->order_id → ->linnworks_order_id
->channel_name → ->source

# Order items
->description → (remove - not in DTO)
```

### Step 2: Update Query Builders

Search for:
- `->where('order_source',` → `->where('source',`
- `->where('channel_name',` → `->where('source',`
- `->orderBy('received_date',` → `->orderBy('received_at',`

### Step 3: Update Model Accessors/Mutators

Remove any accessors for deleted columns:
- `getChannelAttribute()` if normalizing channel_name
- Any computed profit/margin attributes

---

## Testing Plan

### Before Migration

1. Export production data snapshot
2. Count total orders and items
3. Run integrity checks

### After Migration

1. Verify all DTO properties map to database columns
2. Re-sync a test order to verify mapping works
3. Run dashboard queries to ensure analytics still work
4. Check all Livewire components for broken column references

---

## Benefits of This Refactor

### Storage Savings
- Remove `raw_data` JSON (potentially GBs)
- Remove duplicate `items` JSON
- Remove redundant columns
- **Estimated savings**: 30-40% table size reduction

### Performance Improvements
- Reduce indexes from 31 to ~10 on orders
- Faster writes with fewer indexes
- Cleaner query plans

### Maintainability
- Single source of truth (DTO → DB)
- No more confusion about source vs order_source vs channel_name
- Consistent timestamp naming
- Easy to understand what each column does

### Data Integrity
- No more sync issues between JSON and normalized tables
- Clear mapping from API → DTO → Database
- Proper foreign keys maintained

---

## Timeline

- **Phase 1** (Migration Creation): 2-3 hours
- **Phase 2** (Model Updates): 1-2 hours
- **Phase 3** (Sync Logic): 2-3 hours
- **Phase 4** (Query Updates): 3-4 hours
- **Testing**: 2-3 hours

**Total Estimated Time**: 1-2 days of focused work

---

## Rollback Plan

If something breaks:

1. Keep old migration file as backup
2. Can rollback migration and restore from snapshot
3. All sync logic changes are non-destructive (just column name changes)

---

## Next Steps

1. Review this plan
2. Create backup of production database
3. Create the refactored migration
4. Test on local/staging with real Linnworks data
5. Update models and sync logic
6. Search and replace column references
7. Test thoroughly
8. Deploy to production
