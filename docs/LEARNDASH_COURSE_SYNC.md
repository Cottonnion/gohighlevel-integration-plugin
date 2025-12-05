# LearnDash Course Sync - GHL Custom Objects

This document explains how LearnDash courses sync with GoHighLevel Custom Objects.

## Overview

The plugin syncs LearnDash courses to GHL Custom Objects with student enrollment tracking. Similar to WooCommerce products, courses create a single custom object record that can be associated with multiple student contacts.

## Architecture

### Data Model
- **1 Course** = **1 GHL Custom Object Record**
- **Multiple Students** = **Multiple Contact Associations** to that same record
- **Course Instructor** = **Secondary Contact** (optional)

### Storage
- Record ID stored in: `_ghl_custom_object_record_id` post meta
- Last sync time: `_ghl_last_sync_time`
- Sync status: `_ghl_sync_status`

## Triggers

### Available Triggers
1. **`student_enrolled`** - Fires when a student enrolls in a course
   - Creates custom object record if it doesn't exist
   - Associates the enrolled student with the course record
   
2. **`student_completed`** - Fires when a student completes a course
   - Updates the existing course record
   - Can update completion status or progress fields
   
3. **`student_unenrolled`** - Fires when a student is removed from a course
   - Can update record status or remove association

### WordPress Hooks Used
```php
// Enrollment tracking
add_action( 'learndash_update_user_activity', array( $this, 'handle_learndash_enrollment' ), 10, 1 );

// Course completion
add_action( 'learndash_course_completed', array( $this, 'handle_learndash_course_completed' ), 10, 1 );

// Unenrollment
add_action( 'ld_removed_course_access', array( $this, 'handle_learndash_unenrollment' ), 10, 2 );
```

## Contact Sources

### Primary Contact Sources
- **`course_students`** - The enrolled/completing student (creates one record per enrollment)
  - Resolves from context: `student_email`
  - Required context: Student user data passed through enrollment hooks

### Secondary Contact Sources
- **`course_instructor`** - The course author/instructor
  - Resolves from: Post author user meta `_ghl_contact_id`
- **`post_author`** - Generic post author (same as instructor for courses)

## Context Data Flow

### Student Enrollment
```php
$context = [
    'trigger'        => 'student_enrolled',
    'student_id'     => 123,
    'student_email'  => 'student@example.com',
    'student_name'   => 'John Doe',
    'enrolled_date'  => '2025-12-04 10:30:00',
];
```

### Student Completion
```php
$context = [
    'trigger'         => 'student_completed',
    'student_id'      => 123,
    'student_email'   => 'student@example.com',
    'student_name'    => 'John Doe',
    'completed_date'  => '2025-12-04 15:45:00',
];
```

### Student Unenrollment
```php
$context = [
    'trigger'         => 'student_unenrolled',
    'student_id'      => 123,
    'student_email'   => 'student@example.com',
    'student_name'    => 'John Doe',
    'unenrolled_date' => '2025-12-04 18:20:00',
];
```

## Configuration Example

### Mapping Setup
```php
[
    'id' => 'mapping_courses_123',
    'name' => 'Course Enrollments',
    'wp_post_type' => 'sfwd-courses',
    'ghl_object' => 'schema_abc123',
    'ghl_object_key' => 'custom_objects.course_enrollments',
    'active' => true,
    'triggers' => ['student_enrolled', 'student_completed'],
    'associations' => [
        [
            'target_type' => 'contact',
            'source' => 'course_students', // Primary
            'source_field' => '',
            'not_found_action' => 'create', // Auto-create contact if student not in GHL
            'association_key' => ''
        ],
        [
            'target_type' => 'contact',
            'source' => 'course_instructor', // Secondary
            'source_field' => '',
            'not_found_action' => 'skip',
            'association_key' => ''
        ]
    ],
    'field_mappings' => [
        [
            'wp_field' => 'post_title',
            'ghl_field' => 'course_name',
            'transform' => 'none'
        ],
        [
            'wp_field' => 'post_meta:_sfwd-courses',
            'ghl_field' => 'course_settings',
            'transform' => 'json_encode'
        ]
    ]
]
```

## Workflow Example

### Scenario: Student Enrolls in Course

1. **Student John enrolls in "WordPress Fundamentals" course**
   - LearnDash fires: `learndash_update_user_activity`
   - Handler: `handle_learndash_enrollment()`

2. **Check mapping configuration**
   - Mapping exists for `sfwd-courses`
   - Trigger `student_enrolled` is enabled
   - Primary contact source: `course_students`
   - Contact not found action: `create`

3. **Queue sync operation**
   ```php
   queue_sync_operation(
       course_id: 456,
       mapping: [...],
       action: 'sync_custom_object',
       context: [
           'student_email' => 'john@example.com',
           'student_name' => 'John Doe',
           ...
       ]
   );
   ```

4. **Sync execution**
   - Check if course record exists: `get_post_meta(456, '_ghl_custom_object_record_id')`
   - **First enrollment** → No record exists
     - Create new custom object record in GHL
     - Store record ID: `update_post_meta(456, '_ghl_custom_object_record_id', 'record_xyz')`
   - **Subsequent enrollments** → Record exists
     - Use existing record ID
   
5. **Contact resolution**
   - Resolve student contact from `student_email` context
   - Check if WordPress user has `_ghl_contact_id` user meta
   - If not found and action is `create`:
     - Create new contact in GHL
     - Store contact ID in user meta

6. **Create association**
   - Associate student contact with course record
   - If secondary contacts configured (instructor):
     - Resolve instructor contact ID
     - Associate instructor with same course record

7. **Result in GHL**
   - 1 Custom Object Record: "WordPress Fundamentals"
   - Contact 1: John Doe (Student) - Associated
   - Contact 2: Jane Smith (Instructor) - Associated

### Scenario: Second Student Enrolls in Same Course

1. **Student Mary enrolls in "WordPress Fundamentals" course**

2. **Sync execution**
   - Record already exists: `record_xyz`
   - Use existing record (no new record created)

3. **Contact resolution**
   - Resolve Mary's contact from `student_email`
   - Create contact if needed

4. **Create association**
   - Associate Mary with **existing** record `record_xyz`

5. **Result in GHL**
   - Same Custom Object Record: "WordPress Fundamentals"
   - Contact 1: John Doe (Student) - Associated
   - Contact 2: Mary Johnson (Student) - Associated ← NEW
   - Contact 3: Jane Smith (Instructor) - Associated

## Field Mappings

### Available WordPress Fields
- **Core Fields**
  - `post_title` - Course title
  - `post_content` - Course description
  - `post_author` - Instructor ID
  - `post_date` - Course creation date
  
- **LearnDash Meta Fields**
  - `_sfwd-courses` - Course settings (serialized)
  - `_ld_course_price` - Course price
  - `_ld_course_price_type` - Price type (free/paynow/subscribe)
  - Custom course meta fields

### Context Fields (Available During Sync)
Map these to GHL fields to track enrollment data:
- `student_id` - WordPress user ID
- `student_email` - Student email
- `student_name` - Student display name
- `enrolled_date` - Enrollment timestamp
- `completed_date` - Completion timestamp (on completion trigger)

## Contact Creation

When `not_found_action` is set to `create`:

```php
$contact_data = [
    'email' => $context['student_email'],
    'name'  => $context['student_name'],
    'source' => 'LearnDash Course Enrollment',
    'tags' => ['learndash-student', 'course-' . $course_id],
];
```

The contact is created in GHL and the contact ID is stored in WordPress user meta for future lookups.

## Self-Healing

If a custom object record is deleted in GHL but the post meta still references it:

1. Next sync attempt detects missing record
2. Deletes stale `_ghl_custom_object_record_id` meta
3. Creates new record in GHL
4. Updates post meta with new record ID
5. Re-associates all students with new record

## Troubleshooting

### Course not syncing when student enrolls

**Check logs:**
```
[GHL Custom Objects] LearnDash enrollment - User 123 enrolled in course 456
[GHL Custom Objects] No active mapping found for sfwd-courses
```

**Solution:** Create a mapping for post type `sfwd-courses` and enable the `student_enrolled` trigger.

### Student not associated with course

**Check logs:**
```
[GHL Custom Objects] Contact source "course_students" requires email in context for post 456
[GHL Custom Objects] No existing contact found for email: student@example.com
```

**Solution:** 
- Ensure `contact_not_found` action is set to `create`
- Verify student email is valid

### Instructor not being associated

**Check mapping:**
- Secondary contacts should include `course_instructor`
- Instructor must have `_ghl_contact_id` user meta (already synced to GHL)

**Solution:**
- Sync instructor user to GHL first via user sync
- Or set secondary contact `not_found_action` to `create`

## Testing Checklist

- [ ] Create mapping for `sfwd-courses` post type
- [ ] Enable `student_enrolled` trigger
- [ ] Set primary contact source to `course_students`
- [ ] Set contact not found action to `create`
- [ ] Add instructor as secondary contact (optional)
- [ ] Enroll student in course
- [ ] Check logs for enrollment event
- [ ] Verify custom object created in GHL
- [ ] Verify student contact associated
- [ ] Enroll second student in same course
- [ ] Verify both students associated with same record
- [ ] Complete course as student
- [ ] Check if completion updates record (if trigger enabled)

## Hooks Summary

| LearnDash Hook | Plugin Handler | Trigger |
|---|---|---|
| `learndash_update_user_activity` | `handle_learndash_enrollment()` | `student_enrolled` |
| `learndash_course_completed` | `handle_learndash_course_completed()` | `student_completed` |
| `ld_removed_course_access` | `handle_learndash_unenrollment()` | `student_unenrolled` |

## Future Enhancements

- [ ] Support for lesson/topic progress tracking
- [ ] Quiz score syncing
- [ ] Certificate generation tracking
- [ ] Group enrollments (bulk sync)
- [ ] Drip-feed content tracking
- [ ] Course prerequisites completion status
