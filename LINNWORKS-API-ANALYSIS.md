# Linnworks API Data Coverage Analysis

## API Response vs Database Schema Comparison

### ORDER LEVEL

#### ✅ Currently Captured
- `OrderId` → `linnworks_order_id`
- `NumOrderId` → `order_number`
- `Processed` → `is_processed`
- `ProcessedDateTime` → `processed_date`
- `FulfilmentLocationId` → `location_id`
- `PaidDateTime` → `paid_date`
- `GeneralInfo.Status` → `order_status`
- `GeneralInfo.Marker` → `marker`
- `GeneralInfo.IsParked` → `is_parked`
- `GeneralInfo.ReceivedDate` → `received_date`
- `GeneralInfo.Source` → `order_source`
- `GeneralInfo.SubSource` → `subsource`
- `GeneralInfo.HoldOrCancel` → `is_cancelled`
- `GeneralInfo.DespatchByDate` → `despatch_by_date`
- `GeneralInfo.NumItems` → `num_items`
- `GeneralInfo.ReferenceNum` → `channel_reference_number`
- `GeneralInfo.ExternalReferenceNum` → `external_reference`
- `TotalsInfo.Subtotal` → (calculated)
- `TotalsInfo.PostageCost` → `postage_cost`
- `TotalsInfo.Tax` → `tax`
- `TotalsInfo.TotalCharge` → `total_charge`
- `TotalsInfo.PaymentMethod` → `payment_method`
- `TotalsInfo.ProfitMargin` → `profit_margin`
- `TotalsInfo.TotalDiscount` → `total_discount`
- `TotalsInfo.Currency` → `currency`

#### ❌ NOT Captured (Available in API)

**GeneralInfo fields:**
- `LabelPrinted` (boolean)
- `LabelError` (string)
- `InvoicePrinted` (boolean)
- `PickListPrinted` (boolean)
- `IsRuleRun` (boolean)
- `PartShipped` (boolean)
- `SecondaryReference` (string)
- `HasScheduledDelivery` (boolean)
- `PickwaveIds` (array of integers)

**TotalsInfo fields:**
- `PostageCostExTax` (decimal)
- `PaymentMethodId` (guid)
- `CountryTaxRate` (decimal)
- `ConversionRate` (decimal)

**Separate Tables (Already Handled):**
- ✅ `ShippingInfo` → `order_shipping` table
- ✅ `CustomerInfo` → `addresses` JSON field
- ✅ `ExtendedProperties` → `order_properties` table
- ✅ `GeneralInfo.Identifiers` → `order_identifiers` table
- ✅ `Notes` → `order_notes` table
- ✅ `Items` → `order_items` table

---

### ORDER ITEMS LEVEL

#### ✅ Currently Captured
- `ItemId` → `item_id`
- `StockItemId` → `linnworks_item_id` (currently has parsing bug)
- `SKU` → `sku`
- `Title` → `title` (currently has parsing bug)
- `Quantity` → `quantity`
- `CategoryName` → `category`
- `PricePerUnit` → `unit_price`
- `UnitCost` → `cost_price`
- `TaxRate` → `tax_rate`
- `IsService` → `is_service`
- `BinRack` → `bin_rack`
- `DiscountValue` → `discount_amount`

#### ❌ NOT Captured (Available in API)

**Identification:**
- `ItemNumber` (string) - Order line item number
- `ItemSource` (string) - Channel/source name
- `RowId` (guid) - Database row identifier
- `OrderId` (guid) - Parent order ID (redundant with FK)
- `StockItemIntId` (int) - Integer stock item ID

**Pricing & Costs:**
- `DespatchStockUnitCost` (decimal) - Cost at despatch time
- `Discount` (decimal) - Discount amount
- `Tax` (decimal) - Tax amount for this item
- `Cost` (decimal) - Total cost excluding tax
- `CostIncTax` (decimal) - Total cost including tax
- `SalesTax` (decimal) - Sales tax amount
- `TaxCostInclusive` (boolean) - Whether tax is inclusive

**Stock & Inventory:**
- `StockLevelsSpecified` (boolean)
- `OnOrder` (int) - Quantity on order
- `Level` (int) - Current stock level
- `AvailableStock` (int) - Available stock quantity
- `StockLevelIndicator` (int) - Stock level warning indicator
- `InventoryTrackingType` (int) - How inventory is tracked
- `isBatchedStockItem` (boolean)
- `IsWarehouseManaged` (boolean)
- `IsUnlinked` (boolean) - Not linked to stock item

**Shipping:**
- `PartShipped` (boolean)
- `Weight` (decimal)
- `ShippingCost` (decimal)
- `PartShippedQty` (int)
- `BinRacks` (array) - Multiple bin rack locations with quantities

**Channel Integration:**
- `Market` (int) - Marketplace ID
- `ChannelSKU` (string) - SKU on the channel
- `ChannelTitle` (string) - Title on the channel
- `BarcodeNumber` (string)

**Images:**
- `HasImage` (boolean)
- `ImageId` (guid)

**Batch/Serial Tracking:**
- `BatchNumberScanRequired` (boolean)
- `SerialNumberScanRequired` (boolean)

**Composite Items:**
- `CompositeSubItems` (array) - Sub-items for composite products

**Metadata:**
- `AdditionalInfo` (array) - Custom additional information
- `AddedDate` (datetime) - When item was added to order

---

## Recommended Actions

### Immediate Fixes (Critical Bugs)
1. **LinnworksOrderItem.php**:
   - Add `stockItemId` property
   - Fix: Look for `Title` instead of `ItemTitle`
   - Fix: Calculate `lineTotal` from `Cost` or `CostIncTax`

### Phase 1: Enhanced Core Data (High Value)
Add these fields to capture important business data:

**orders table:**
- `label_printed` (boolean)
- `label_error` (string)
- `invoice_printed` (boolean)
- `pick_list_printed` (boolean)
- `part_shipped` (boolean)
- `secondary_reference` (string)
- `postage_cost_ex_tax` (decimal)
- `country_tax_rate` (decimal)
- `conversion_rate` (decimal)

**order_items table:**
- `item_number` (string)
- `item_source` (string)
- `channel_sku` (string)
- `channel_title` (string)
- `item_tax` (decimal) - Individual item tax
- `cost` (decimal) - Cost excluding tax
- `cost_inc_tax` (decimal) - Cost including tax
- `weight` (decimal)
- `barcode_number` (string)
- `has_image` (boolean)
- `image_id` (string)
- `part_shipped` (boolean)
- `part_shipped_qty` (int)

### Phase 2: Inventory & Stock (Medium Priority)
**order_items table:**
- `stock_level` (int)
- `available_stock` (int)
- `on_order` (int)
- `stock_level_indicator` (int)
- `is_batched_stock_item` (boolean)
- `is_warehouse_managed` (boolean)
- `batch_number_scan_required` (boolean)
- `serial_number_scan_required` (boolean)

### Phase 3: Advanced Features (Lower Priority)
**order_items table:**
- `composite_sub_items` (JSON) - For composite products
- `additional_info` (JSON) - Custom data
- `bin_racks` (JSON) - Multiple bin locations
- `added_date` (datetime)

**orders table:**
- `pickwave_ids` (JSON)
- `has_scheduled_delivery` (boolean)

---

## Migration Strategy

1. Create migrations for Phase 1 fields
2. Update DTOs to parse new fields
3. Test with real data
4. Deploy to production
5. Repeat for Phase 2 & 3

Would you like me to start with Phase 1 migrations?
