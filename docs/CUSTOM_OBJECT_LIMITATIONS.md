# Custom Object Association Implementation

## Contact Associations via API (Updated November 2024)

### Current Status ✅
GoHighLevel's Associations API **DOES support programmatic associations** between custom object records and contacts via the `/associations/relations` endpoint.

### What Works ✅
- **CREATE** custom object records: `POST /objects/:schemaId/records`
- **UPDATE** custom object records: `PUT /objects/:schemaKey/records/:id?locationId=xxx`
- **DELETE** custom object records: `DELETE /objects/:schemaKey/records/:id`
- **GET** custom object records: `GET /objects/:schemaId/records/:id`
- **CREATE ASSOCIATION** between records: `POST /associations/relations`
- All custom object properties sync correctly
- **Automatic contact associations** after record creation

### Correct API Endpoint ✅
The correct endpoint for creating associations is:
```http
POST /associations/relations
Content-Type: application/json

{
  "locationId": "V2i5mzwrZLvCj8AR0ePZ",
  "schemaKey": "custom_objects.my_custom_objects",
  "recordId": "69075a1eae440b978bba62b5",
  "associationKey": "tester_association_name",
  "relatedSchemaKey": "contact",
  "relatedRecordId": "sDgl781KCHaqV4GaspI5"
}
```

### What Was Wrong ❌
- **Tried wrong endpoint**: `POST /objects/:schemaKey/records/:recordId/relations` - This doesn't exist
- The correct endpoint is under `/associations/` not `/objects/`

### What We've Tried
1. ❌ Including `contact_id` in record properties → Ignored by API
2. ❌ Including `tester_association_name` in properties → Ignored by API
3. ❌ POST to `/objects/:schemaKey/records/:recordId/relations` → Doesn't exist
4. ✅ POST to `/associations/relations` → **THIS WORKS!**

### Implementation Details
The plugin now:
1. Creates the custom object record via `POST /objects/:schemaId/records`
2. Stores the returned record ID
3. Immediately calls `POST /associations/relations` to link record with contact
4. Uses the association key from the mapping configuration

The `associate_with_contact()` method is implemented in `CustomObjectResource.php` and actively being used.

### Code Location
Association logic is implemented in:
- `src/Sync/CustomObjectSync.php` (lines ~370-400) - Calls association after CREATE
- `src/API/Resources/CustomObjectResource.php` (method: `associate_with_contact()`) - Makes API call

### References
- **Associations API:** https://marketplace.gohighlevel.com/docs/ghl/associations/create-relation
- **Relations Docs:** https://marketplace.gohighlevel.com/docs/ghl/associations/relations
- **Custom Objects API:** https://marketplace.gohighlevel.com/docs/ghl/objects/custom-objects-api/
- **Records API:** https://marketplace.gohighlevel.com/docs/ghl/objects/records

### Configuration Required
For associations to work, the mapping configuration must include:
- `schema_key`: The custom object schema key (e.g., `custom_objects.my_custom_objects`)
- `association_id`: The association definition ID from GHL (e.g., `ve9EPM428h8vShlRW1KT`)

**IMPORTANT:** You need the association ID, not the association name!

### How to Get the Association ID

1. **Via GHL API:**
   ```
   GET https://services.leadconnectorhq.com/associations/?locationId=YOUR_LOCATION_ID
   ```

2. **Via the plugin:**
   The plugin includes a `get_associations()` method in `CustomObjectResource` to fetch all associations.

3. **In the response, look for:**
   - `firstObjectKey`: Should match your custom object schema key (`custom_objects.my_custom_objects`)
   - `secondObjectKey`: Should be `contact`
   - `id`: This is the association ID you need!

4. **Add to mapping configuration:**
   ```php
   $mapping['association_id'] = 've9EPM428h8vShlRW1KT'; // The ID from step 3
   ```

### Impact on Users
- ✅ All custom object data syncs correctly
- ✅ Records are created and updated successfully
- ✅ **Automatic contact associations** work via API
- ⚠️ Each association label supports max 1,000 records
- ⚠️ Associations are many-to-many
- ⚠️ Association key must be configured in mapping settings

### Testing Checklist
1. Create a new WordPress post
2. Check logs for "Successfully associated record with contact"
3. Verify in GHL that the custom object record appears under the contact's relations
4. Check that the `relations` array in API response contains the contact
