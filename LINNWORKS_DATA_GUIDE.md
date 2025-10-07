# Linnworks API Data Integration Guide

## ✅ COMPLETED IMPROVEMENTS

### 1. **Product Cost & Pricing Capture** ✨
**Status**: Implemented (limited by Linnworks data quality)

**What we capture now:**
- ✅ `purchase_price` - Product cost from Linnworks (when available)
- ✅ `retail_price` - Extracted from `ItemChannelPrices` array
- ✅ `unit_cost` - Item-level cost from orders (when available)

**Data Quality:**
- Your Linnworks has 0% cost data coverage (0/411 items)
- Products return `PurchasePrice: 0` from API
- Order items return `UnitCost: 0` from API

**Analytics Enhancement:**
- ✅ Profit calculations now use order item costs (when available)
- ✅ Cost Data Coverage metric shows data quality
- ✅ Warnings displayed when cost data is missing
- ✅ Helpful tips on improving data quality

**To get profit calculations working:**
1. Add purchase prices in Linnworks for your products
2. Ensure costs are set on product master records
3. Re-sync products: `php artisan sync:linnworks-products --limit=1000 --force`

---

### 2. **Product Sync Fixed** 🔧
**File**: `app/Services/Linnworks/Products/ProductsService.php:56`

**Problems Fixed:**
- ❌ Was using: `Inventory/GetStockItems` (404 error)
- ✅ Now using: `Stock/GetStockItems` (GET method)
- ❌ Only 5 products syncing
- ✅ Now: 4,562 products syncing successfully

**Enhanced Data Extraction:**
- ✅ StockLevels array properly parsed (first location)
- ✅ Retail prices from ItemChannelPrices
- ✅ Purchase prices stored as null when zero (cleaner data tracking)

---

## 🔴 HIGH-VALUE DATA NOT YET CAPTURED

### **Orders - Shipping Analytics** 📦

**Available in API but not captured:**
```php
'vendor' => $data['Vendor']                    // Courier name (Evri, DX, etc.)
'postal_service_name' => $data['PostalServiceName']
'postal_tracking_number' => $data['PostalTrackingNumber']
'total_weight' => $data['TotalWeight']
'package_category' => $data['PackageCategory']
```

**Impact**:
- Shipping cost analysis by courier
- Delivery performance tracking
- Weight-based shipping optimization

**Implementation**: Add to `app/Models/Order.php` fillable fields

---

### **Orders - Payment & Discounts** 💳

**Available but not captured:**
```php
'payment_method' => $data['PaymentMethod']    // Card, PayPal, etc.
'total_discount' => $data['TotalDiscount']    // Already in schema!
'postage_cost_ex_tax' => $data['PostageCostExTax']
'time_diff' => $data['timeDiff']              // Days to process
'dispatch_by_date' => $data['DespatchByDate']
```

**Impact**:
- Payment gateway analytics
- Discount effectiveness tracking
- Fulfillment SLA monitoring

---

### **Products - Inventory Planning** 📊

**Available but not captured:**
```php
// From StockLevels array
'quantity_in_orders' => $stockLevel['InOrder']
'quantity_due' => $stockLevel['Due']
'minimum_level' => $stockLevel['MinimumLevel']

// From root level
'tax_rate' => $stockItem['TaxRate']
'creation_date' => $stockItem['CreationDate']
'supplier_id' => $stockItem['SupplierId']
```

**Impact**:
- Reorder point alerts
- Product lifecycle analysis
- Supplier performance tracking

---

### **Products - Multi-Channel Strategy** 🌐

**Available but not fully utilized:**
```php
// ItemChannelPrices array
foreach ($stockItem['ItemChannelPrices'] as $channelPrice) {
    'channel' => $channelPrice['Source']       // Amazon, eBay, etc.
    'price' => $channelPrice['Price']
    'tag' => $channelPrice['Tag']
}

// ItemChannelDescriptions array
'channel_descriptions' => $stockItem['ItemChannelDescriptions']
```

**Impact**:
- Price comparison across channels
- Channel-specific product optimization
- Multi-marketplace strategy insights

---

## 📊 CURRENT DATA QUALITY ISSUES

### From Your Analytics:
```
❌ Total Profit: £0.00           → No cost data in Linnworks
❌ Total Tax: £0.00              → Not captured from open orders
❌ Total Postage: £0.00          → Not captured from open orders
❌ DIRECT channel: £0.00 revenue → Orders missing pricing
❌ Cost Data Coverage: 0.0%      → No purchase prices in Linnworks
```

**Root Causes:**
1. Open orders don't include tax/postage in API response
2. Processed orders would have this data
3. Purchase prices not set in Linnworks product catalog

---

## 🎯 RECOMMENDED PRIORITY

### **Phase 1: Core Improvements** (Quick Wins)
1. ✅ **DONE**: Product purchase prices capture
2. ⚠️ **BLOCKED**: Need cost data in Linnworks first
3. 📝 **NEXT**: Add shipping fields to orders
4. 📝 **NEXT**: Add payment method tracking

### **Phase 2: Advanced Analytics**
5. Sync processed orders (includes tax, postage, tracking)
6. Calculate true profit margins with costs
7. Shipping performance by courier
8. Payment gateway analysis

### **Phase 3: Inventory Intelligence**
9. Reorder point calculations
10. Multi-channel pricing strategy
11. Supplier performance tracking
12. Product lifecycle insights

---

## 🚀 QUICK COMMANDS

### Sync Products (Updated with Cost Data)
```bash
php artisan sync:linnworks-products --limit=1000 --force
```

### View Analytics with Cost Tracking
```bash
php artisan analytics:extract-orders --days=30
```

### Check Data Quality
```bash
php artisan tinker
$products = Product::whereNotNull('purchase_price')->where('purchase_price', '>', 0)->count();
$total = Product::count();
echo "Products with costs: $products / $total (" . round(($products/$total)*100, 1) . "%)";
```

---

## 💡 NEXT STEPS TO ENABLE PROFIT CALCULATIONS

1. **In Linnworks**: Add purchase prices to your product catalog
2. **Re-sync products**: `php artisan sync:linnworks-products --limit=5000 --force`
3. **Sync processed orders**: These include full cost breakdowns
4. **View updated analytics**: Profit margins will auto-calculate

---

## 📚 API DOCUMENTATION REFERENCES

- [Open Orders API](https://github.com/LinnSystems/PublicApiSpecs/blob/main/1.0/openorders.json)
- [Processed Orders API](https://github.com/LinnSystems/PublicApiSpecs/blob/main/1.0/processedorders.json)
- [Stock API](https://github.com/LinnSystems/PublicApiSpecs/blob/main/1.0/stock.json)

---

**Generated**: October 2025
**Status**: Products fixed ✅ | Costs awaiting Linnworks data ⚠️
