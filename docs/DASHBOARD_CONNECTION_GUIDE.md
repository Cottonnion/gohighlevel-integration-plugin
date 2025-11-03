# Dashboard Connection Setup - User Guide

## Overview
When users visit the Dashboard and are not connected, they see two connection methods with clear instructions and required scopes.

---

## Tab 1: API Key (Recommended) ⭐

### Visual Layout
```
┌─────────────────────────────────────────────────────────────┐
│  📱 API Key (Recommended)    ☁ OAuth Connection              │
└─────────────────────────────────────────────────────────────┘

Connect Using API Key
This is the recommended method. Use a GoHighLevel API key to connect 
your location. This method is more reliable and doesn't require OAuth 
app configuration.

┌─────────────────────────────────────────────────────────────┐
│ ℹ️  How to Create a Private Integration:                     │
│                                                              │
│  1. Log into your GoHighLevel sub-account                   │
│  2. Go to Settings → Integrations → Private Integrations    │
│  3. Click "Create" to create a new integration              │
│  4. Give it a name (e.g., "WordPress Plugin")               │
│  5. Select the required scopes listed below                 │
│  6. Click "Create" and copy the generated API Key           │
│  7. Get Location ID from Settings → Business Profile →       │
│     Location ID                                             │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ ⚠️  Required Scopes                                          │
│                                                              │
│ These scopes are necessary for the plugin to function       │
│ properly. Make sure to select all of them when creating     │
│ your private integration.                                   │
│                                                              │
│ [View Contacts] [Edit Contacts] [View Tags] [Edit Tags]    │
│ [View Locations] [Edit Location Tasks] [View Custom Fields] │
│ [Edit Custom Fields] [View Objects Schema]                  │
│ [Edit Objects Schema] [View Objects Record]                 │
│ [Edit Objects Record] [View Associations]                   │
│ [Write Associations] [View Associations Relation]           │
│ [Write Associations Relation] [View Forms]                  │
│                                                              │
│ (17 amber/yellow badges with darker text)                   │
└─────────────────────────────────────────────────────────────┘

API Key *
[_____________________________________________]
Your location API key from GoHighLevel Settings

Location ID *
[_____________________________________________]
Found in Settings → Business Profile

[✓ Connect Now]
```

### Color Scheme (API Key Tab)
- **Info Box**: Blue (`#e7f3ff` background, `#2271b1` border)
- **Scopes Box**: Amber/Yellow warning style (`#fff4e6` background, `#ffb84d` border)
- **Scope Badges**: Yellow (`#fef3c7` background, `#fbbf24` border, `#78350f` text)
- **Warning Icon**: Orange (`#f0a020`)

---

## Tab 2: OAuth Connection

### Visual Layout
```
┌─────────────────────────────────────────────────────────────┐
│  📱 API Key (Recommended)    ☁ OAuth Connection              │
└─────────────────────────────────────────────────────────────┘

Connect Using OAuth
Use our OAuth app to connect multiple locations easily. This method 
is ideal for agencies managing multiple sub-accounts.

┌─────────────────────────────────────────────────────────────┐
│ 💡 About OAuth Connection:                                   │
│                                                              │
│  • One-click connection to GoHighLevel                      │
│  • Automatic token refresh (stays connected)                │
│  • Works across multiple locations                          │
│  • More secure than manual API keys                         │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ ℹ️  Required Scopes                                          │
│                                                              │
│ These scopes are necessary for the plugin to function       │
│ properly. When you click "Connect with GoHighLevel" below,   │
│ you'll be asked to authorize these permissions for our app. │
│                                                              │
│ [View Contacts] [Edit Contacts] [View Tags] [Edit Tags]    │
│ [View Locations] [Edit Location Tasks] [View Custom Fields] │
│ [Edit Custom Fields] [View Objects Schema]                  │
│ [Edit Objects Schema] [View Objects Record]                 │
│ [Edit Objects Record] [View Associations]                   │
│ [Write Associations] [View Associations Relation]           │
│ [Write Associations Relation] [View Forms]                  │
│                                                              │
│ (17 gray badges with neutral styling)                       │
└─────────────────────────────────────────────────────────────┘

           [☁ Connect with GoHighLevel]
           
You will be redirected to GoHighLevel to authorize this 
integration. After authorization, you'll be redirected back here.
```

### Color Scheme (OAuth Tab)
- **Info Box**: Yellow (`#fff3cd` background, `#ffc107` border)
- **Scopes Box**: Light gray (`#f9f9f9` background, `#e0e0e6` border)
- **Scope Badges**: Light gray (`#fafafc` background, `#e0e0e6` border, `#344054` text)
- **Info Icon**: Blue (`#2271b1`)

---

## Key Messaging

### API Key Tab Emphasis
✅ **"Required Scopes"** (not optional)  
✅ **"These scopes are necessary for the plugin to function properly"**  
✅ **"Make sure to select all of them when creating your private integration"**  
✅ Amber/yellow warning styling to draw attention  

### OAuth Tab Emphasis
✅ **"Required Scopes"** (not optional)  
✅ **"These scopes are necessary for the plugin to function properly"**  
✅ **"You'll be asked to authorize these permissions"**  
✅ Professional gray styling matching GHL interface  

---

## All 17 Required Scopes

Both tabs display the same complete list:

1. **View Contacts** - Read contact data
2. **Edit Contacts** - Create/update contacts
3. **View Tags** - Read contact tags
4. **Edit Tags** - Add/remove tags
5. **View Locations** - Read location settings
6. **Edit Location Tasks** - Manage tasks
7. **View Custom Fields** - Read custom field definitions
8. **Edit Custom Fields** - Create/update custom fields
9. **View Objects Schema** - Read custom object schemas
10. **Edit Objects Schema** - Modify custom object schemas
11. **View Objects Record** - Read custom object records
12. **Edit Objects Record** - Create/update records
13. **View Associations** - Read object associations
14. **Write Associations** - Create object associations
15. **View Associations Relation** - Read association relationships
16. **Write Associations Relation** - Create association relationships
17. **View Forms** - Read GHL forms

---

## Setup Instructions Comparison

### Manual API Key Method (7 Steps)
1. Log into your GoHighLevel sub-account
2. Go to **Settings → Integrations → Private Integrations**
3. Click "Create" to create a new integration
4. Give it a name (e.g., "WordPress Plugin")
5. **Select the required scopes listed below** ⚠️
6. Click "Create" and copy the generated API Key
7. Get Location ID from **Settings → Business Profile → Location ID**

### OAuth Method (1 Step)
1. Click "Connect with GoHighLevel" button
   - User is redirected to GHL
   - Asked to authorize the 17 scopes
   - Redirected back to WordPress

---

## Design Principles

1. **Clarity**: Clear instructions that anyone can follow
2. **Emphasis**: Strong messaging that scopes are required, not optional
3. **Visual Hierarchy**: Important warnings use amber/yellow styling
4. **Completeness**: All 17 scopes shown upfront (no surprises)
5. **Guidance**: Step-by-step instructions with exact menu paths
6. **Consistency**: Same scope list in both tabs
7. **Professional**: Clean, modern design matching GHL UI

---

## User Flow

```
User arrives at Dashboard
         ↓
Not Connected? → Show two-tab interface
         ↓
User chooses method:
         ↓
    ┌────┴────┐
    ↓         ↓
API Key    OAuth
    ↓         ↓
Sees scopes  Sees scopes
(amber)      (gray)
    ↓         ↓
Follows 7    Clicks button
steps        & authorizes
    ↓         ↓
    └────┬────┘
         ↓
   Connected! ✓
```

---

## Benefits of This Approach

✅ **Transparency**: Users know exactly what permissions are needed before connecting  
✅ **Education**: Clear explanation of why each scope is necessary  
✅ **Flexibility**: Two connection methods to suit different use cases  
✅ **Compliance**: Aligns with WordPress.org and data privacy best practices  
✅ **Reduced Support**: Comprehensive instructions reduce "how to connect" questions  
✅ **Professional**: Enterprise-grade UI that builds trust  

---

## Implementation Notes

- Scopes are defined once in `ScopeChecker::$feature_scopes`
- Dashboard template loops through scope list to generate badges
- Both tabs use same scope data source (DRY principle)
- Scope badges are CSS-styled (no external dependencies)
- Responsive design works on mobile devices
- Dashicons used for icons (WordPress native)
