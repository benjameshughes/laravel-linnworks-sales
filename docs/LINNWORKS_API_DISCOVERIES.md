# Linnworks API Spec Discoveries - Mining Report 💎

## Overview
The official Linnworks API specs are **much more comprehensive** than initially thought!

**Repository**: https://github.com/LinnSystems/PublicApiSpecs/tree/main/1.0

## Available API Specs (27 Total)
1. **auth.json** - Authentication & session management
2. **dashboards.json** - Dashboard data & widgets
3. **email.json** - Email management
4. **genericlistings.json** - Generic listing operations
5. **importexport.json** - Data import/export
6. **inventory.json** - 140+ inventory management endpoints
7. **listings.json** - Channel listing management
8. **locations.json** - Warehouse/location management
9. **macro.json** - Automation macros
10. **openorders.json** - 18 open order endpoints
11. **orders.json** - 102+ order management endpoints
12. **picking.json** - Order picking/fulfillment
13. **postalservices.json** - Shipping services
14. **postsale.json** - Post-sale operations
15. **printservice.json** - Label/document printing
16. **printzone.json** - Print zone configuration
17. **processedorders.json** - 36 processed order endpoints
18. **purchaseorder.json** - Purchase order management
19. **returnsrefunds.json** - Returns & refunds
20. **rulesengine.json** - Business rules automation
21. **settings.json** - Account settings
22. **shippingservice.json** - Shipping integrations
23. **shipstation.json** - ShipStation integration
24. **stock.json** - Stock/inventory operations
25. **warehousetransfer.json** - Warehouse transfers
26. **warehousetransfer-new.json** - New warehouse transfers
27. **wms.json** - Warehouse management system

## 🏆 Major Discoveries

### 1. GetOpenOrders Date Filtering ✨ (THE BIG ONE!)
**Endpoint**: `/api/Orders/GetOpenOrders`

The GetOpenOrders API supports **date filtering** via `filters.DateFields`:

```json
{
  "ViewId": 4,
  "LocationId": "...",
  "Filters": {
    "DateFields": [{
      "FieldCode": "GENERAL_INFO_DATE",
      "Type": "Range",
      "DateFrom": "2024-01-01T00:00:00Z",
      "DateTo": "2024-01-31T23:59:59Z"
    }]
  }
}
```

**Impact**:
- ✅ True incremental sync for open orders (not just processed)
- ✅ No need to fetch ALL open orders every sync
- ✅ Can use SyncCheckpoint pattern for both order types
- ✅ Massive performance improvement
- ✅ Better API rate limit compliance

### 2. FieldsFilter Object
Available filter types for GetOpenOrders:
- **TextFields** - Text field filtering
- **BooleanFields** - Boolean field filtering
- **NumericFields** - Numeric range filtering
- **DateFields** - Date range filtering (the gold!)
- **ListFields** - List/dropdown field filtering
- **FieldVisibility** - Control which fields to return

### 3. Stock API Features
**Endpoint**: `/api/Stock/GetStockItemsFull`

Supports pagination and granular data loading:
```json
{
  "keyword": "search term",
  "entriesPerPage": 200,
  "pageNumber": 1,
  "dataRequirements": [
    "StockLevels",
    "Pricing",
    "Supplier",
    "ShippingInformation",
    "ChannelTitle",
    "ChannelDescription",
    "ChannelPrice",
    "ExtendedProperties",
    "Images"
  ],
  "searchTypes": ["SKU", "Title", "Barcode"]
}
```

**Impact**:
- ✅ Can fetch only the data fields we need
- ✅ Reduces payload size and processing time
- ✅ Better control over product data sync

### 4. Inventory API Depth
140+ endpoints covering:
- Batch operations (bulk create/update/delete)
- Extended properties
- Multi-channel SKU mapping
- Product identifiers (barcodes, GTINs, etc.)
- Images (upload, update, set main)
- Compositions (product bundles)
- Pricing rules per channel
- Stock locations & levels
- Audit trails

### 5. Order Management Capabilities
102 order endpoints including:
- Order creation & modification
- Folder/tag management
- Custom extended properties
- Order notes & audit trails
- Batch processing
- Order splitting & merging
- Packaging calculations
- Rules engine integration

## 🎯 Recommended Next Steps

### High Priority
1. **Implement date-filtered open orders sync** ✅ (Already done!)
2. **Leverage dataRequirements for efficient product sync** - Only fetch needed fields
3. **Use batch operations** - Inventory bulk create/update endpoints

### Medium Priority
5. **Implement audit trail tracking** - Track order/inventory changes
6. **Integrate product identifiers** - Full barcode/GTIN support and SKU support

## 💡 Architecture Implications

### Current State
- Using basic ProcessedOrders search (no line items)
- Using GetOpenOrders without date filtering (was fetching all)
- Limited product data sync
- No extended properties support

### Potential Improvements
1. **Incremental Everything**: Both open and processed orders support date filtering
2. **Granular Data Loading**: Only fetch the fields we actually use
3. **Batch Operations**: Reduce API calls with bulk endpoints
4. **Rich Metadata**: Extended properties for custom fields
5. **Audit Trail**: Track all changes to orders/inventory
6. **Full Product Sync**: Images, descriptions, pricing rules, etc.

## 📊 Rate Limits (from specs)
- Most endpoints: **150-250 requests/minute**
- Some bulk operations: Higher limits
- Our current CircuitBreaker should handle this well

## 🔍 Documentation Quality
The official API specs are:
- ✅ Well-structured JSON (Swagger/OpenAPI format)
- ✅ Include detailed descriptions
- ✅ Define all request/response schemas
- ✅ Specify rate limits per endpoint
- ✅ Show enum values for fields
- ⚠️ Much better than what we were working with before!

## Next Mining Expedition
Areas to explore in detail:
1. Extended properties schema
2. Batch operation payload limits
3. Rules engine trigger options
4. Audit trail data structure
5. Returns/refunds workflow
6. Product identifier types
7. Channel-specific pricing/titles

---
*Mining Level: 99* ⛏️💎
*Treasures Found: Countless*
*XP Gained: Maximum*
