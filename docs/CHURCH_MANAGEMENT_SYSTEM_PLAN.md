# Church Management System Implementation Plan

## Comprehensive Planning Center Clone - Integrated Church Website Solution

---

## Executive Summary

This document outlines a comprehensive plan to build a custom church management system that replicates and improves upon Planning Center's functionality, fully integrated into your church website. The system will be built using PHP (to match your existing codebase), MySQL, and modern frontend technologies.

**Key Integration Principles:**
1. Members = Website users marked as church members (not separate tables)
2. All features integrate with existing systems (events, giving, users)
3. Admin panel reorganized: "Website" section + new module sections
4. Non-breaking changes - site works at any deployment point
5. All database changes via migrations

---

## IMPORTANT: Admin Panel Structure

**The NEW admin panel is being built at `/adminnew`**

The legacy admin panel at `/admin` must remain untouched and functional. All new development happens in `/adminnew`.

**Before implementing Planning Center modules, we must first copy ALL existing legacy admin features from `/admin` into `/adminnew/website/`**

Legacy features to migrate to `/adminnew/website/`:
- [x] Blog management (blog.php, edit-blog.php, blog-categories.php, blog-comments.php)
- [x] Events management (events.php, edit-event.php)
- [x] Sermons (sermons.php, edit-sermon.php, sermon-comments.php)
- [x] Pages (pages.php)
- [x] Media library (media.php)
- [x] Forms (forms.php)
- [x] Navigation (navigation.php)
- [x] Settings (settings.php)
- [x] Bible Study (bible-study.php, edit-bible-study.php)
- [x] Reading Plans (reading-plans.php, edit-reading-plans.php)
- [x] Ministries (ministries.php)
- [x] Users (users.php)
- [x] Testimonies (testimonies.php)
- [x] Welcome Journeys (welcome-journeys.php)
- [x] Next Steps (next-steps.php)
- [x] Serve (serve.php)
- [x] Newsletter (newsletter.php)
- [x] Analytics (analytics.php + analytics-traffic.php, analytics-geographic.php, analytics-behavior.php, analytics-content.php, analytics-realtime.php, analytics-bots.php)
- [x] Profanity Filter (profanity-filter.php)

**STATUS: All website features have been copied to `/adminnew/website/`**
**NOTE: The frontend styling of each page needs to be updated to match the new admin design**

Once all website features are in `/adminnew/website/`, then proceed with Planning Center modules.

---

## Table of Contents

1. [Planning Center Feature Analysis](#1-planning-center-feature-analysis)
2. [Recommended Feature Prioritization](#2-recommended-feature-prioritization)
3. [System Architecture](#3-system-architecture)
4. [Database Schema Design](#4-database-schema-design)
5. [Module Implementation Plans](#5-module-implementation-plans)
6. [Security Considerations](#6-security-considerations)
7. [Integration Points](#7-integration-points)
8. [Mobile App Strategy](#8-mobile-app-strategy)
9. [Implementation Phases](#9-implementation-phases)

---

## 1. Planning Center Feature Analysis

### 1.1 Planning Center Products Overview

Planning Center consists of 8 interconnected products:

| Product | Purpose | Pricing Model |
|---------|---------|---------------|
| **People** | Core member database & CRM | Always FREE |
| **Services** | Worship planning & volunteer scheduling | Per-user pricing |
| **Groups** | Small group management | Per-active-member pricing |
| **Giving** | Online donations & financial tracking | Per-transaction fees |
| **Check-Ins** | Child safety & attendance | Per-user pricing |
| **Calendar** | Event & facility scheduling | Always FREE |
| **Registrations** | Event signups & payments | Per-attendee pricing |
| **Publishing** | Church Center app & website content | Free tier + paid upgrades |

### 1.2 Detailed Feature Breakdown

#### A. People (Member Database) - CORE FOUNDATION

**Profile Management:**
- Individual and family/household profiles
- Photos, contact information, addresses
- Custom fields for any data type
- Membership status tracking
- Baptism dates, salvation dates, anniversaries
- Background check integration and tracking
- Medical notes and allergies
- Communication preferences

**Lists & Segmentation:**
- Dynamic lists based on criteria (age, location, involvement)
- Static lists for manual groupings
- List-based bulk actions (email, export, workflow)
- Smart lists that auto-update

**Workflows:**
- Multi-step workflows for processes (membership, volunteering)
- Automatic triggers based on actions
- Task assignments to staff
- Email automation at workflow steps
- Progress tracking and reporting

**Forms:**
- Visitor connection cards
- Volunteer signup forms
- Membership applications
- Prayer request submissions
- Custom form builder with any field types
- File uploads (documents, images)
- Conditional logic (show/hide fields based on answers)

**Communication:**
- Email sending (individual and bulk)
- SMS/Text messaging
- Email templates
- Communication history tracking
- Unsubscribe management

**Reporting:**
- Attendance trends
- Giving summaries
- Workflow completion rates
- Custom report builder

#### B. Services (Worship Planning)

**Service Planning:**
- Service types (Contemporary, Traditional, Youth, etc.)
- Service order/run sheet builder
- Item types: Songs, Sermons, Media, Notes, Headers
- Timing for each item
- Color-coding items
- Drag-and-drop reordering
- Copy/clone services
- Service templates

**Song Library:**
- Complete song database
- Multiple arrangements per song
- Multiple keys per arrangement
- Chord charts (ChordPro format)
- Lyrics with chord positions
- Transposition to any key
- Song tags and categories
- CCLI reporting integration
- Import from SongSelect, PraiseCharts, MultiTracks

**Team Management:**
- Unlimited teams (Band, Vocals, Tech, Hospitality, etc.)
- Team positions (Lead Vocalist, Drummer, Sound Engineer)
- Team leaders with elevated permissions
- Split teams (e.g., different bands for different services)

**Scheduling:**
- Volunteer scheduling interface
- Matrix view for bulk scheduling
- Auto-scheduling based on preferences
- Availability/blockout date management
- Conflict detection
- Schedule requests with accept/decline
- Reminder emails (automatic)
- Decline reasons tracking
- Volunteer preferences (frequency, positions, times)

**Rehearsal Tools:**
- Rehearsal notes per service item
- Media attachments (audio, video, PDFs)
- Integration with Music Stand app

**Communication:**
- Team chat/messaging
- Service-specific announcements
- Direct messaging to scheduled volunteers

#### C. Groups (Small Groups)

**Group Management:**
- Group types (Life Groups, Bible Studies, Recovery, etc.)
- Group creation with full details
- Leaders and co-leaders
- Membership management
- Open vs. closed groups
- Location-based grouping
- Group tags and categories

**Discovery & Signup:**
- Public group directory
- Search and filter by location, day, type
- Online signup requests
- Leader approval workflow
- Waitlists for full groups

**Communication:**
- Group chat/messaging
- Email to group members
- Announcements
- Push notifications

**Resources:**
- Curriculum sharing
- File attachments
- Links to videos/content
- Discussion guides

**Events & Attendance:**
- Group event scheduling
- RSVP functionality
- Attendance tracking
- Attendance reporting
- Health metrics (participation %, turnover)

#### D. Giving (Donations)

**Donation Methods:**
- Credit/Debit card
- ACH bank transfer
- Apple Pay / Google Pay
- Text-to-give
- Cash/check recording
- Kiosk giving

**Recurring Giving:**
- Set frequency (weekly, bi-weekly, monthly, twice monthly)
- Pause/resume recurring gifts
- Payment method updates
- Card expiration auto-update

**Fund Management:**
- Multiple funds (General, Missions, Building, etc.)
- Default fund assignment
- Fund visibility settings

**Campaigns & Pledges:**
- Campaign creation with goals
- Pledge tracking
- Progress visualization
- Campaign-specific giving pages

**Donor Management:**
- Giving history
- Donor statements
- Year-end tax statements
- Anonymous giving option

**Reporting:**
- Giving trends
- Fund performance
- Donor retention
- Per-capita giving
- Comparison reports

#### E. Check-Ins (Attendance & Child Safety)

**Check-In Stations:**
- Self-service kiosks
- Manned stations
- Mobile check-in

**Label Printing:**
- Child name labels
- Parent security labels
- Custom label designer
- Allergy/medical alerts on labels
- Barcode/QR code support

**Security Features:**
- Matching security codes (child + parent)
- Authorized pickup list
- Not-authorized alerts
- Photo identification
- Check-out verification
- SMS alerts to parents
- Location tracking within facility

**Attendance:**
- Event-based attendance
- Historical attendance records
- First-time visitor flagging
- Attendance reporting

**Room/Capacity Management:**
- Room assignments
- Capacity limits
- Overflow handling
- Age-appropriate routing

#### F. Calendar (Events & Facilities)

**Event Management:**
- Event creation with full details
- Recurring events
- Event categories
- Public vs. private events
- Event approvals workflow

**Facility/Room Booking:**
- Room catalog with details
- Room setup configurations
- Resource inventory (tables, chairs, A/V equipment)
- Booking requests
- Approval workflows
- Conflict detection

**Event Request Forms:**
- Custom request forms
- Conditional fields
- Auto-assignment based on form data
- Approval routing

**Integration:**
- iCal export/import
- Google Calendar sync
- Public calendar display

#### G. Registrations (Event Signups)

**Registration Forms:**
- Custom form builder
- Attendee questions
- Multiple attendee registration
- T-shirt sizes, meal preferences, etc.
- Document uploads
- Waivers and releases

**Payments:**
- Full payment
- Deposits and partial payments
- Installment plans
- Discounts and scholarships
- Promo codes
- Cash/check option

**Capacity Management:**
- Registration limits
- Waitlists
- Automatic cutoffs

**Add-ons:**
- Optional purchases (t-shirts, meals)
- Upsells during registration

**Communication:**
- Confirmation emails
- Reminder emails
- Balance due notifications

**Reporting:**
- Registration summaries
- Payment tracking
- Attendee lists

#### H. Publishing (Church Center App/Website)

**Church Center App:**
- Custom-branded mobile app (iOS/Android)
- Or: Progressive Web App (PWA)

**Content Management:**
- Home page customization
- Custom pages (drag-and-drop builder)
- Navigation management
- Branding (colors, logo)

**Content Types:**
- Sermons library (video, audio, notes)
- Event calendar
- Group directory
- Giving portal
- Check-in
- Profile management
- Directory (opt-in)

**Blocks/Widgets:**
- Text blocks
- Image/video blocks
- Event schedule blocks
- Location/map blocks
- Contact blocks
- Countdown timers
- Announcements

### 1.3 Supporting Features

**Music Stand App:**
- Digital music reader
- Chord chart display
- Lyrics with chords
- Transposition
- Annotations
- Foot pedal support
- Session sync (leader controls all devices)
- Offline access

**Headcounts:**
- Manual attendance counts
- Historical tracking
- Reporting

**Webhooks & API:**
- REST API (JSON:API spec)
- Webhooks for real-time updates
- OAuth 2.0 authentication
- Personal access tokens

---

## 2. Recommended Feature Prioritization

Based on typical church needs, here's my recommended prioritization:

### Tier 1: Essential (Build First)
1. **People** - Foundation for everything else
2. **Calendar** - Core event management
3. **Groups** - Community connection
4. **Giving** - Financial sustainability

### Tier 2: High Value (Build Second)
5. **Services** - Worship team coordination
6. **Registrations** - Event management
7. **Check-Ins** - Child safety

### Tier 3: Enhanced Experience (Build Third)
8. **Publishing/Church Center** - Member-facing portal
9. **Music Stand** - Musician tools

### Features You May NOT Need

Based on your context, consider whether you need:
- [ ] Multiple campuses support
- [ ] CCLI reporting (if you don't report)
- [ ] Complex approval workflows
- [ ] SMS/Text messaging (cost implications)
- [ ] Kiosk hardware integration
- [ ] Native mobile apps (PWA may suffice)

---

## 3. System Architecture

### 3.1 Integration with Existing Site

Your existing site already has:
- User authentication system
- Events system
- Giving/donations (Stripe)
- Admin panel
- Blog/content management

**Integration Strategy:**
```
EXISTING SYSTEM                    NEW PLANNING CENTER FEATURES
================                   ============================
users table          ──────────►   Add: is_member, member_since,
                                   membership_status_id, household_id

events table         ──────────►   Extend with: registrations,
                                   room bookings, check-ins

donations table      ──────────►   Extend with: funds, recurring,
                                   campaigns, statements

admin panel          ──────────►   Reorganize: Website section +
                                   People, Groups, Services, etc.
```

### 3.2 Directory Structure (Additions)

```
alivechurchsite/
├── admin/
│   ├── index.php              # Existing - add new nav items
│   ├── website/               # NEW: Moved website settings here
│   │   ├── settings.php
│   │   ├── pages.php
│   │   └── navigation.php
│   ├── people/                # NEW: People/Members module
│   │   ├── index.php
│   │   ├── view.php
│   │   ├── households.php
│   │   ├── lists.php
│   │   └── workflows.php
│   ├── groups/                # NEW: Groups module
│   │   ├── index.php
│   │   ├── view.php
│   │   └── types.php
│   ├── services/              # NEW: Worship planning
│   │   ├── index.php
│   │   ├── plan.php
│   │   ├── songs.php
│   │   ├── teams.php
│   │   └── schedule.php
│   ├── giving/                # NEW: Enhanced giving admin
│   │   ├── index.php
│   │   ├── funds.php
│   │   ├── campaigns.php
│   │   ├── batches.php
│   │   └── statements.php
│   ├── check-ins/             # NEW: Check-in system
│   │   ├── index.php
│   │   ├── events.php
│   │   ├── stations.php
│   │   └── labels.php
│   └── calendar/              # NEW: Enhanced calendar/rooms
│       ├── rooms.php
│       ├── resources.php
│       └── bookings.php
├── includes/
│   └── Services/              # NEW: Business logic services
│       ├── PeopleService.php
│       ├── GroupsService.php
│       ├── GivingService.php
│       ├── ServicesService.php
│       ├── CheckInsService.php
│       └── WorkflowEngine.php
├── api/
│   └── v1/                    # NEW: API endpoints
│       ├── people.php
│       ├── groups.php
│       ├── giving.php
│       └── services.php
├── migrations/                # Database migrations
│   ├── 001_add_member_fields_to_users.sql
│   ├── 002_create_households.sql
│   ├── 003_create_groups_tables.sql
│   └── ...
└── groups/                    # Public group pages
    ├── index.php              # Group finder
    └── view.php               # Group detail
```

---

## 4. Database Schema Design

### 4.1 Integration Approach

**Principle: Extend existing tables, don't replace them.**

```sql
-- EXTEND existing users table (don't create separate people table)
ALTER TABLE users ADD COLUMN is_member BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN membership_status_id INT;
ALTER TABLE users ADD COLUMN member_since DATE;
ALTER TABLE users ADD COLUMN household_id INT;
-- ... more fields

-- EXTEND existing events table
ALTER TABLE events ADD COLUMN room_id INT;
ALTER TABLE events ADD COLUMN registration_enabled BOOLEAN DEFAULT FALSE;
ALTER TABLE events ADD COLUMN max_registrations INT;
-- ... more fields

-- EXTEND existing donations table
ALTER TABLE donations ADD COLUMN fund_id INT;
ALTER TABLE donations ADD COLUMN recurring_gift_id INT;
-- ... more fields
```

### 4.2 New Tables (Created via Migrations)

All new tables will be created via migration files in `/migrations/`.

#### People Module Extensions

```sql
-- Migration: 001_add_member_fields_to_users.sql
-- Extends the existing users table with membership fields

ALTER TABLE users
    ADD COLUMN is_member BOOLEAN DEFAULT FALSE AFTER email,
    ADD COLUMN membership_status_id INT AFTER is_member,
    ADD COLUMN member_since DATE AFTER membership_status_id,
    ADD COLUMN household_id INT AFTER member_since,
    ADD COLUMN household_role ENUM('primary', 'spouse', 'child', 'other') DEFAULT 'primary',
    ADD COLUMN middle_name VARCHAR(100) AFTER first_name,
    ADD COLUMN nickname VARCHAR(100) AFTER last_name,
    ADD COLUMN prefix VARCHAR(20),
    ADD COLUMN suffix VARCHAR(20),
    ADD COLUMN gender ENUM('male', 'female', 'other'),
    ADD COLUMN birthdate DATE,
    ADD COLUMN marital_status ENUM('single', 'married', 'divorced', 'widowed', 'separated'),
    ADD COLUMN anniversary DATE,
    ADD COLUMN salvation_date DATE,
    ADD COLUMN baptism_date DATE,
    ADD COLUMN directory_visible BOOLEAN DEFAULT TRUE,
    ADD COLUMN communication_preferences JSON,
    ADD INDEX idx_is_member (is_member),
    ADD INDEX idx_membership_status (membership_status_id),
    ADD INDEX idx_household (household_id);
```

```sql
-- Migration: 002_create_households.sql

CREATE TABLE households (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    primary_contact_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (primary_contact_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Migration: 003_create_membership_statuses.sql

CREATE TABLE membership_statuses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7),
    is_member BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default statuses
INSERT INTO membership_statuses (name, color, is_member, sort_order) VALUES
('Visitor', '#9CA3AF', FALSE, 1),
('Regular Attender', '#3B82F6', FALSE, 2),
('Member', '#10B981', TRUE, 3),
('Leader', '#8B5CF6', TRUE, 4),
('Inactive', '#EF4444', FALSE, 5);
```

```sql
-- Migration: 004_create_addresses.sql

CREATE TABLE addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    household_id INT,
    street VARCHAR(255),
    street2 VARCHAR(255),
    city VARCHAR(100),
    county VARCHAR(100),
    postcode VARCHAR(20),
    country VARCHAR(100) DEFAULT 'United Kingdom',
    location_type ENUM('home', 'work', 'mailing', 'other') DEFAULT 'home',
    is_primary BOOLEAN DEFAULT FALSE,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_household (household_id)
);
```

```sql
-- Migration: 005_create_phone_numbers.sql

CREATE TABLE phone_numbers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    number VARCHAR(30) NOT NULL,
    location_type ENUM('home', 'work', 'mobile', 'other') DEFAULT 'mobile',
    is_primary BOOLEAN DEFAULT FALSE,
    can_receive_sms BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
);
```

```sql
-- Migration: 006_create_people_tags.sql

CREATE TABLE member_tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    tag_group VARCHAR(100),
    color VARCHAR(7),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_tags (
    user_id INT NOT NULL,
    tag_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    added_by INT,
    PRIMARY KEY (user_id, tag_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES member_tags(id) ON DELETE CASCADE
);
```

```sql
-- Migration: 007_create_people_notes.sql

CREATE TABLE user_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    note TEXT NOT NULL,
    note_type ENUM('general', 'prayer', 'counseling', 'follow_up', 'private') DEFAULT 'general',
    is_pinned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_user (user_id)
);
```

#### Groups Module

```sql
-- Migration: 010_create_groups_tables.sql

CREATE TABLE group_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7),
    default_visibility ENUM('public', 'private', 'unlisted') DEFAULT 'public',
    allow_signups BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_type_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,

    -- Schedule
    meeting_day ENUM('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'),
    meeting_time TIME,
    meeting_frequency ENUM('weekly', 'bi-weekly', 'monthly', 'custom'),

    -- Location
    location_type ENUM('physical', 'online', 'hybrid') DEFAULT 'physical',
    location_name VARCHAR(200),
    address_id INT,
    online_url VARCHAR(500),

    -- Visibility & Signup
    visibility ENUM('public', 'private', 'unlisted') DEFAULT 'public',
    allow_signups BOOLEAN DEFAULT TRUE,
    requires_approval BOOLEAN DEFAULT FALSE,

    -- Capacity
    max_members INT,

    -- Contact
    contact_email VARCHAR(255),

    -- Status
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active',

    -- Features
    childcare_available BOOLEAN DEFAULT FALSE,
    target_demographics JSON,

    -- Image
    image_url VARCHAR(500),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,

    FOREIGN KEY (group_type_id) REFERENCES group_types(id),
    FOREIGN KEY (address_id) REFERENCES addresses(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_type (group_type_id),
    INDEX idx_status (status),
    FULLTEXT INDEX ft_search (name, description)
);

CREATE TABLE group_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    user_id INT NOT NULL,

    role ENUM('member', 'leader', 'co-leader', 'admin') DEFAULT 'member',
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',

    -- Permissions
    can_take_attendance BOOLEAN DEFAULT FALSE,
    can_manage_resources BOOLEAN DEFAULT FALSE,
    can_send_messages BOOLEAN DEFAULT FALSE,

    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_group_user (group_id, user_id)
);

CREATE TABLE group_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,

    title VARCHAR(200) NOT NULL,
    description TEXT,

    event_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,

    location_name VARCHAR(200),
    address_id INT,

    rsvp_enabled BOOLEAN DEFAULT TRUE,

    cancelled BOOLEAN DEFAULT FALSE,
    cancelled_reason VARCHAR(255),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,

    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_group_date (group_id, event_date)
);

CREATE TABLE group_attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_event_id INT NOT NULL,
    user_id INT NOT NULL,

    status ENUM('present', 'absent', 'excused') DEFAULT 'present',

    rsvp_status ENUM('yes', 'no', 'maybe'),
    rsvp_at TIMESTAMP,

    checked_in_at TIMESTAMP,
    checked_in_by INT,

    FOREIGN KEY (group_event_id) REFERENCES group_events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE INDEX idx_event_user (group_event_id, user_id)
);

CREATE TABLE group_resources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,

    title VARCHAR(200) NOT NULL,
    description TEXT,
    resource_type ENUM('document', 'video', 'link', 'curriculum') NOT NULL,

    file_path VARCHAR(500),
    file_size INT,
    url VARCHAR(500),

    visible_to_members BOOLEAN DEFAULT TRUE,

    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,

    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_group (group_id)
);

CREATE TABLE group_signup_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    user_id INT NOT NULL,

    status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    message TEXT,

    response_notes TEXT,
    responded_by INT,
    responded_at TIMESTAMP,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_status (status)
);

-- Insert default group types
INSERT INTO group_types (name, description, color, sort_order) VALUES
('Life Groups', 'Weekly small groups for community and discipleship', '#10B981', 1),
('Bible Studies', 'In-depth Bible study groups', '#3B82F6', 2),
('Ministry Teams', 'Serving teams and ministry groups', '#8B5CF6', 3),
('Interest Groups', 'Groups based on shared interests', '#F59E0B', 4),
('Support Groups', 'Recovery and support groups', '#EC4899', 5);
```

#### Giving Module Extensions

```sql
-- Migration: 020_create_giving_tables.sql

-- Funds for donation allocation
CREATE TABLE giving_funds (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7),
    is_default BOOLEAN DEFAULT FALSE,
    visible_online BOOLEAN DEFAULT TRUE,
    tax_deductible BOOLEAN DEFAULT TRUE,
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Campaigns for special giving initiatives
CREATE TABLE giving_campaigns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    goal_amount DECIMAL(15, 2),
    start_date DATE,
    end_date DATE,
    fund_id INT,
    show_progress BOOLEAN DEFAULT TRUE,
    image_url VARCHAR(500),
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fund_id) REFERENCES giving_funds(id)
);

-- Pledges for campaigns
CREATE TABLE giving_pledges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    pledge_amount DECIMAL(15, 2) NOT NULL,
    frequency ENUM('one-time', 'weekly', 'monthly', 'yearly'),
    start_date DATE,
    end_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES giving_campaigns(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_campaign (campaign_id)
);

-- Stored payment methods
CREATE TABLE payment_methods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('card', 'bank_account') NOT NULL,
    stripe_payment_method_id VARCHAR(100),
    last_four VARCHAR(4),
    brand VARCHAR(50),
    bank_name VARCHAR(100),
    is_default BOOLEAN DEFAULT FALSE,
    exp_month TINYINT,
    exp_year SMALLINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
);

-- Recurring gifts
CREATE TABLE recurring_gifts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    payment_method_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    frequency ENUM('weekly', 'bi-weekly', 'monthly', 'twice-monthly') NOT NULL,
    day_of_month TINYINT,
    day_of_week TINYINT,
    fund_allocations JSON,
    stripe_subscription_id VARCHAR(100),
    status ENUM('active', 'paused', 'cancelled') DEFAULT 'active',
    paused_until DATE,
    next_scheduled DATE,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cancelled_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id),
    INDEX idx_user (user_id),
    INDEX idx_status (status)
);

-- Extend existing donations table (if it exists)
-- If donations table doesn't exist, create it

-- Donation batches for cash/check entry
CREATE TABLE donation_batches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    description VARCHAR(200),
    batch_date DATE NOT NULL,
    expected_total DECIMAL(15, 2),
    actual_total DECIMAL(15, 2),
    status ENUM('open', 'committed') DEFAULT 'open',
    committed_at TIMESTAMP,
    committed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    INDEX idx_date (batch_date)
);

-- Giving statements
CREATE TABLE giving_statements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    household_id INT,
    year YEAR NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_amount DECIMAL(15, 2) NOT NULL,
    file_path VARCHAR(500),
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_year (user_id, year)
);

-- Insert default funds
INSERT INTO giving_funds (name, description, color, is_default, sort_order) VALUES
('General Fund', 'Supports the general operations and ministry of the church', '#10B981', TRUE, 1),
('Missions', 'Supports local and global mission partners', '#3B82F6', FALSE, 2),
('Building Fund', 'Supports building maintenance and improvements', '#8B5CF6', FALSE, 3),
('Benevolence', 'Helps those in need within our community', '#EC4899', FALSE, 4);
```

#### Services (Worship Planning) Module

```sql
-- Migration: 030_create_services_tables.sql

CREATE TABLE service_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    frequency ENUM('weekly', 'bi-weekly', 'monthly', 'custom'),
    default_day TINYINT,
    default_time TIME,
    color VARCHAR(7),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE worship_services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_type_id INT NOT NULL,
    title VARCHAR(200),
    service_date DATE NOT NULL,
    service_time TIME,
    status ENUM('draft', 'scheduled', 'confirmed', 'completed') DEFAULT 'draft',
    series_title VARCHAR(200),
    sermon_title VARCHAR(200),
    sermon_notes TEXT,
    total_duration INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (service_type_id) REFERENCES service_types(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_date (service_date),
    INDEX idx_type_date (service_type_id, service_date)
);

CREATE TABLE songs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(200),
    copyright VARCHAR(500),
    ccli_number VARCHAR(20),
    default_key VARCHAR(10),
    bpm INT,
    time_signature VARCHAR(10),
    lyrics TEXT,
    themes JSON,
    last_used DATE,
    times_used INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FULLTEXT INDEX ft_search (title, author, lyrics)
);

CREATE TABLE song_arrangements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    song_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    chord_chart TEXT,
    lyrics_with_chords TEXT,
    bpm INT,
    default_key VARCHAR(10),
    sequence VARCHAR(500),
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
    INDEX idx_song (song_id)
);

CREATE TABLE service_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_id INT NOT NULL,
    item_type ENUM('header', 'song', 'sermon', 'media', 'note', 'custom') NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    song_id INT,
    song_key VARCHAR(10),
    arrangement_id INT,
    duration INT,
    start_time TIME,
    sort_order INT NOT NULL,
    color VARCHAR(7),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES worship_services(id) ON DELETE CASCADE,
    FOREIGN KEY (song_id) REFERENCES songs(id),
    FOREIGN KEY (arrangement_id) REFERENCES song_arrangements(id),
    INDEX idx_service (service_id)
);

CREATE TABLE serving_teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_type_id INT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    default_status ENUM('unconfirmed', 'confirmed') DEFAULT 'unconfirmed',
    notify_on_schedule BOOLEAN DEFAULT TRUE,
    reminder_days_before INT DEFAULT 3,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_type_id) REFERENCES service_types(id),
    INDEX idx_service_type (service_type_id)
);

CREATE TABLE team_positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    quantity_needed INT DEFAULT 1,
    requires_background_check BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES serving_teams(id) ON DELETE CASCADE,
    INDEX idx_team (team_id)
);

CREATE TABLE team_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('member', 'leader', 'admin') DEFAULT 'member',
    position_ids JSON,
    preferred_times JSON,
    notes TEXT,
    status ENUM('active', 'inactive', 'on_break') DEFAULT 'active',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES serving_teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_team_user (team_id, user_id)
);

CREATE TABLE service_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_id INT NOT NULL,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    position_id INT,
    status ENUM('pending', 'confirmed', 'declined') DEFAULT 'pending',
    responded_at TIMESTAMP,
    decline_reason TEXT,
    scheduled_times JSON,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (service_id) REFERENCES worship_services(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES serving_teams(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (position_id) REFERENCES team_positions(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_service (service_id),
    INDEX idx_user (user_id)
);

CREATE TABLE blockout_dates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    team_id INT,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES serving_teams(id),
    INDEX idx_user (user_id),
    INDEX idx_dates (start_date, end_date)
);

-- Insert default service types
INSERT INTO service_types (name, description, default_day, default_time, color, sort_order) VALUES
('Sunday Service', 'Main Sunday morning service', 0, '10:30:00', '#10B981', 1),
('Midweek', 'Wednesday evening service', 3, '19:30:00', '#3B82F6', 2),
('Youth', 'Friday youth service', 5, '19:00:00', '#8B5CF6', 3);

-- Insert default teams
INSERT INTO serving_teams (name, description, sort_order) VALUES
('Worship Team', 'Musicians and vocalists', 1),
('Tech Team', 'Sound, lighting, and projection', 2),
('Welcome Team', 'Greeters and ushers', 3),
('Kids Team', 'Children''s ministry volunteers', 4),
('Prayer Team', 'Prayer ministry', 5);
```

#### Check-Ins Module

```sql
-- Migration: 040_create_checkins_tables.sql

CREATE TABLE check_in_event_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    requires_security_code BOOLEAN DEFAULT TRUE,
    print_labels BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE check_in_locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    parent_id INT,
    min_age_months INT,
    max_age_months INT,
    grade_level VARCHAR(50),
    max_capacity INT,
    current_count INT DEFAULT 0,
    allow_checkout BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (parent_id) REFERENCES check_in_locations(id),
    INDEX idx_parent (parent_id)
);

CREATE TABLE authorized_pickups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    child_id INT NOT NULL,
    authorized_user_id INT,
    authorized_name VARCHAR(200),
    relationship VARCHAR(100),
    photo_url VARCHAR(500),
    is_blocked BOOLEAN DEFAULT FALSE,
    blocked_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (child_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (authorized_user_id) REFERENCES users(id),
    INDEX idx_child (child_id)
);

CREATE TABLE check_in_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,  -- Links to existing events table
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME,
    headcount INT,
    headcount_by INT,
    headcount_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id),
    INDEX idx_date (event_date)
);

CREATE TABLE check_ins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    user_id INT NOT NULL,
    location_id INT,
    security_code VARCHAR(10),
    checked_in_by_id INT,
    checked_in_at TIMESTAMP NOT NULL,
    checked_out_at TIMESTAMP,
    checked_out_by_id INT,
    child_label_printed BOOLEAN DEFAULT FALSE,
    security_label_printed BOOLEAN DEFAULT FALSE,
    check_in_type ENUM('regular', 'volunteer', 'first_time', 'guest') DEFAULT 'regular',
    notes TEXT,
    FOREIGN KEY (session_id) REFERENCES check_in_sessions(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (location_id) REFERENCES check_in_locations(id),
    FOREIGN KEY (checked_in_by_id) REFERENCES users(id),
    INDEX idx_session (session_id),
    INDEX idx_user (user_id),
    INDEX idx_security (security_code)
);

CREATE TABLE medical_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    note TEXT NOT NULL,
    note_type ENUM('allergy', 'medical', 'dietary', 'other') NOT NULL,
    show_on_label BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
);

-- Insert default check-in event types
INSERT INTO check_in_event_types (name, description, sort_order) VALUES
('Sunday Service', 'Main Sunday service check-in', 1),
('Kids Church', 'Children''s ministry check-in', 2),
('Youth', 'Youth ministry check-in', 3),
('Midweek', 'Midweek service check-in', 4);

-- Insert default locations
INSERT INTO check_in_locations (name, min_age_months, max_age_months, sort_order) VALUES
('Nursery', 0, 24, 1),
('Toddlers', 24, 48, 2),
('Preschool', 48, 72, 3),
('Primary', 72, 132, 4),
('Youth', 132, 216, 5);
```

#### Calendar/Rooms Module

```sql
-- Migration: 050_create_rooms_resources.sql

CREATE TABLE rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    max_capacity INT,
    building VARCHAR(100),
    floor VARCHAR(50),
    features JSON,
    requires_approval BOOLEAN DEFAULT FALSE,
    color VARCHAR(7),
    photo_url VARCHAR(500),
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE room_setups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    diagram_url VARCHAR(500),
    default_resources JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

CREATE TABLE resources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    quantity_available INT DEFAULT 1,
    serial_number VARCHAR(100),
    purchase_date DATE,
    last_service_date DATE,
    next_service_date DATE,
    requires_approval BOOLEAN DEFAULT FALSE,
    status ENUM('available', 'in_use', 'maintenance', 'retired') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Extend existing events table for room bookings
ALTER TABLE events
    ADD COLUMN room_id INT AFTER location,
    ADD COLUMN setup_time INT AFTER end_time,
    ADD COLUMN teardown_time INT AFTER setup_time,
    ADD COLUMN approval_status ENUM('pending', 'approved', 'denied') DEFAULT 'approved',
    ADD COLUMN approved_by INT,
    ADD COLUMN approved_at TIMESTAMP,
    ADD FOREIGN KEY (room_id) REFERENCES rooms(id);

CREATE TABLE resource_bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    resource_id INT NOT NULL,
    quantity INT DEFAULT 1,
    status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (resource_id) REFERENCES resources(id)
);

-- Insert default rooms
INSERT INTO rooms (name, description, max_capacity, features) VALUES
('Main Hall', 'Main worship hall', 200, '["projector", "sound_system", "stage"]'),
('Youth Room', 'Youth ministry space', 50, '["projector", "sound_system", "games"]'),
('Meeting Room 1', 'Small meeting room', 15, '["whiteboard", "tv"]'),
('Meeting Room 2', 'Small meeting room', 15, '["whiteboard", "tv"]'),
('Kitchen', 'Church kitchen', 10, '["oven", "fridge", "dishwasher"]');
```

#### Event Registrations

```sql
-- Migration: 060_create_registrations.sql

-- Extend existing events table for registrations
ALTER TABLE events
    ADD COLUMN registration_enabled BOOLEAN DEFAULT FALSE,
    ADD COLUMN registration_opens DATETIME,
    ADD COLUMN registration_closes DATETIME,
    ADD COLUMN max_registrations INT,
    ADD COLUMN current_registrations INT DEFAULT 0,
    ADD COLUMN waitlist_enabled BOOLEAN DEFAULT FALSE,
    ADD COLUMN is_free BOOLEAN DEFAULT TRUE,
    ADD COLUMN base_price DECIMAL(10, 2),
    ADD COLUMN allow_partial_payment BOOLEAN DEFAULT FALSE,
    ADD COLUMN minimum_deposit DECIMAL(10, 2);

CREATE TABLE registration_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    question TEXT NOT NULL,
    field_type ENUM('text', 'textarea', 'select', 'multiselect', 'checkbox', 'number', 'date', 'file') NOT NULL,
    options JSON,
    required BOOLEAN DEFAULT FALSE,
    show_condition JSON,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE registration_addons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    options JSON,
    max_quantity INT,
    quantity_sold INT DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE promo_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    code VARCHAR(50) NOT NULL,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10, 2) NOT NULL,
    max_uses INT,
    times_used INT DEFAULT 0,
    valid_from DATETIME,
    valid_until DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_code (event_id, code)
);

CREATE TABLE event_registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    amount_paid DECIMAL(10, 2) DEFAULT 0,
    status ENUM('pending', 'confirmed', 'waitlist', 'cancelled', 'refunded') DEFAULT 'pending',
    promo_code_id INT,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    payment_method ENUM('card', 'bank', 'cash', 'check'),
    stripe_payment_intent_id VARCHAR(100),
    answers JSON,
    addons JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (promo_code_id) REFERENCES promo_codes(id),
    INDEX idx_event (event_id),
    INDEX idx_user (user_id)
);

CREATE TABLE registration_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    registration_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('card', 'bank', 'cash', 'check') NOT NULL,
    stripe_charge_id VARCHAR(100),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'completed',
    paid_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (registration_id) REFERENCES event_registrations(id)
);
```

---

## 5. Module Implementation Plans

### 5.1 Phase 1: People/Members Foundation

**Goal:** Extend existing users to support membership features

**Migrations:**
1. Add member fields to users table
2. Create households table
3. Create membership_statuses table
4. Create addresses table
5. Create phone_numbers table
6. Create member_tags tables
7. Create user_notes table

**Admin Pages:**
- `/admin/people/` - Member list with filters
- `/admin/people/view.php` - Member profile view
- `/admin/people/households.php` - Household management
- `/admin/people/lists.php` - Lists/segments
- `/admin/settings/membership.php` - Membership statuses

**Public Features:**
- Profile page updates (existing) - add membership info display
- Directory page (new) - opt-in member directory

### 5.2 Phase 2: Groups

**Goal:** Full small groups system

**Migrations:**
- Create group_types table
- Create groups table
- Create group_members table
- Create group_events table
- Create group_attendance table
- Create group_resources table
- Create group_signup_requests table

**Admin Pages:**
- `/admin/groups/` - Group list
- `/admin/groups/view.php` - Group detail/management
- `/admin/groups/types.php` - Group type settings

**Public Pages:**
- `/groups/` - Group finder
- `/groups/view.php` - Group detail with signup

### 5.3 Phase 3: Giving Enhancements

**Goal:** Extend existing giving with funds, recurring, statements

**Migrations:**
- Create giving_funds table
- Create giving_campaigns table
- Create giving_pledges table
- Create payment_methods table
- Create recurring_gifts table
- Create donation_batches table
- Create giving_statements table
- Add fund_id to existing donations

**Admin Pages:**
- `/admin/giving/` - Enhanced giving dashboard
- `/admin/giving/funds.php` - Fund management
- `/admin/giving/campaigns.php` - Campaign management
- `/admin/giving/batches.php` - Cash/check batch entry
- `/admin/giving/statements.php` - Statement generation

**Public Features:**
- Enhanced give page with fund selection
- Recurring giving setup
- Giving history in profile

### 5.4 Phase 4: Services (Worship Planning)

**Goal:** Full worship planning system

**Migrations:**
- Create service_types table
- Create worship_services table
- Create songs table
- Create song_arrangements table
- Create service_items table
- Create serving_teams table
- Create team_positions table
- Create team_members table
- Create service_schedules table
- Create blockout_dates table

**Admin Pages:**
- `/admin/services/` - Service list/calendar
- `/admin/services/plan.php` - Service order builder
- `/admin/services/songs.php` - Song library
- `/admin/services/teams.php` - Team management
- `/admin/services/schedule.php` - Scheduling interface

**Public Features:**
- Schedule view for volunteers
- Accept/decline interface
- Blockout date management

### 5.5 Phase 5: Check-Ins

**Goal:** Attendance and child safety system

**Migrations:**
- Create check_in_event_types table
- Create check_in_locations table
- Create authorized_pickups table
- Create check_in_sessions table
- Create check_ins table
- Create medical_notes table

**Admin Pages:**
- `/admin/check-ins/` - Check-in dashboard
- `/admin/check-ins/events.php` - Event types
- `/admin/check-ins/locations.php` - Location/room setup
- `/admin/check-ins/reports.php` - Attendance reports

**Public Features:**
- Self-service check-in interface
- Parent SMS alerts (optional)

### 5.6 Phase 6: Event Registrations

**Goal:** Event signup and payment system

**Migrations:**
- Add registration fields to events table
- Create registration_questions table
- Create registration_addons table
- Create promo_codes table
- Create event_registrations table
- Create registration_payments table

**Admin Pages:**
- Event edit page - registration settings
- Registration management for events
- Attendee lists and exports

**Public Features:**
- Event registration form
- Payment processing
- Registration confirmation

---

## 6. Security Considerations

*[Same as original document]*

---

## 7. Integration Points

### 7.1 Existing System Integration

**Users Table:**
- All new features reference the existing `users` table
- Member fields added via migration
- No separate "people" table

**Events Table:**
- Room bookings link to existing events
- Registrations link to existing events
- Check-ins link to existing events

**Donations:**
- Existing donation system extended
- Fund allocation added
- Recurring gifts added

**Admin Panel:**
- Existing admin structure preserved
- New sections added alongside existing
- Website settings moved to `/admin/website/`

### 7.2 Stripe Integration

Your existing Stripe integration will be extended for:
- Recurring donations (Subscriptions API)
- Saved payment methods (Payment Methods API)
- Event registration payments (Payment Intents API)

---

## 8. Mobile App Strategy

*[Same as original document - PWA recommended]*

---

## 9. Implementation Phases

### Phase Overview

```
Phase 1: People/Members Foundation
    ↓
Phase 2: Groups
    ↓
Phase 3: Giving Enhancements
    ↓
Phase 4: Services (Worship Planning)
    ↓
Phase 5: Check-Ins
    ↓
Phase 6: Event Registrations
    ↓
Phase 7: Workflows & Automation
    ↓
Phase 8: Reporting & Analytics
```

Each phase:
1. Database migrations created
2. Backend services implemented
3. Admin interface built
4. Public features added
5. Testing completed
6. Can be deployed independently

---

## Appendix: Migration Naming Convention

All migrations follow this pattern:
```
migrations/
├── 001_add_member_fields_to_users.sql
├── 002_create_households.sql
├── 003_create_membership_statuses.sql
├── 004_create_addresses.sql
├── 005_create_phone_numbers.sql
├── 006_create_people_tags.sql
├── 007_create_people_notes.sql
├── 010_create_groups_tables.sql
├── 020_create_giving_tables.sql
├── 030_create_services_tables.sql
├── 040_create_checkins_tables.sql
├── 050_create_rooms_resources.sql
├── 060_create_registrations.sql
└── ...
```

Number gaps allow for inserting related migrations later.

---

## Sources

*[Same as original document]*
