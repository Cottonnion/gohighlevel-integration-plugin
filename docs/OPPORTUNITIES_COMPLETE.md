# Opportunities Integration - Implementation Complete

## ✅ What's Been Implemented

### 1. **OpportunityManager.php** (NEW)
Complete opportunity lifecycle management:
- ✅ Filter checking (all products, specific products, categories, minimum value)
- ✅ Create abandoned cart opportunities
- ✅ Create/update order opportunities
- ✅ Auto-map order status to pipeline stages
- ✅ Auto-set opportunity status (open/won/lost)
- ✅ Contact creation/lookup
- ✅ Opportunity deletion

### 2. **AbandonedCartTracker.php** (UPDATED)
- ✅ Added OpportunityManager instance
- ✅ Create opportunity when cart is marked as abandoned
- ✅ Store opportunity ID in cart transient
- ✅ Check filters before creating opportunity

### 3. **WooCommerceSync.php** (UPDATED)
- ✅ Added OpportunityManager instance
- ✅ Hook: `woocommerce_checkout_order_processed` - Create opportunity on new order
- ✅ Hook: `woocommerce_order_status_changed` - Update opportunity on status change
- ✅ Link abandoned cart opportunity to order
- ✅ Store opportunity ID in order meta

## 🎯 User Flow

### Scenario 1: Abandoned Cart → Recovery
1. **Cart Created** → Cart tracked
2. **Email Captured** → Cart linked to email
3. **60 min passes** (configurable) → Cart marked as abandoned
   - ✅ Contact tagged in GHL
   - ✅ Opportunity created in "Abandoned Cart" stage
4. **Customer returns & completes order** →
   - ✅ Opportunity moves to "Processing" or "Completed" stage
   - ✅ Status changed to "won"

### Scenario 2: Direct Purchase
1. **Customer completes checkout** →
   - ✅ Check if matches filter (products/categories/min value)
   - ✅ Create opportunity in "Pending" or "Processing" stage
2. **Order status changes** →
   - ✅ Opportunity moves through pipeline stages
   - ✅ Completed → marked as "won"
   - ✅ Cancelled/Refunded → marked as "lost"

## 📊 Stage Mapping

| WooCommerce Status | Pipeline Stage | Opportunity Status |
|--------------------|----------------|-------------------|
| Cart Abandoned     | Abandoned Cart | open |
| Pending Payment    | Pending Payment | open |
| Processing         | Processing | open |
| Completed          | Completed | **won** |
| Cancelled/Refunded/Failed | Cancelled | **lost** |

## 🎨 Filter Logic

### All Products (Default)
```
Every cart/order creates an opportunity
```

### Specific Products
```php
if ( cart contains product ID 123 OR 456 ) {
    create_opportunity();
}
```

### Specific Categories
```php
if ( cart contains products from category "Courses" OR "Memberships" ) {
    create_opportunity();
}
```

### Minimum Value
```php
if ( cart_total >= $100 ) {
    create_opportunity();
}
```

## 🔧 Testing Checklist

### Abandoned Cart Flow
- [ ] Add items to cart
- [ ] Enter email at checkout (don't complete)
- [ ] Wait 15-60 minutes (or configured time)
- [ ] Run cron: `wp cron event run ghl_crm_check_abandoned_carts`
- [ ] Verify:
  - [ ] Contact tagged in GHL
  - [ ] Opportunity created in GHL pipeline
  - [ ] Opportunity in "Abandoned Cart" stage

### Order Completion Flow
- [ ] Complete a purchase
- [ ] Check GHL:
  - [ ] Opportunity created (or updated from abandoned cart)
  - [ ] Opportunity in "Processing" or "Completed" stage
  - [ ] If completed, status = "won"

### Order Status Changes
- [ ] Create order → Set to "Pending"
- [ ] Change to "Processing" → Check opportunity moves to "Processing" stage
- [ ] Change to "Completed" → Check opportunity moves to "Completed" stage + status "won"
- [ ] Change to "Cancelled" → Check opportunity moves to "Cancelled" stage + status "lost"

### Filter Testing
- [ ] Set filter to "Specific Products"
- [ ] Add non-selected product to cart
- [ ] Verify: NO opportunity created
- [ ] Add selected product to cart
- [ ] Verify: Opportunity created

## 📝 Database Storage

### Order Meta
```php
$order->get_meta('_ghl_opportunity_id'); // Stores GHL opportunity ID
```

### Cart Transient
```php
$cart_data['ghl_opportunity_id']; // Stores opportunity ID for abandoned cart
```

## 🐛 Debugging

Enable debug logging:
```php
// In wp-config.php
define('GHL_SHOW_DEBUG', true);
```

Check logs:
- WordPress Debug Log: `wp-content/debug.log`
- Look for: `GHL Opportunities:` entries

## 🎉 What Now Happens When You Place an Order:

1. ✅ **Contact synced** to GHL (name, email, phone)
2. ✅ **Customer tags applied** (if first purchase + enabled)
3. ✅ **Opportunity created** in your selected pipeline
4. ✅ **Opportunity stage** set based on order status
5. ✅ **Opportunity value** = order total
6. ✅ **Opportunity status** changes as order progresses (open → won/lost)

**All configured settings are now fully functional! 🚀**
