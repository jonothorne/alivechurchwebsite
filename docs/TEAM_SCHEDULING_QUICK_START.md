# Team Scheduling Quick Start Guide

## Installation

```bash
# Run the migration
php migrations/run.php 2026_03_24_002_enhanced_team_scheduling.sql
```

## Files Created

### Database Migration
- `/migrations/2026_03_24_002_enhanced_team_scheduling.sql` - Database schema

### PHP Pages
- `/adminnew/services/availability.php` - Member availability management page
- `/adminnew/services/confirm.php` - Public confirmation page (no login required)

### API Endpoints
- `/adminnew/services/api/availability.php` - Availability/blackout date management
- `/adminnew/services/api/confirmations.php` - Assignment confirmation handling
- `/adminnew/services/api/send-assignment-email.php` - Email notification system
- `/adminnew/services/api/rota.php` - Enhanced with conflict detection

### Documentation
- `/docs/ENHANCED_TEAM_SCHEDULING.md` - Complete feature documentation
- `/docs/TEAM_SCHEDULING_QUICK_START.md` - This file

## Quick Setup

1. **Run Migration:**
   ```bash
   php migrations/run.php 2026_03_24_002_enhanced_team_scheduling.sql
   ```

2. **Update Email Settings:**
   Edit `/adminnew/services/api/send-assignment-email.php`:
   ```php
   $headers = [
       "From: Your Church <noreply@yourchurch.org>",
       // ...
   ];
   ```

3. **Set Base URL:**
   In `send-assignment-email.php`:
   ```php
   $baseUrl = "https://yourchurch.org";
   ```

4. **Test the System:**
   - Navigate to Services > Teams
   - Add roles to a team
   - Navigate to Services > Schedule Service
   - Create a test service
   - Add roles to the rota
   - Assign a member
   - Check for conflicts

## Key Features

### 1. Position Management
**Where:** Services > Teams > Select Team > Team Roles

- Define positions within teams
- Default roles already created:
  - Worship: Leader, Vocals, Keys, Guitars, Bass, Drums
  - Tech: Sound, Projection, Lighting, Camera, Stream
  - Welcome: Greeters, Ushers, Info Desk
  - Kids: Teacher, Helper, Check-in
  - Prayer: Minister, Room Host

### 2. Member Availability
**Where:** Services > My Availability

- Mark unavailable dates (single or range)
- Add optional reason
- Recurring annual dates
- View upcoming assignments

### 3. Smart Assignment
**Where:** Services > Plan Service > Rota Sidebar

- Click "Add Role" to add positions needed
- Click "Assign" to assign members
- System suggests best candidates:
  - Available on that date
  - Has role capability
  - Longest time since last served
  - Not over-scheduled
- Automatic conflict detection

### 4. Email Confirmations
**What happens:**
- Member assigned → Email sent automatically
- Member clicks "I Can Serve" or "I Can't Make It"
- Status updates in system
- Declined assignments logged as conflicts

**Confirmation Link:**
`https://yourchurch.org/adminnew/services/confirm?token=XXX`

### 5. Conflict Detection
**Automatic checks:**
- Member unavailable on date?
- Member already assigned elsewhere?
- Member assigned to 3+ roles?
- Member lacks skill for role?

**View conflicts:**
`GET /adminnew/services/api/rota.php?action=check-conflicts&service_id=123`

## Common Tasks

### Add New Role to Team
1. Services > Teams
2. Select team
3. Scroll to "Team Roles"
4. Click "Add Role"
5. Enter role name
6. Save

### Assign Member to Role
1. Services > Teams
2. Select team
3. Find member in list
4. Click edit icon
5. Check roles they can perform
6. Save

### Plan a Service
1. Services > Schedule Service
2. Fill in details
3. Click "Schedule Service"
4. On plan page, click "Add Role" in rota
5. Select roles and quantities needed
6. Click "Assign" next to each role
7. Select member from suggestions
8. Review any conflicts
9. Send notifications

### Mark Unavailable
1. Services > My Availability
2. Click "Add Date(s)"
3. Select "Single Date" or "Date Range"
4. Pick dates
5. Add reason (optional)
6. Save

### Send Assignment Emails
**Option 1: Automatic (when assigning)**
```javascript
// In assignment API call
{
    "send_notification": true
}
```

**Option 2: Manual bulk send**
```javascript
POST /adminnew/services/api/send-assignment-email.php
{
    "action": "send-service-reminders",
    "service_id": 123
}
```

## Database Tables Reference

| Table | Purpose |
|-------|---------|
| `service_roles` | Positions within teams |
| `member_role_capabilities` | Who can perform which roles |
| `service_rota` | Specific assignments for services |
| `member_availability` | Unavailable/blackout dates |
| `service_assignment_notifications` | Email notification log |
| `service_scheduling_conflicts` | Detected conflicts |

## API Quick Reference

```javascript
// Add unavailable date
POST /adminnew/services/api/availability.php
{ "action": "add", "member_id": 1, "unavailable_date": "2026-04-15" }

// Assign member to role
POST /adminnew/services/api/rota.php
{ "action": "assign-member", "rota_id": 1, "member_id": 2 }

// Check conflicts
GET /adminnew/services/api/rota.php?action=check-conflicts&service_id=123

// Send assignment email
POST /adminnew/services/api/send-assignment-email.php
{ "action": "send-assignment", "rota_id": 1 }

// Confirm assignment (public - no auth)
POST /adminnew/services/api/confirmations.php
{ "action": "confirm", "token": "abc123..." }
```

## Troubleshooting

**Emails not sending?**
- Check PHP `mail()` works: `php -r "mail('test@example.com', 'Test', 'Body');"`
- Use SMTP service like SendGrid for production
- Check spam folder

**Conflicts not detected?**
- Verify dates are in `YYYY-MM-DD` format
- Check `member_availability` table has records
- Check member_id values match

**Can't access confirmation page?**
- Verify token in URL
- Check service date hasn't passed
- Look for PHP errors in logs

## Next Steps

1. **Customize Roles:** Add/edit roles specific to your church
2. **Assign Capabilities:** Mark which members can perform which roles
3. **Set Up Teams:** Add members to appropriate teams
4. **Configure Email:** Set up production email service
5. **Train Team Leaders:** Show them the assignment interface
6. **Train Members:** Show them availability page and confirmation process

## Support

- Full documentation: `/docs/ENHANCED_TEAM_SCHEDULING.md`
- Database schema: `/migrations/2026_03_24_002_enhanced_team_scheduling.sql`
- API code: `/adminnew/services/api/`

---

**Version:** 1.0.0 (2026-03-24)
