# WooCommerce Opportunities Integration

## Overview
The Opportunities integration creates and tracks sales opportunities in GoHighLevel pipelines for WooCommerce abandoned carts and orders. This provides visual pipeline tracking of your e-commerce sales process.

## Features

### 1. **Scope Checking**
- Automatically checks if the connected GHL account has `opportunities.readonly` and `opportunities.write` scopes
- Shows clear warning with instructions if scopes are missing
- Real-time API verification (not cached)

### 2. **Pipeline Selection**
- Loads all pipelines from your GHL account via AJAX
- Select2 dropdown with search functionality
- Saved selection persists across page loads

### 3. **Stage Mapping**
Maps WooCommerce events to pipeline stages:
- **🛒 Abandoned Cart** → Initial stage when cart is abandoned
- **⏳ Pending Payment** → Orders awaiting payment confirmation
- **⚙️ Processing** → Orders being prepared/packed
- **✅ Completed (Won)** → Finished orders, marked as WON
- **❌ Cancelled/Failed (Lost)** → Cancelled or refunded orders, marked as LOST

### 4. **Product Filtering**
Control which products trigger opportunity creation:

#### **All Products (Default)**
```
No filters applied - opportunities created for every purchase
💡 Use this for general sales tracking
```

#### **Specific Products Only**
```
Select individual products → Only create opportunities when these products are purchased
Example: High-value courses, coaching packages, premium products
```

#### **Specific Categories Only**
```
Select product categories → Only create opportunities for products in these categories
Example: "Courses", "Memberships", "Digital Products"
```

#### **Orders Above Minimum Value**
```
Set minimum cart/order value → Only track high-value opportunities
Example: Set to $100 to track only orders worth $100+
```

## Implementation Files

### **Created Files**
1. **OpportunityResource.php** (`src/API/Resources/OpportunityResource.php`)
   - Methods: `search()`, `get_pipelines()`, `create()`, `update()`, `update_status()`, `upsert()`
   - Handles all GHL Opportunities API operations

### **Modified Files**
1. **ScopeChecker.php** (`src/Core/ScopeChecker.php`)
   - Added `opportunities` scope definition
   - Added opportunities endpoint for scope testing

2. **woocommerce.php** (`templates/admin/partials/integrations/woocommerce.php`)
   - Added opportunities settings section
   - Pipeline and stage selection UI
   - Product filtering configuration
   - Scope checking UI

3. **integrations.js** (`assets/admin/js/integrations.js`)
   - Added `initOpportunitiesSelects()` method
   - Pipeline Select2 with AJAX loading (caches pipeline data with stages)
   - Stage selects initialization (uses cached stages from pipeline response)
   - Products Select2 with AJAX search
   - Categories Select2
   - Filter type toggle handling
   - `pipelinesData` object stores pipelines with nested stages

4. **AjaxHandler.php** (`src/Core/AjaxHandler.php`)
   - `get_pipelines()` - Fetch pipelines with stages from GHL (stages are nested in response)
   - `search_products()` - AJAX product search for Select2
   - Updated `save_integrations()` to handle opportunities settings

5. **SettingsManager.php** (`src/Core/SettingsManager.php`)
   - Added AJAX action hooks: `ghl_get_pipelines`, `ghl_search_products`
   - Added handler methods: `handle_get_pipelines()`, `handle_search_products()`
   - Note: `ghl_get_pipeline_stages` endpoint removed - stages come with pipelines response

## Settings Structure

```php
[
    // Enable/disable
    'wc_opportunities_enabled' => true/false,
    
    // Pipeline selection
    'wc_opportunities_pipeline' => 'pipeline_id_here',
    
    // Stage mapping
    'wc_opportunities_stage_abandoned' => 'stage_id',
    'wc_opportunities_stage_pending' => 'stage_id',
    'wc_opportunities_stage_processing' => 'stage_id',
    'wc_opportunities_stage_completed' => 'stage_id',
    'wc_opportunities_stage_cancelled' => 'stage_id',
    
    // Product filtering
    'wc_opportunities_filter_type' => 'all|products|categories|min_value',
    'wc_opportunities_products' => [123, 456],        // Product IDs
    'wc_opportunities_categories' => [1, 2, 3],       // Category IDs
    'wc_opportunities_min_value' => 100.00,           // Minimum order value
]
```

## User Experience Flow

### 1. **Admin Setup**
```
1. Navigate to: GHL CRM → Integrations → WooCommerce
2. Scroll to "Opportunities (Sales Pipeline)" section
3. If scopes missing → Follow instructions to add scopes in GHL
4. If scopes OK → Enable "Create Opportunities in Sales Pipeline"
5. Select pipeline from dropdown
6. Map stages to WooCommerce events
7. Configure product filtering (optional)
8. Save settings
```

### 2. **Automatic Operation**
```
When cart is abandoned:
→ Create opportunity in "Abandoned Cart" stage
→ Apply tags (if configured)
→ Set opportunity value to cart total
→ Link to contact in GHL

When order is placed:
→ Move opportunity to "Pending" stage
→ Update opportunity value to order total

When order status changes:
→ Move opportunity to corresponding stage
→ Mark as WON (completed) or LOST (cancelled)
```

## Next Steps: Implementation in AbandonedCartTracker

The next phase will integrate OpportunityResource into the AbandonedCartTracker.php to:

1. **On Cart Abandonment** (`mark_cart_abandoned()`)
   - Check if opportunities enabled + filter matches
   - Create opportunity in abandoned stage
   - Store opportunity ID in user meta

2. **On Order Placement** (new hook: `woocommerce_new_order`)
   - Update opportunity to pending/processing stage
   - Update monetary value

3. **On Status Change** (new hook: `woocommerce_order_status_changed`)
   - Map WC status to pipeline stage
   - Update opportunity stage
   - Mark as won/lost when appropriate

## Use Cases

### **Abandoned Cart Recovery Pipeline**
```
Pipeline: "Cart Recovery"
Stages:
  1. Abandoned → Cart left for 60+ minutes
  2. Email Sent → Recovery email triggered
  3. Revisited → Customer returned via email link
  4. Won → Purchase completed
  5. Lost → Cart expired after 7 days
```

### **High-Value Orders Pipeline**
```
Filter: Orders above $500
Pipeline: "Premium Customers"
Stages:
  1. Order Received → High-value order placed
  2. Processing → Being prepared
  3. Shipped → On the way
  4. Delivered → Completed
  5. Won → Customer leaves review/repeats
```

### **Course Sales Pipeline**
```
Filter: Category = "Courses"
Pipeline: "Course Sales"
Stages:
  1. Enrolled → Course purchased
  2. Onboarded → LearnDash access granted
  3. Active → First lesson completed
  4. Completed → Course finished
  5. Won → Ready for upsell to next level
```

## API Endpoints Used

- **Get Pipelines**: `GET /opportunities/pipelines?locationId={id}`
- **Get Pipeline Details**: `GET /opportunities/pipelines/{pipeline_id}`
- **Search Opportunities**: `GET /opportunities/search?location_id={id}`
- **Create Opportunity**: `POST /opportunities`
- **Update Opportunity**: `PUT /opportunities/{opportunity_id}`

## Testing Checklist

- [ ] Scope checker displays correctly (missing vs. has scopes)
- [ ] Pipeline dropdown loads pipelines from GHL
- [ ] Stage dropdowns populate when pipeline selected
- [ ] Product search works (AJAX Select2)
- [ ] Category selection works
- [ ] Filter type toggle shows/hides correct sections
- [ ] Settings save correctly
- [ ] All tooltips display helpful information
- [ ] Mobile responsive UI
- [ ] Error handling for API failures
