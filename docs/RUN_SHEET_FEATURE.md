# Run Sheet / Service Flow Feature

## Overview

The Run Sheet / Service Flow feature provides comprehensive service planning and live tracking capabilities for the church management system. This feature allows worship leaders, tech teams, and service coordinators to plan services in detail, print run sheets, and track services in real-time.

## Features Implemented

### 1. Enhanced Service Items

Service items now support multiple item types beyond songs:
- Songs (with chord charts and keys)
- Scripture Readings
- Prayer
- Announcements
- Sermon/Message
- Offering
- Communion
- Video
- Custom/Other

### 2. Comprehensive Timing System

Each service item can have:
- **Planned Duration**: Expected length in minutes
- **Actual Start Time**: Real-time tracking when item begins
- **Actual End Time**: Real-time tracking when item ends
- **Running Total**: Automatic calculation of cumulative service time
- **Estimated End Time**: Projected service completion time

### 3. Multi-Team Notes & Cues

Each service item supports different types of notes for various teams:

- **General Notes**: Overall notes visible to everyone
- **Worship Team Notes**: Specific instructions for musicians/worship leaders
- **Tech/AV Notes**: Lighting cues, slide transitions, audio levels, etc.
- **Transition Notes**: How to smoothly transition to the next item

### 4. Additional Item Details

- **Presenter/Leader**: Who is leading or presenting this item
- **Video URL**: Link to video content (for video items)
- **Slides URL**: Link to presentation slides or ProPresenter files

### 5. Run Sheet View

Accessed via the "Run Sheet" button from the service plan page.

**Features:**
- Clean, printable timeline view of the entire service
- Shows start times, durations, and estimated end times for each item
- Displays all notes organized by team
- Links to videos and slides
- Multiple view modes:
  - Full View: Shows all details
  - Compact View: Condensed for quick reference
  - Tech Only View: Shows only tech/AV notes
- Toggle options for times and notes
- Professional print layout optimized for paper

**URL:** `/adminnew/services/runsheet/{service_id}`

### 6. Live Mode

Real-time service tracking interface for running services.

**Features:**
- Large, easy-to-read display optimized for tablets/large screens
- Start/Stop tracking for each item
- Live timer showing current item duration
- Progress bar showing service completion
- Elapsed time, current time, and remaining time displays
- Visual indicators for current, upcoming, and completed items
- Fullscreen mode support
- Dark theme optimized for low-light environments

**URL:** `/adminnew/services/live/{service_id}`

**Live Tracking:**
- Automatically records actual start and end times
- Calculates actual duration vs. planned duration
- Saves all timing data to database for analysis
- Enables service to start automatically when first item begins

## Database Schema

### New Fields in `service_items` Table

```sql
planned_duration INT NULL          -- Planned duration in minutes
actual_start_time DATETIME NULL    -- Actual start time when running live
actual_end_time DATETIME NULL      -- Actual end time when running live
worship_notes TEXT NULL            -- Notes for worship team
tech_notes TEXT NULL               -- Notes for tech team
transition_notes TEXT NULL         -- Transition notes to next item
presenter VARCHAR(200) NULL        -- Who is leading/presenting
video_url VARCHAR(500) NULL        -- URL for video items
slides_url VARCHAR(500) NULL       -- Link to presentation slides
```

### New Fields in `services` Table

```sql
live_mode_active BOOLEAN DEFAULT FALSE     -- Is service currently running
live_started_at DATETIME NULL             -- When live mode was started
actual_start_time DATETIME NULL           -- Actual service start time
actual_end_time DATETIME NULL             -- Actual service end time
```

### New Tables

**`service_runsheet_templates`** - Reusable run sheet templates
- Stores common service structures
- Can be applied to new services
- Includes default timings and item types

**`v_runsheet_items`** - Database view for reporting
- Combines service items with calculated fields
- Includes running totals and cumulative times
- Useful for reports and analytics

## API Endpoints

### Live Tracking API
**File:** `/adminnew/services/api/live-tracking.php`

Actions:
- `start-service` - Begin live mode for a service
- `end-service` - End live mode for a service
- `start-item` - Start tracking a specific item
- `end-item` - End tracking a specific item
- `update-item-notes` - Update notes for an item

### Enhanced Plan Actions API
**File:** `/adminnew/services/api/plan-actions.php`

Updated `update-item` action now supports:
- All new fields (presenter, notes, URLs, etc.)
- Planned duration separate from display duration
- Team-specific notes

## Usage Workflows

### Planning a Service

1. Navigate to Services > Plan Service
2. Add service items (songs, readings, etc.)
3. Click "Edit" on each item to add:
   - Planned duration
   - Presenter/leader
   - Team-specific notes
   - Links to videos/slides
4. Click "Run Sheet" to view the complete timeline
5. Print or save as PDF for team distribution

### Running a Service Live

1. Open the service in plan view
2. Click "Run Sheet" button
3. Click "Live Mode" button (top right)
4. Click "Start" on first item when service begins
5. Click "End" when item completes
6. System auto-advances to next item
7. Click "Start" on next item to continue
8. Use fullscreen mode for distraction-free tracking

### Printing Run Sheets

1. Access Run Sheet view
2. Customize view (toggle times/notes as needed)
3. Click "Print" button
4. Print settings will auto-configure for optimal layout
5. Distribute to team members

## Integration Points

### With Existing Features

- **Service Planning**: Extends the existing plan.php
- **Song Library**: Integrates with songs and chord charts
- **Service Teams**: Notes can reference team roles
- **Rota System**: Works alongside team assignments

### Future Enhancement Opportunities

1. **Analytics Dashboard**
   - Compare planned vs. actual service times
   - Track average item durations
   - Identify consistently over/under items

2. **Template Library**
   - Save common service structures
   - Apply templates to new services
   - Share templates across service types

3. **Multi-Screen Support**
   - Separate views for different teams
   - Tech booth display
   - Presenter confidence monitor

4. **Mobile App Integration**
   - Team member mobile view
   - Push notifications for upcoming roles
   - Real-time updates during service

5. **ProPresenter Integration**
   - Auto-advance slides
   - Sync timing with presentation software
   - Import/export run sheets

## Files Created

### Migration
- `/migrations/2026_03_24_002_runsheet_enhancement.sql`

### Pages
- `/adminnew/services/runsheet.php` - Run sheet view
- `/adminnew/services/live.php` - Live tracking mode

### APIs
- `/adminnew/services/api/live-tracking.php` - Live mode API

### Modified Files
- `/adminnew/services/plan.php` - Added run sheet button, enhanced edit modal
- `/adminnew/services/api/plan-actions.php` - Extended to support new fields

## Installation

1. Run the migration:
   ```bash
   mysql -u [user] -p [database] < migrations/2026_03_24_002_runsheet_enhancement.sql
   ```

2. Verify new pages are accessible:
   - Check `/adminnew/services/runsheet/[id]`
   - Check `/adminnew/services/live/[id]`

3. Test the feature:
   - Create or edit a service
   - Add items with detailed notes
   - View run sheet
   - Test live mode

## Browser Compatibility

- Chrome/Edge: Full support (recommended)
- Firefox: Full support
- Safari: Full support
- Mobile browsers: Responsive, touch-friendly

## Performance Considerations

- Run sheet view renders server-side for fast initial load
- Live mode uses client-side JavaScript for real-time updates
- API calls are minimal and efficient
- Print styles are optimized for paper/PDF

## Security

- All endpoints check for admin authentication
- SQL injection protection via PDO prepared statements
- XSS protection via proper HTML escaping
- CSRF protection via session validation

## Support & Troubleshooting

### Common Issues

**Run sheet times don't match**
- Check that service start time is set correctly
- Verify item durations are specified
- Use planned_duration for more accurate estimates

**Live mode not saving times**
- Check browser console for API errors
- Verify live-tracking.php is accessible
- Ensure database permissions allow DATETIME updates

**Print layout issues**
- Use Chrome for best print results
- Ensure CSS print styles are loading
- Check for browser extensions blocking styles

## Credits

Developed as part of the Alive Church Management System
Version: 1.0
Date: March 24, 2026
