# WooCommerce Product Custom Object Sync

## Overview
This document explains how WooCommerce products are synced to GoHighLevel Custom Objects when purchased.

## Problem Statement
Initially, Custom Object sync only hooked into `save_post`, which works for regular WordPress posts but **not** for WooCommerce product purchases. When a customer purchases a product, WooCommerce creates an order but doesn't trigger `save_post` on the product itself.

## Solution
We've implemented WooCommerce-specific hooks to capture product purchases and sync them to GHL Custom Objects.

---

## Implementation

### 1. New Hooks in `CustomObjectSync::init()`

```php
// WooCommerce product purchase hooks
add_action( 'woocommerce_order_status_completed', array( $this, 'handle_woocommerce_order_completed' ), 10, 2 );
add_action( 'woocommerce_order_status_processing', array( $this, 'handle_woocommerce_order_processing' ), 10, 2 );
add_action( 'woocommerce_thankyou', array( $this, 'handle_woocommerce_thankyou' ), 10, 1 );
```

### 2. Handler Methods

#### `handle_woocommerce_order_completed( $order_id, $order )`
**Trigger:** `woocommerce_order_status_completed`  
**Purpose:** Sync products when order reaches "Completed" status

**Logic:**
1. Verify WooCommerce is active
2. Get order object
3. Check for active product mapping
4. Verify `product_purchased` trigger is enabled
5. Iterate through order line items
6. Queue sync for each product with order context

**Context Data Passed:**
- `trigger` → `'product_purchased'`
- `order_id` → WooCommerce order ID
- `purchaser_email` → Billing email from order
- `purchaser_name` → Full name from billing
- `quantity` → Product quantity in order
- `total` → Line item total
- `order_total` → Total order amount
- `payment_method` → Payment method used

#### `handle_woocommerce_order_processing( $order_id, $order )`
**Trigger:** `woocommerce_order_status_processing`  
**Purpose:** Sync products when order enters "Processing" status (alternative/earlier trigger)

**Requirements:**
- Mapping must have `order_processing` trigger enabled
- Works same as completed handler but fires earlier in order lifecycle

#### `handle_woocommerce_thankyou( $order_id )`
**Trigger:** `woocommerce_thankyou`  
**Purpose:** Sync products when customer views the thank you page

**Requirements:**
- Mapping must have `thankyou_page` trigger enabled
- Only processes orders with status `completed` or `processing`

---

## Available Triggers (for Product CPT)

When creating a product custom object mapping, you can choose from these triggers:

| Trigger Key | Label | Description | Hook |
|------------|-------|-------------|------|
| `publish` | Post Published | Create object when product is first published | `save_post` |
| `update` | Post Updated | Sync when product details are updated | `save_post` |
| `delete` | Post Deleted | Delete custom object when product is deleted | `before_delete_post` |
| `product_purchased` | **Product Purchased (Order Completed)** | Create object when product is purchased and order completed | `woocommerce_order_status_completed` |
| `order_processing` | Order Processing | Create object when order enters processing status | `woocommerce_order_status_processing` |
| `thankyou_page` | Thank You Page Viewed | Create object when customer views thank you page | `woocommerce_thankyou` |
| `stock_changed` | Stock Level Changed | Sync when stock quantity changes | *(not yet implemented)* |

---

## Queue Payload Structure

When a product purchase is queued, the payload includes:

```php
[
    'mapping_id' => 'mapping_xxx',
    'mapping'    => [...], // Full mapping config
    'context'    => [
        'trigger'          => 'product_purchased',
        'order_id'         => 290,
        'purchaser_email'  => 'customer@example.com',
        'purchaser_name'   => 'John Doe',
        'quantity'         => 2,
        'total'            => 99.98,
        'order_total'      => 109.98,
        'payment_method'   => 'stripe',
    ]
]
```

This context data can be used in future enhancements:
- Create multiple objects (one per purchaser)
- Store order metadata in custom object fields
- Link to order-specific contacts
- Track purchase history

---

## Contact Linking Strategy

### Primary Contact Options for Products:
1. **Post Author** - The user who created the product (usually admin)
2. **Custom Meta Field** - Email stored in product meta
3. **Product Purchasers** - Create one object per purchaser (uses `purchaser_email` from context)

### Recommended Setup:
For product purchases, you likely want to use **Product Purchasers** as the primary contact so that:
- Each customer gets their own custom object for the product
- Tracks individual purchase history
- Links to customer's GHL contact record

---

## Usage Example

### Step 1: Create Product Mapping
1. Go to **GHL CRM → Custom Objects**
2. Click **"Add New Mapping"**
3. Configure:
   - **WordPress Post Type:** `Product`
   - **GHL Custom Object:** Select your product schema
   - **Triggers:** Check `Product Purchased (Order Completed)`
   - **Primary Contact:** `Product Purchasers`
   - **Contact Not Found:** `Create New Contact`

### Step 2: Map Fields
Map product fields to GHL custom object fields:

| WordPress Field | GHL Field | Description |
|----------------|-----------|-------------|
| `ID` | `product_id` | Product ID |
| `post_title` | `product_name` | Product name |
| `meta:_price` | `price` | Product price |
| `taxonomy:product_cat` | `category` | Product category |
| `context.order_id` | `order_id` | WooCommerce order ID *(future)* |
| `context.quantity` | `quantity` | Quantity purchased *(future)* |
| `context.total` | `total_paid` | Amount paid *(future)* |

### Step 3: Test
1. Place a test order with a product
2. Complete the order (mark as "Completed")
3. Check debug log for sync messages
4. Verify custom object created in GHL
5. Confirm contact association

---

## Debug Log Examples

### Successful Sync
```
[2024-01-15 13:05:32] [GHL Custom Objects] Processing completed order #290 for custom objects sync
[2024-01-15 13:05:32] [GHL Custom Objects] Queueing sync for product #72 from order #290 (purchaser: customer@example.com)
[2024-01-15 13:05:32] [GHL Custom Objects] Queued 1 product(s) from order #290 for sync
[2024-01-15 13:05:32] GHL CRM CustomObjectSync: Queued sync_custom_object operation for post 72 (mapping: product_mapping) with context: {"trigger":"product_purchased","order_id":290,"purchaser_email":"customer@example.com",...}
[2024-01-15 13:05:33] GHL CRM CustomObjectSync: execute_custom_object_sync() called - Post ID: 72, Action: sync_custom_object
[2024-01-15 13:05:33] GHL CRM CustomObjectSync: Custom object synced successfully for post 72
```

### No Active Mapping
```
[2024-01-15 13:05:32] [GHL Custom Objects] Processing completed order #290 for custom objects sync
[2024-01-15 13:05:32] [GHL Custom Objects] No active mapping found for product post type
```

### Trigger Not Enabled
```
[2024-01-15 13:05:32] [GHL Custom Objects] Processing completed order #290 for custom objects sync
[2024-01-15 13:05:32] [GHL Custom Objects] product_purchased trigger not enabled in mapping
```

---

## Future Enhancements

### 1. Multi-Object Creation
Currently creates ONE object per product in the order. Future enhancement:
- Create multiple objects if mapping contact strategy is `product_purchasers`
- One object per unique customer who purchased
- Track purchase history across all purchases

### 2. Context Field Mapping
Allow mapping context data directly in field mappings:
```php
'field_mappings' => [
    ['wp_field' => 'context.order_id', 'ghl_field' => 'order_id'],
    ['wp_field' => 'context.quantity', 'ghl_field' => 'qty_purchased'],
    ['wp_field' => 'context.total', 'ghl_field' => 'amount_paid'],
]
```

### 3. Stock Level Sync
Implement `stock_changed` trigger:
```php
add_action( 'woocommerce_product_set_stock', array( $this, 'handle_stock_changed' ) );
```

### 4. Refund Handling
Add triggers for refunds:
```php
add_action( 'woocommerce_order_refunded', array( $this, 'handle_order_refunded' ) );
```

### 5. Subscription Integration
Support WooCommerce Subscriptions:
- `subscription_activated`
- `subscription_renewed`
- `subscription_cancelled`
- `subscription_expired`

---

## Troubleshooting

### Products Not Syncing After Purchase

**Check 1:** Verify WooCommerce is active
```bash
# In debug.log, look for:
[GHL Custom Objects] WooCommerce not available for order completed hook
```

**Check 2:** Confirm mapping exists and is active
```bash
[GHL Custom Objects] No active mapping found for product post type
```

**Check 3:** Verify trigger is enabled
```bash
[GHL Custom Objects] product_purchased trigger not enabled in mapping
```

**Check 4:** Check order status
- Hooks only fire on specific order statuses
- `completed` → fires `woocommerce_order_status_completed`
- `processing` → fires `woocommerce_order_status_processing`

**Check 5:** Review queue logs
```bash
grep "CustomObjectSync" /path/to/debug.log
```

### Empty Order Items

If orders have no line items:
```bash
[GHL Custom Objects] No items found in order #290
```

**Cause:** Virtual orders or corrupted order data  
**Solution:** Check WooCommerce order in admin, verify line items exist

---

## Code Reference

**Files Modified:**
- `src/Sync/CustomObjectSync.php` - Added WooCommerce hooks and handlers
- `src/Sync/CustomObjectFieldDiscovery.php` - Added WooCommerce triggers to available triggers list

**Key Methods:**
- `CustomObjectSync::init()` - Registers WooCommerce hooks
- `CustomObjectSync::handle_woocommerce_order_completed()` - Main purchase handler
- `CustomObjectSync::queue_sync_operation()` - Enhanced to accept context data
- `CustomObjectFieldDiscovery::get_sync_triggers()` - Returns available triggers per CPT

---

## Testing Checklist

- [ ] Create product mapping with `product_purchased` trigger
- [ ] Map product fields to GHL custom object
- [ ] Set primary contact to "Product Purchasers"
- [ ] Place test order with test product
- [ ] Mark order as "Completed"
- [ ] Check debug.log for sync messages
- [ ] Verify custom object created in GHL
- [ ] Verify contact association
- [ ] Test with multiple products in one order
- [ ] Test with same product purchased multiple times
- [ ] Verify queue processing works correctly
- [ ] Test error handling (invalid GHL credentials, network errors)

---

## License
GPL v2 or later
