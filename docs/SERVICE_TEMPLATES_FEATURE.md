# Service Templates Feature - Implementation Summary

## Overview
The Service Templates feature allows church administrators to save services as reusable templates, streamlining the process of creating similar services week after week. This feature includes template management, quick service creation from templates, and the ability to duplicate the previous week's service.

## Features Implemented

### 1. Database Schema
**Migration File:** `/migrations/2026_03_24_002_service_templates.sql`

#### Tables Created:
- **service_templates** - Stores template configurations
  - Template name, description, service type
  - Created by user, active status

- **service_template_items** - Stores default order of service for templates
  - Links to songs, item types, durations, positions

- **service_template_roles** - Stores default team roles needed
  - Role assignments with quantities per role

- **service_roles** - Team role definitions (created if not exists)
- **service_rota** - Service-specific role assignments (created if not exists)
- **member_role_capabilities** - Member role skills (created if not exists)
- **member_availability** - Member blockout dates (created if not exists)

### 2. Templates Management Page
**File:** `/adminnew/services/templates.php`

**Features:**
- Grid view of all active templates
- Template cards showing:
  - Service type badge
  - Template name and description
  - Item count and role count
- Actions per template:
  - View details (opens modal)
  - Use template (redirects to schedule page)
  - Delete template (soft delete)
- Empty state with guidance when no templates exist
- View modal displays:
  - Complete order of service from template
  - All team roles and quantities needed

### 3. API Endpoints
**File:** `/adminnew/services/api/templates.php`

**Actions Supported:**
- `save` - Save a service as a template
  - Copies service items (songs, announcements, etc.)
  - Copies rota roles with quantities
  - Associates with service type

- `get` - Retrieve template details
  - Returns template info, items, and roles
  - Formats data for display

- `delete` - Soft delete a template
  - Sets is_active = 0

- `apply` - Apply template to a service
  - Copies all items and roles to new service
  - Maintains order and positions

- `duplicate-last` - Duplicate last week's service
  - Finds most recent service of same type
  - Copies items and rota structure
  - Creates new service at specified date/time

### 4. Save as Template (Plan Page)
**File:** `/adminnew/services/plan.php`

**Changes:**
- Added "Save as Template" button to page header
- Modal for template creation:
  - Template name (required)
  - Description (optional)
  - Confirmation and success feedback
- JavaScript functions:
  - `showSaveTemplateModal()`
  - `hideSaveTemplateModal()`
  - `saveAsTemplate()` - Calls API to save current service

### 5. Create from Template (Schedule Page)
**File:** `/adminnew/services/schedule.php`

**Changes:**
- Template selection sidebar:
  - Radio buttons for each available template
  - Shows template name, service type, and item count
  - Hidden input to pass selected template ID

- Form submission enhancement:
  - Checks for `apply_template` parameter
  - Applies template items and roles after service creation

- "Duplicate Last Week" button:
  - Quick action in sidebar
  - Calls API to duplicate most recent service
  - Redirects to new service plan page

### 6. Navigation Updates
**File:** `/adminnew/services/services.php`

**Changes:**
- Added "Service Templates" quick link to main services dashboard
- Icon and link to `/adminnew/services/templates`

## User Workflow

### Creating a Template from Existing Service
1. Plan a service completely (add items, assign roles)
2. Click "Save as Template" button
3. Enter template name and optional description
4. Template is saved and can be reused

### Using a Template to Create New Service
1. Go to "Schedule Service" page
2. Select service type, date, and time
3. Choose a template from the sidebar (optional)
4. Click "Schedule Service"
5. Service is created with all template items and roles pre-populated

### Duplicating Last Week's Service
1. Go to "Schedule Service" page
2. Select service type, date, and time
3. Click "Duplicate Last Week's Service"
4. System finds most recent service of that type
5. Creates new service with copied items and roles
6. Redirects to plan page for the new service

### Managing Templates
1. Go to "Service Templates" page from dashboard
2. View all templates in grid layout
3. Click "View" to see template details
4. Click "Use Template" to create service from it
5. Click delete icon to remove template

## Technical Details

### Template Data Structure
Templates capture:
- **Service Type** - Which type of service (Sunday AM, PM, etc.)
- **Items** - Order of service with:
  - Item type (song, scripture, prayer, etc.)
  - Song references (if applicable)
  - Titles, durations, notes
  - Position/order
- **Roles** - Team positions needed with:
  - Role definitions
  - Quantity needed per role
  - Position/order

### What Templates Don't Include
- Specific member assignments (roles are unassigned)
- Service date/time (specified when creating new service)
- Service-specific notes or descriptions
- Attendance counts or service status

### Database Relationships
```
service_templates
├── service_template_items (1:many)
│   └── songs (many:1, optional)
└── service_template_roles (1:many)
    └── service_roles (many:1)
        └── service_teams (many:1)
```

## Files Created/Modified

### New Files:
1. `/migrations/2026_03_24_002_service_templates.sql`
2. `/adminnew/services/templates.php`
3. `/adminnew/services/api/templates.php`
4. `/docs/SERVICE_TEMPLATES_FEATURE.md` (this file)

### Modified Files:
1. `/adminnew/services/plan.php`
   - Added "Save as Template" button and modal
   - Added JavaScript for template save functionality

2. `/adminnew/services/schedule.php`
   - Added template selection UI
   - Added "Duplicate Last Week" functionality
   - Enhanced form submission to apply templates

3. `/adminnew/services/services.php`
   - Added "Service Templates" quick link

## Testing Checklist

- [ ] Run migration successfully
- [ ] Create a service with items and roles
- [ ] Save service as template
- [ ] View template in templates list
- [ ] View template details in modal
- [ ] Create new service from template
- [ ] Verify all items copied correctly
- [ ] Verify all roles copied correctly (unassigned)
- [ ] Duplicate last week's service
- [ ] Delete a template
- [ ] Create service without template (normal flow)

## Future Enhancements (Optional)

1. **Template Categories/Tags** - Organize templates by season, occasion, etc.
2. **Default Templates** - Mark a template as default for each service type
3. **Template Sharing** - Export/import templates between churches
4. **Template Versioning** - Track changes to templates over time
5. **Smart Templates** - AI-suggested templates based on church patterns
6. **Recurring Services** - Auto-create services from templates on schedule

## API Response Examples

### Save Template Response:
```json
{
  "success": true,
  "message": "Template saved successfully!",
  "template_id": 5
}
```

### Get Template Response:
```json
{
  "success": true,
  "template": {
    "id": 5,
    "name": "Standard Sunday Morning",
    "description": "Regular Sunday AM service format",
    "service_type_id": 1,
    "type_name": "Sunday Morning",
    "type_color": "#3B82F6"
  },
  "items": [
    {
      "id": 12,
      "item_type": "song",
      "title": "Amazing Grace",
      "duration_minutes": 5,
      "position": 0
    }
  ],
  "roles": [
    {
      "id": 8,
      "role_name": "Worship Leader",
      "team_name": "Worship Team",
      "quantity": 1
    }
  ]
}
```

### Duplicate Last Week Response:
```json
{
  "success": true,
  "message": "Service duplicated successfully!",
  "service_id": 45,
  "redirect_url": "/adminnew/services/plan/45"
}
```

## Notes for Developers

- Templates use soft deletes (`is_active` flag)
- All database operations use PDO prepared statements
- JavaScript uses modern fetch API with async/await
- CSS uses CSS variables for theming consistency
- Modal dialogs follow existing admin UI patterns
- Toast notifications provide user feedback
- Form validation on both client and server side

## Support

For questions or issues with the Service Templates feature:
1. Check this documentation first
2. Review the migration file for schema details
3. Check API endpoint responses for debugging
4. Verify database tables were created correctly
5. Check browser console for JavaScript errors
