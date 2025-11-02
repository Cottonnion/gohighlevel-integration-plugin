# Custom Object Associations & Update Fix

## Issues Identified

### 1. UPDATE Operation Failure
**Problem:** `PUT /objects/:schemaKey/records/:id` was failing with "Custom Object not found" error.

**Root Cause:** GHL API requires different identifiers for CREATE vs UPDATE:
- **CREATE**: Uses Schema ID (hex string like `6906127a53f12b340975e826`)
- **UPDATE**: Uses Schema KEY (dot-notation like `custom_objects.my_custom_objects`)

**Solution:** Modified `CustomObjectSync::sync_post()` to use the correct identifier based on operation type.

### 2. Records Not Associated with Contacts
**Problem:** Records were created successfully but `relations[]` array was empty - no link to contacts.

**Root Cause:** Contact associations in GHL Custom Objects require a **separate API call** to the relations endpoint.

**Endpoint:** `POST /objects/:schemaKey/records/:recordId/relations`

**Payload:**
```json
{
  "relatedObject": "contacts",
  "relatedObjectId": "contact_id_here"
}
```

**Solution:** Added `CustomObjectResource::associate_with_contact()` method and integrated it into the sync flow.

---

## Changes Made

### 1. CustomObjectResource.php

#### Added Method: `associate_with_contact()`

```php
/**
 * Associate a custom object record with a contact
 *
 * @param string $record_id   Custom object record ID
 * @param string $contact_id  Contact ID to associate with
 * @param string $schema_key  Schema key (e.g., 'custom_objects.my_custom_objects')
 * @return array Response data
 * @throws \Exception If association fails
 */
public function associate_with_contact( string $record_id, string $contact_id, string $schema_key ): array
```

**Purpose:** Links a custom object record to a contact using GHL's relations API.

**Called After:** Successful record creation (not needed for updates as relation persists).

---

### 2. CustomObjectSync.php

#### Modified: `sync_post()`

**Key Changes:**

1. **Schema Identifier Logic:**
```php
$schema_id  = $mapping['ghl_object'] ?? '';       // Hex ID
$schema_key = $mapping['ghl_object_key'] ?? '';   // Dot-notation key

// Use KEY for UPDATE, ID for CREATE
$schema_identifier = $is_update && ! empty( $schema_key ) ? $schema_key : $schema_id;
```

2. **Association After Create:**
```php
if ( $ghl_record_id && $contact_id && ! empty( $schema_key ) ) {
    try {
        $this->custom_object_resource->associate_with_contact(
            $ghl_record_id,
            $contact_id,
            $schema_key
        );
    } catch ( \Exception $assoc_error ) {
        // Log but don't fail the whole sync
        error_log( 'Association WARNING: ' . $assoc_error->getMessage() );
    }
}
```

**Error Handling:** Association errors are logged but don't fail the entire sync operation (graceful degradation).

---

## API Endpoints Reference

### Create Record
```
POST /objects/:schemaId/records
Body: { locationId, properties }
```

### Update Record
```
PUT /objects/:schemaKey/records/:recordId
Body: { properties }
```

### Associate with Contact
```
POST /objects/:schemaKey/records/:recordId/relations
Body: { relatedObject: "contacts", relatedObjectId: "contact_id" }
```

---

## Testing Checklist

- [ ] Create new post → Record created in GHL
- [ ] Verify record appears in contact's relations
- [ ] Update existing post → Record updated in GHL
- [ ] Check UPDATE uses schema key, not ID
- [ ] Verify association only runs once (on create)
- [ ] Test with missing contact ID (should skip or log)
- [ ] Check logs for association errors
- [ ] Verify graceful degradation if association fails

---

## Expected Log Flow

### Successful Create + Association
```
GHL CRM CustomObjectSync: Creating new record...
GHL CRM CustomObjectResource: Making API POST request to objects/6906127a53f12b340975e826/records
GHL CRM CustomObjectSync: Stored record ID: 690751ae0fb7123e658132a4
GHL CRM CustomObjectSync: Attempting to associate record 690751ae0fb7123e658132a4 with contact sDgl781KCHaqV4GaspI5
GHL CRM CustomObjectResource: Association endpoint: objects/custom_objects.my_custom_objects/records/690751ae0fb7123e658132a4/relations
GHL CRM CustomObjectSync: Successfully associated record with contact
```

### Successful Update
```
GHL CRM CustomObjectSync: Updating existing record...
GHL CRM CustomObjectSync: Using schema identifier: custom_objects.my_custom_objects (type: KEY for UPDATE)
GHL CRM CustomObjectResource: Making API PUT request to objects/custom_objects.my_custom_objects/records/690751ae0fb7123e658132a4
GHL CRM CustomObjectResource: Update response: {"record":{...}}
```

---

## Notes

1. **Schema Key Storage:** The mapping configuration must include both `ghl_object` (ID) and `ghl_object_key` (key) for proper operation.

2. **Association Timing:** Only called after CREATE, not UPDATE (relations persist after initial creation).

3. **Error Resilience:** If association fails, the record still exists in GHL - just without the contact link. Admin can manually link in GHL UI if needed.

4. **Future Enhancement:** Could add a "re-sync associations" bulk action to fix records created before this fix.

---

## Documentation References

- [Create Record API](https://marketplace.gohighlevel.com/docs/ghl/objects/create-object-record)
- [Update Record API](https://marketplace.gohighlevel.com/docs/ghl/objects/update-object-record)
- Relations API: Not documented in public marketplace (discovered via testing)

---

## Commit Message

```
Fix: Custom object UPDATE failures and contact associations

- Use schema KEY for UPDATE, ID for CREATE operations
- Add associate_with_contact() method to link records with contacts
- Implement relations API call after record creation
- Add graceful error handling for association failures
- Improve logging for schema identifier selection

Fixes #[issue-number]
```
