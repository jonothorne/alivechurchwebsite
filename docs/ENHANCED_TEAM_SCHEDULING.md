# Enhanced Team Scheduling Features

## Overview

The enhanced team scheduling system provides comprehensive tools for managing service teams, roles, member availability, and assignment confirmations. This system is designed to streamline the process of scheduling team members for church services while reducing conflicts and improving communication.

## Features Implemented

### 1. Position/Role Management

Define specific roles within each team (e.g., Worship Leader, Drums, Bass, Keys, Vocals 1-3, Sound, Projection).

**Database Tables:**
- `service_roles` - Defines positions available within each team
- `member_role_capabilities` - Tracks which members can perform which roles

**Key Features:**
- Assign multiple roles to team members
- Track skill levels (beginner, competent, proficient, expert)
- Track preference levels (unwilling, willing, prefer, strong prefer)
- Sort order for role display

**Usage:**
1. Navigate to Services > Teams
2. Select a team
3. Click "Add Role" to create new positions
4. Edit member profiles to assign role capabilities

### 2. Member Availability/Blackout Dates

Team members can mark dates when they're unavailable to serve.

**Database Tables:**
- `member_availability` - Stores unavailable dates for each member

**Key Features:**
- Add single dates or date ranges
- Optional reason field
- Recurring annual dates (e.g., annual vacation)
- Calendar view of unavailable dates
- Conflict detection when assigning members

**Usage:**
1. Navigate to Services > My Availability
2. Click "Add Date(s)"
3. Select single date or date range
4. Add optional reason
5. Save

**API Endpoints:**
- `POST /adminnew/services/api/availability.php?action=add` - Add single date
- `POST /adminnew/services/api/availability.php?action=add-range` - Add date range
- `POST /adminnew/services/api/availability.php?action=remove` - Remove availability
- `GET /adminnew/services/api/availability.php?action=list` - List member availability
- `GET /adminnew/services/api/availability.php?action=check` - Check specific date

### 3. Assignment UI (Drag-Drop/Select Interface)

Enhanced interface for assigning team members to service positions.

**Database Tables:**
- `service_rota` - Specific role assignments for each service

**Key Features:**
- View all roles needed for a service
- See which positions are filled/unfilled
- Assign members with conflict detection
- View member suggestions based on:
  - Role capability
  - Availability
  - Time since last served
  - Current service load
- Visual status indicators (unassigned, pending, confirmed, declined)

**Usage:**
1. Navigate to Services > Plan Service
2. In the Rota sidebar, click "Add Role" to add positions
3. Click "Assign" next to any role
4. Select from suggested members (sorted by availability and rotation)
5. System automatically detects conflicts

### 4. Confirmation Workflow

Team members receive email notifications and can accept/decline assignments via email links.

**Database Tables:**
- `service_assignment_notifications` - Log of sent emails
- `service_rota` (uses `confirmation_token` field)

**Key Features:**
- Automated email notifications with unique confirmation links
- One-click confirm/decline from email
- Decline reason capture
- Notification tracking (sent, opened, responded)
- Reminder emails for pending assignments

**Email Template:**
- HTML and plain text versions
- Service details (date, time, location, role)
- List of other team members
- One-click confirm/decline buttons
- Branded design

**Confirmation Page:**
- Token-based access (no login required)
- Mobile-friendly design
- Shows service details
- Shows other serving members
- Optional decline reason
- Immediate status feedback

**Usage:**
1. After assigning a member, notification is logged
2. Call `/adminnew/services/api/send-assignment-email.php` to send email
3. Member receives email with confirmation link
4. Member clicks "I Can Serve" or "I Can't Make It"
5. Status updates in system automatically

**API Endpoints:**
- `POST /adminnew/services/api/send-assignment-email.php?action=send-assignment` - Send single email
- `POST /adminnew/services/api/send-assignment-email.php?action=send-service-reminders` - Send reminders for all pending
- `GET /adminnew/services/api/confirmations.php?action=get-details&token=XXX` - Get assignment details
- `POST /adminnew/services/api/confirmations.php?action=confirm&token=XXX` - Confirm assignment
- `POST /adminnew/services/api/confirmations.php?action=decline&token=XXX` - Decline assignment

### 5. Conflict Detection

Automatic detection of scheduling conflicts when assigning members.

**Database Tables:**
- `service_scheduling_conflicts` - Log of detected conflicts

**Conflict Types:**
- **unavailable** - Member marked as unavailable on that date
- **double_booked** - Member already assigned to another service
- **over_scheduled** - Member assigned to too many roles (3+) in one service
- **insufficient_skill** - Member doesn't have capability for the role

**Features:**
- Real-time conflict warnings during assignment
- Conflict dashboard per service
- Resolution tracking
- Override capability for administrators

**Usage:**
1. System automatically checks conflicts when assigning
2. Warnings displayed in assignment modal
3. View all conflicts: `GET /adminnew/services/api/rota.php?action=check-conflicts&service_id=X`
4. Conflicts can be marked as resolved

**API Endpoint:**
- `GET /adminnew/services/api/rota.php?action=check-conflicts&service_id=X`

## Database Schema

### New Tables

```sql
-- Service Roles (Positions)
service_roles
- id
- team_id
- name
- description
- sort_order
- min_skill_level
- is_active
- created_at
- updated_at

-- Member Role Capabilities
member_role_capabilities
- id
- member_id
- role_id
- skill_level (beginner|competent|proficient|expert)
- preference_level (unwilling|willing|prefer|strong_prefer)
- notes
- is_active
- created_at
- updated_at

-- Service Rota (Assignments)
service_rota
- id
- service_id
- role_id
- member_id (nullable)
- status (unassigned|pending|confirmed|declined)
- assigned_at
- responded_at
- confirmation_token
- decline_reason
- notes
- sort_order
- created_at
- updated_at

-- Member Availability (Blackout Dates)
member_availability
- id
- member_id
- unavailable_date
- reason
- is_recurring
- created_at
- updated_at

-- Assignment Notifications
service_assignment_notifications
- id
- rota_id
- notification_type (assignment|reminder|change|cancellation)
- sent_to_email
- sent_at
- opened_at
- responded_at

-- Scheduling Conflicts
service_scheduling_conflicts
- id
- service_id
- member_id
- conflict_type
- conflict_details
- resolved
- resolved_at
- created_at
```

### Schema Updates

```sql
-- Existing tables updated
ALTER TABLE service_team_members ADD member_id (references members.id)
ALTER TABLE service_assignments ADD member_id (references members.id)
ALTER TABLE services ADD description, location
```

## Installation

1. **Run Database Migration:**
   ```bash
   php migrations/run.php 2026_03_24_002_enhanced_team_scheduling.sql
   ```

2. **Verify Tables Created:**
   - service_roles
   - member_role_capabilities
   - service_rota
   - member_availability
   - service_assignment_notifications
   - service_scheduling_conflicts

3. **Default Roles:**
   The migration automatically creates default roles for:
   - Worship Team (Leader, Vocals 1-3, Keys, Guitars, Bass, Drums)
   - Tech/AV (Sound, Projection, Lighting, Camera, Stream Director)
   - Welcome Team (Greeters, Ushers, Info Desk)
   - Kids Ministry (Teacher, Helper, Check-in)
   - Prayer Team (Prayer Minister, Prayer Room Host)

## Configuration

### Email Settings

Configure email sending in `/adminnew/services/api/send-assignment-email.php`:

```php
// Update the From address
$headers = [
    "From: Your Church Name <noreply@yourchurch.org>",
    "Reply-To: noreply@yourchurch.org",
    // ...
];
```

**Production Email Services:**

For reliable email delivery in production, consider using:
- **SendGrid** - Easy PHP integration, good deliverability
- **Mailgun** - RESTful API, detailed analytics
- **Amazon SES** - Cost-effective, scalable
- **Postmark** - Fast delivery, excellent tracking

### Base URL

Update the base URL for confirmation links in `send-assignment-email.php`:

```php
$baseUrl = "https://yourchurch.org";
```

## API Reference

### Rota Management

**Add Role to Service:**
```javascript
POST /adminnew/services/api/rota.php
{
    "action": "add-role",
    "service_id": 123,
    "role_id": 5
}
```

**Assign Member:**
```javascript
POST /adminnew/services/api/rota.php
{
    "action": "assign-member",
    "rota_id": 456,
    "member_id": 789,
    "add_capability": false,
    "send_notification": true
}
```

**Get Member Suggestions:**
```javascript
GET /adminnew/services/api/rota.php?action=suggestions&role_id=5&service_id=123&service_date=2026-03-30
```

**Check Conflicts:**
```javascript
GET /adminnew/services/api/rota.php?action=check-conflicts&service_id=123
```

### Availability Management

**Add Unavailable Date:**
```javascript
POST /adminnew/services/api/availability.php
{
    "action": "add",
    "member_id": 789,
    "unavailable_date": "2026-04-15",
    "reason": "Vacation",
    "is_recurring": false
}
```

**Add Date Range:**
```javascript
POST /adminnew/services/api/availability.php
{
    "action": "add-range",
    "member_id": 789,
    "start_date": "2026-04-15",
    "end_date": "2026-04-22",
    "reason": "Vacation"
}
```

### Notifications

**Send Assignment Email:**
```javascript
POST /adminnew/services/api/send-assignment-email.php
{
    "action": "send-assignment",
    "rota_id": 456
}
```

**Send Service Reminders:**
```javascript
POST /adminnew/services/api/send-assignment-email.php
{
    "action": "send-service-reminders",
    "service_id": 123
}
```

### Confirmations

**Get Assignment Details:**
```javascript
GET /adminnew/services/api/confirmations.php?action=get-details&token=abc123...
```

**Confirm Assignment:**
```javascript
POST /adminnew/services/api/confirmations.php
{
    "action": "confirm",
    "token": "abc123..."
}
```

**Decline Assignment:**
```javascript
POST /adminnew/services/api/confirmations.php
{
    "action": "decline",
    "token": "abc123...",
    "reason": "Schedule conflict"
}
```

## User Workflows

### Service Planning Workflow

1. **Create Service**
   - Navigate to Services > Schedule Service
   - Select service type, date, and time
   - Optionally assign teams

2. **Add Roles to Rota**
   - Open service planning page
   - Click "Add Role" in rota sidebar
   - Select roles needed (quantities for each)
   - Roles appear as "Unassigned"

3. **Assign Team Members**
   - Click "Assign" next to each role
   - System shows suggested members:
     - Sorted by availability
     - Shows time since last served
     - Highlights any conflicts
   - Select member
   - System sends notification email (optional)

4. **Review Conflicts**
   - Check for conflict warnings
   - Review unavailable members
   - Review over-scheduled members
   - Resolve or override as needed

5. **Send Notifications**
   - Click "Send Notifications" button
   - All pending assignments receive email
   - Track responses in rota view

6. **Monitor Confirmations**
   - View status badges (Pending/Confirmed/Declined)
   - Follow up with pending members
   - Replace declined assignments

### Member Workflow

1. **Set Availability**
   - Navigate to Services > My Availability
   - Click "Add Date(s)"
   - Mark unavailable dates
   - Add optional reason

2. **Receive Assignment**
   - Receive email notification
   - View service details
   - See other team members

3. **Respond to Assignment**
   - Click "I Can Serve" (confirms)
   - Or click "I Can't Make It" (declines with reason)
   - Receive confirmation message

4. **View Upcoming Assignments**
   - Check My Availability page
   - See all upcoming services
   - See current status (Pending/Confirmed)

## Troubleshooting

### Emails Not Sending

1. Check PHP `mail()` function is configured
2. Verify sender email address
3. Check spam folder
4. Consider using SMTP service (SendGrid, Mailgun)
5. Check logs in `service_assignment_notifications` table

### Conflicts Not Detecting

1. Verify member has availability record in `member_availability`
2. Check date format (YYYY-MM-DD)
3. Verify member_id matches in assignments
4. Check `service_scheduling_conflicts` table for logged conflicts

### Members Can't Confirm

1. Verify `confirmation_token` is set in `service_rota`
2. Check token in URL matches database
3. Verify service date hasn't passed
4. Check for PHP errors in `/adminnew/services/confirm.php`

## Future Enhancements

Potential features for future development:

1. **Auto-Scheduling Algorithm**
   - Automatically fill roles based on:
     - Availability
     - Rotation fairness
     - Skill levels
     - Preferences

2. **Mobile App**
   - Native iOS/Android apps
   - Push notifications
   - Quick confirm/decline
   - View schedule

3. **Availability Sync**
   - Import from Google Calendar
   - Sync with Outlook
   - Block out recurring events

4. **Team Analytics**
   - Service frequency per member
   - Rotation fairness metrics
   - Declined assignment patterns
   - Skill gap analysis

5. **Advanced Notifications**
   - SMS notifications
   - WhatsApp integration
   - Slack/Teams integration
   - Customizable reminder schedules

6. **Sub Requests**
   - Members can request substitutes
   - Notify qualified replacements
   - Track sub history

## Support

For questions or issues:
- Review this documentation
- Check API error responses
- Review database logs
- Contact system administrator

## Version History

- **1.0.0** (2026-03-24) - Initial implementation
  - Position/role management
  - Member availability tracking
  - Assignment UI with conflict detection
  - Email confirmation workflow
  - Comprehensive API endpoints
