# Field Mapping Reference Guide

Quick reference for understanding how data flows from Linnworks API → DTO → Model → Database.

---

## Order Items - Critical Field Mappings

### Current State (BROKEN)

```
LinnworksOrderItem DTO     OrderImportDTO           OrderItem Model        MySQL Database
━━━━━━━━━━━━━━━━━━━━━━━   ━━━━━━━━━━━━━━━━━━━━━━   ━━━━━━━━━━━━━━━━━━━   ━━━━━━━━━━━━━━━━
itemId                  → item_id              → item_id            → item_id ✅
stockItemId             → [missing]            → [missing]          → linnworks_item_id ❌
sku                     → sku                  → sku                → sku ✅
itemTitle               → [in metadata]        → [missing]          → title ❌
categoryName            → [in metadata]        → [missing]          → category ❌
quantity                → quantity             → quantity           → quantity ✅
unitCost                → unit_cost            → unit_cost          → unit_price ❌ MISMATCH
pricePerUnit            → price_per_unit       → price_per_unit     → unit_price ❌ MISMATCH
lineTotal               → line_total           → line_total         → total_price ❌ MISMATCH
profit                  → [missing]            → [missing]          → profit_margin ❌
tax                     → [missing]            → tax_amount         → tax_rate ❌ MISMATCH
discount                → [missing]            → discount_amount    → discount_amount ⚠️
[metadata]              → metadata             → metadata           → item_attributes ❌ MISMATCH
```

### After Phase 1 Refactoring (FIXED)

```
LinnworksOrderItem DTO     OrderImportDTO           OrderItem Model        MySQL Database
━━━━━━━━━━━━━━━━━━━━━━━   ━━━━━━━━━━━━━━━━━━━━━━   ━━━━━━━━━━━━━━━━━━━   ━━━━━━━━━━━━━━━━
itemId                  → item_id              → item_id            → item_id ✅
stockItemId             → linnworks_item_id    → linnworks_item_id  → linnworks_item_id ✅
sku                     → sku                  → sku                → sku ✅
itemTitle               → title                → title              → title ✅
categoryName            → category             → category           → category ✅
quantity                → quantity             → quantity           → quantity ✅
unitCost                → unit_cost            → unit_cost          → unit_cost ✅ (renamed)
pricePerUnit            → price_per_unit       → price_per_unit     → price_per_unit ✅ (renamed)
lineTotal               → line_total           → line_total         → line_total ✅ (renamed)
profit                  → profit_margin        → profit_margin      → profit_margin ✅
tax                     → tax_amount           → tax_amount         → tax_amount ✅ (new)
discount                → discount_amount      → discount_amount    → discount_amount ✅
[metadata]              → metadata             → metadata           → metadata ✅ (renamed)
```

---

## Orders - Duplicate Field Cleanup

### Current State (MESSY)

```
Field Purpose              Duplicate 1        Duplicate 2         Recommended
━━━━━━━━━━━━━━━━━━━━━━━━   ━━━━━━━━━━━━━━━━   ━━━━━━━━━━━━━━━━━   ━━━━━━━━━━━━━━━━
Order source              source              order_source        order_source ✅
Sub-source                sub_source          subsource           subsource ✅
Dispatch timestamp        dispatched_date     dispatched_at       dispatched_at ✅
Order identifier          linnworks_order_id  order_id            Both needed*
```

*Both `linnworks_order_id` and `order_id` serve different purposes:
- `linnworks_order_id`: Primary Linnworks GUID (unique, indexed)
- `order_id`: Legacy field for historical import compatibility

### After Phase 2 Refactoring (CLEAN)

```
Field Purpose              Field Name          Notes
━━━━━━━━━━━━━━━━━━━━━━━━   ━━━━━━━━━━━━━━━━━   ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Order source              order_source        Normalized channel name
Sub-source                subsource           Sub-channel or marketplace
Dispatch timestamp        dispatched_at       When order was dispatched
Primary identifier        linnworks_order_id  Unique GUID from Linnworks
Legacy identifier         order_id            For historical data compatibility
```

---

## Complete Field Inventory

### Orders Table (50 columns)

#### Identifiers (5)
```
✅ id                      - Auto-increment primary key
✅ linnworks_order_id      - Unique Linnworks GUID
✅ order_id                - Legacy/historical order ID
✅ order_number            - Human-readable order number (integer)
✅ channel_reference_number - Marketplace reference number
```

#### Source Information (4)
```
✅ channel_name            - Normalized channel (amazon, ebay, etc.)
✅ order_source            - Raw source from Linnworks
✅ subsource               - Sub-channel or marketplace
✅ external_reference      - External system reference
```

#### Financial Data (7)
```
✅ total_charge            - Total order value
✅ total_discount          - Discounts applied
✅ postage_cost            - Shipping cost
✅ tax                     - Tax amount
✅ total_paid              - Amount customer paid
✅ profit_margin           - Calculated profit
✅ currency                - Currency code (GBP, USD, etc.)
```

#### Status Fields (9)
```
✅ status                  - Enum: pending/processed/cancelled/refunded
✅ order_status            - Linnworks status code (integer)
✅ is_open                 - Open order (not yet dispatched)
✅ is_processed            - Order has been processed
✅ is_cancelled            - Order was cancelled
✅ is_paid                 - Payment received
✅ is_parked               - Temporarily parked
✅ has_refund              - Order has refund
✅ status_reason           - Reason for status change
```

#### Timestamps (8)
```
✅ received_date           - When order was received
✅ processed_date          - When order was processed
✅ dispatched_at           - When order was dispatched
✅ cancelled_at            - When order was cancelled
✅ paid_date               - When payment received
✅ despatch_by_date        - Target dispatch date
✅ last_synced_at          - Last sync with Linnworks
✅ deleted_at              - Soft delete timestamp
```

#### Additional Data (7)
```
✅ marker                  - Color marker (0-5)
✅ num_items               - Count of items in order
✅ payment_method          - Payment method used
✅ location_id             - Fulfillment location
✅ addresses               - JSON: billing/shipping addresses
✅ notes                   - Order notes (text)
✅ raw_data                - JSON: full API response
```

#### Sync Metadata (2)
```
✅ sync_status             - synced/pending/failed
✅ sync_metadata           - JSON: sync details, errors, unlinked items
```

#### Deprecated (should be removed in Phase 2)
```
❌ items                   - JSON: order items (should use order_items table)
❌ is_resend               - Is this a resend? (never used)
❌ is_exchange             - Is this an exchange? (never used)
```

#### Standard Laravel (2)
```
✅ created_at
✅ updated_at
```

---

### Order Items Table (20 columns)

#### Identifiers (4)
```
✅ id                      - Auto-increment primary key
✅ order_id                - Foreign key to orders
✅ item_id                 - Linnworks item ID
✅ linnworks_item_id       - Linnworks stock item ID
```

#### Product Information (4)
```
✅ sku                     - Product SKU (nullable for unlinked)
✅ title                   - Item title
✅ description             - Item description
✅ category                - Product category
```

#### Quantities (1)
```
✅ quantity                - Quantity ordered
```

#### Pricing (6)
```
✅ price_per_unit          - Unit price (what customer paid)
✅ unit_cost               - Cost per unit (what we paid)
✅ line_total              - Total for this line item
✅ profit_margin           - Calculated profit
✅ tax_rate                - Tax rate percentage
✅ tax_amount              - Tax amount
```

#### Discounts (1)
```
✅ discount_amount         - Discount applied to this item
```

#### Additional Data (2)
```
✅ bin_rack                - Warehouse location
✅ is_service              - Is this a service item?
```

#### Metadata (1)
```
✅ metadata                - JSON: additional item data
```

#### Standard Laravel (2)
```
✅ created_at
✅ updated_at
```

---

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│ LINNWORKS API                                                    │
│ GET /Orders/GetOrders or /Orders/GetOrdersById                  │
└────────────────────┬────────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────────┐
│ LinnworksOrder DTO (readonly class)                             │
│ - Parses API response into typed PHP object                     │
│ - Handles multiple API response formats                         │
│ - Contains nested LinnworksOrderItem DTOs                       │
└────────────────────┬────────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────────┐
│ OrderImportDTO::fromLinnworks()                                 │
│ - Converts LinnworksOrder into flat database-ready arrays       │
│ - Prepares: order, items, shipping, notes, properties           │
│ - All data ready for DB::table()->insert()                      │
└────────────────────┬────────────────────────────────────────────┘
                     │
                     ├─────────────┬──────────────┬──────────────┐
                     ▼             ▼              ▼              ▼
           ┌─────────────┐  ┌────────────┐  ┌──────────┐  ┌──────────┐
           │   orders    │  │order_items │  │ shipping │  │  notes   │
           │   table     │  │   table    │  │  table   │  │  table   │
           └─────────────┘  └────────────┘  └──────────┘  └──────────┘
                     │             │              │              │
                     └─────────────┴──────────────┴──────────────┘
                                   │
                                   ▼
                     ┌──────────────────────────────┐
                     │ Eloquent Models              │
                     │ - Order                      │
                     │ - OrderItem                  │
                     │ - OrderShipping              │
                     │ - OrderNote                  │
                     │ (Used for queries/display)   │
                     └──────────────────────────────┘
```

---

## Common Pitfalls to Avoid

### 1. Assuming Field Names Match
```php
// ❌ BAD: Assuming DTO fields match DB columns
DB::table('order_items')->insert($orderImportDTO->items);

// ✅ GOOD: Validate first
$validatedItems = SchemaValidator::validateDTOAgainstTable($orderImportDTO->items, 'order_items');
DB::table('order_items')->insert($validatedItems);
```

### 2. Using Eloquent Mass Assignment Without Checking Fillable
```php
// ❌ BAD: Will silently ignore fields not in $fillable
OrderItem::create($data);

// ✅ GOOD: Explicitly check fillable or use forceFill()
$item = new OrderItem();
$item->forceFill($data)->save();  // Or update $fillable array
```

### 3. Accessing Deprecated Fields
```php
// ❌ BAD: After Phase 2, these won't exist
$order->source
$order->sub_source
$order->dispatched_date

// ✅ GOOD: Use new standardized names
$order->order_source
$order->subsource
$order->dispatched_at
```

### 4. Relying on JSON Columns
```php
// ❌ BAD: JSON columns are being removed
$order->items  // Array of items (deprecated)

// ✅ GOOD: Use relationships
$order->orderItems  // Eloquent relationship
```

---

## Migration Checklist

When adding new fields in the future:

- [ ] Add column to migration file
- [ ] Add field to Model's `$fillable` array
- [ ] Add cast to Model's `casts()` method
- [ ] Update DTO to include field
- [ ] Update SchemaValidator if using
- [ ] Write test validating field exists
- [ ] Update this documentation

---

## Quick Command Reference

```bash
# Check table structure
php artisan db:table orders --database=mysql
php artisan db:table order_items --database=mysql

# Verify migrations match database
php artisan migrate:status

# Check model fillable vs database columns
php artisan tinker
> $order = new Order();
> $order->getFillable();
> Schema::getColumnListing('orders');

# Find code references to deprecated fields
rg "->source" app/
rg "'source'" app/

# Run tests to verify schema alignment
php artisan test --filter=SchemaSyncTest
```

---

**Last Updated:** 2025-10-13
**Branch:** refactor/database-code-alignment
