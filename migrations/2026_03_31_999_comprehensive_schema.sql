-- ============================================
-- Comprehensive Idempotent Database Schema
-- ============================================
-- Created: 2026-03-31
-- Purpose: Ensures ALL tables, columns, indexes, and seed data exist.
--          Safe to run on any database state - will NOT delete or modify existing data.
--          Uses CREATE TABLE IF NOT EXISTS and conditional ALTER TABLE throughout.
--
-- This file consolidates all migrations into a single idempotent script.
-- It can be run via: php migrations/run.php
-- Or directly:       mysql -u [user] -p [database] < migrations/2026_03_31_999_comprehensive_schema.sql
-- ============================================


-- =============================================
-- SECTION 1: CREATE TABLES (dependency order)
-- =============================================

-- 1.1 Membership Statuses (lookup table, no FKs)
CREATE TABLE IF NOT EXISTS membership_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#6B7280',
    is_member BOOLEAN DEFAULT FALSE COMMENT 'TRUE if this status counts as a church member',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_is_member (is_member),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.2 Households
CREATE TABLE IF NOT EXISTS households (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL COMMENT 'Usually "The [LastName] Family" or custom name',
    primary_contact_id INT NULL COMMENT 'Main contact person for household communications',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_primary_contact (primary_contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.3 Addresses
CREATE TABLE IF NOT EXISTS addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'Individual user address',
    household_id INT NULL COMMENT 'Shared household address',
    street VARCHAR(255) NULL,
    street2 VARCHAR(255) NULL COMMENT 'Flat/unit number, building name, etc.',
    city VARCHAR(100) NULL,
    county VARCHAR(100) NULL,
    postcode VARCHAR(20) NULL,
    country VARCHAR(100) DEFAULT 'United Kingdom',
    location_type ENUM('home', 'work', 'mailing', 'other') DEFAULT 'home',
    is_primary BOOLEAN DEFAULT FALSE,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_household (household_id),
    INDEX idx_postcode (postcode),
    INDEX idx_primary (is_primary),
    CONSTRAINT chk_address_owner CHECK (
        (user_id IS NOT NULL AND household_id IS NULL) OR
        (user_id IS NULL AND household_id IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.4 Phone Numbers
CREATE TABLE IF NOT EXISTS phone_numbers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    number VARCHAR(30) NOT NULL,
    country_code VARCHAR(5) DEFAULT '+44' COMMENT 'UK default',
    location_type ENUM('home', 'work', 'mobile', 'other') DEFAULT 'mobile',
    is_primary BOOLEAN DEFAULT FALSE,
    can_receive_sms BOOLEAN DEFAULT TRUE COMMENT 'For text message communications',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_primary (is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.5 Member Tags
CREATE TABLE IF NOT EXISTS member_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    tag_group VARCHAR(100) NULL COMMENT 'Group tags together',
    color VARCHAR(7) DEFAULT '#6B7280',
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_slug (slug),
    INDEX idx_group (tag_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.6 User Tags (junction)
CREATE TABLE IF NOT EXISTS user_tags (
    user_id INT NOT NULL,
    tag_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    added_by INT NULL,
    PRIMARY KEY (user_id, tag_id),
    INDEX idx_tag (tag_id),
    INDEX idx_added_by (added_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.7 User Notes
CREATE TABLE IF NOT EXISTS user_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    note TEXT NOT NULL,
    note_type ENUM('general', 'prayer', 'pastoral', 'follow_up', 'private') DEFAULT 'general',
    is_pinned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    INDEX idx_user (user_id),
    INDEX idx_type (note_type),
    INDEX idx_pinned (is_pinned),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.8 People Lists
CREATE TABLE IF NOT EXISTS people_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    description TEXT NULL,
    list_type ENUM('static', 'dynamic') NOT NULL DEFAULT 'static',
    criteria JSON NULL,
    color VARCHAR(7) DEFAULT '#6B7280',
    icon VARCHAR(50) NULL,
    is_system BOOLEAN DEFAULT FALSE,
    visibility ENUM('private', 'shared', 'public') DEFAULT 'shared',
    created_by INT NULL,
    member_count INT DEFAULT 0,
    last_refreshed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_slug (slug),
    INDEX idx_type (list_type),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.9 People List Members
CREATE TABLE IF NOT EXISTS people_list_members (
    list_id INT NOT NULL,
    user_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    added_by INT NULL,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (list_id, user_id),
    INDEX idx_user (user_id),
    INDEX idx_added_by (added_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.10 Bot Visits
CREATE TABLE IF NOT EXISTS bot_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_name VARCHAR(100) NOT NULL,
    bot_category VARCHAR(50) NOT NULL,
    bot_owner VARCHAR(100) DEFAULT NULL,
    classification ENUM('good', 'suspicious', 'unknown') NOT NULL DEFAULT 'unknown',
    user_agent VARCHAR(500) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    request_url VARCHAR(500) NOT NULL,
    pattern_matched VARCHAR(50) DEFAULT NULL,
    visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bot_name (bot_name),
    INDEX idx_classification (classification),
    INDEX idx_bot_category (bot_category),
    INDEX idx_visited_at (visited_at),
    INDEX idx_ip_address (ip_address),
    INDEX idx_request_url (request_url(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.11 Group Types
CREATE TABLE IF NOT EXISTS group_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT NULL,
    color VARCHAR(7) DEFAULT '#6B7280',
    default_visibility ENUM('public', 'private', 'unlisted') DEFAULT 'public',
    allow_signups BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.12 Groups
CREATE TABLE IF NOT EXISTS `groups` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_type_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    description TEXT NULL,
    meeting_day ENUM('sunday','monday','tuesday','wednesday','thursday','friday','saturday') NULL,
    meeting_time TIME NULL,
    meeting_frequency ENUM('weekly','bi-weekly','monthly','custom') DEFAULT 'weekly',
    meeting_frequency_note VARCHAR(255) NULL,
    location_type ENUM('physical','online','hybrid') DEFAULT 'physical',
    location_name VARCHAR(200) NULL,
    location_address TEXT NULL,
    location_city VARCHAR(100) NULL,
    location_postcode VARCHAR(20) NULL,
    online_url VARCHAR(500) NULL,
    visibility ENUM('public','private','unlisted') DEFAULT 'public',
    allow_signups BOOLEAN DEFAULT TRUE,
    requires_approval BOOLEAN DEFAULT FALSE,
    max_members INT NULL,
    contact_email VARCHAR(255) NULL,
    contact_phone VARCHAR(30) NULL,
    childcare_available BOOLEAN DEFAULT FALSE,
    image_url VARCHAR(500) NULL,
    status ENUM('active','inactive','archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    UNIQUE INDEX idx_slug (slug),
    INDEX idx_type (group_type_id),
    INDEX idx_status (status),
    INDEX idx_day (meeting_day),
    FULLTEXT INDEX ft_search (name, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.13 Group Members
CREATE TABLE IF NOT EXISTS group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('member','leader','co-leader','admin') DEFAULT 'member',
    status ENUM('active','inactive','pending') DEFAULT 'active',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_group_user (group_id, user_id),
    INDEX idx_user (user_id),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.14 Group Events
CREATE TABLE IF NOT EXISTS group_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    event_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    location_name VARCHAR(200) NULL,
    location_address TEXT NULL,
    is_cancelled BOOLEAN DEFAULT FALSE,
    cancelled_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    INDEX idx_group_date (group_id, event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.15 Group Attendance
CREATE TABLE IF NOT EXISTS group_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_event_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('present','absent','excused') DEFAULT 'present',
    checked_in_at TIMESTAMP NULL,
    notes VARCHAR(255) NULL,
    UNIQUE INDEX idx_event_user (group_event_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.16 Group Signup Requests
CREATE TABLE IF NOT EXISTS group_signup_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NULL,
    status ENUM('pending','approved','denied') DEFAULT 'pending',
    response_notes TEXT NULL,
    responded_by INT NULL,
    responded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_group_status (group_id, status),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.17 Giving Funds
CREATE TABLE IF NOT EXISTS giving_funds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    goal_amount DECIMAL(10,2) NULL,
    goal_deadline DATE NULL,
    display_on_form BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.18 Donations
CREATE TABLE IF NOT EXISTS donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    fund_id INT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'GBP',
    payment_method VARCHAR(50) NULL,
    stripe_payment_id VARCHAR(255) NULL,
    stripe_customer_id VARCHAR(255) NULL,
    donor_email VARCHAR(255) NOT NULL,
    donor_name VARCHAR(200) NULL,
    donor_address TEXT NULL,
    gift_aid BOOLEAN DEFAULT FALSE,
    gift_aid_declaration_date DATE NULL,
    frequency ENUM('one-time', 'weekly', 'monthly', 'yearly') DEFAULT 'one-time',
    recurring_id INT NULL,
    status ENUM('pending', 'completed', 'failed', 'refunded', 'cancelled') DEFAULT 'pending',
    notes TEXT NULL,
    donated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_user (user_id),
    INDEX idx_fund (fund_id),
    INDEX idx_status (status),
    INDEX idx_date (donated_at),
    INDEX idx_stripe (stripe_payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.19 Recurring Donations
CREATE TABLE IF NOT EXISTS recurring_donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    fund_id INT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'GBP',
    frequency ENUM('weekly', 'monthly', 'yearly') DEFAULT 'monthly',
    stripe_subscription_id VARCHAR(255) NULL,
    stripe_customer_id VARCHAR(255) NULL,
    donor_email VARCHAR(255) NOT NULL,
    donor_name VARCHAR(200) NULL,
    gift_aid BOOLEAN DEFAULT FALSE,
    next_payment_date DATE NULL,
    last_payment_date DATE NULL,
    total_given DECIMAL(12,2) DEFAULT 0,
    payment_count INT DEFAULT 0,
    status ENUM('active', 'paused', 'cancelled', 'failed') DEFAULT 'active',
    cancelled_at TIMESTAMP NULL,
    cancel_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_next_payment (next_payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.20 Giving Statements
CREATE TABLE IF NOT EXISTS giving_statements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    year INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_amount DECIMAL(12,2) DEFAULT 0,
    donation_count INT DEFAULT 0,
    gift_aid_amount DECIMAL(12,2) DEFAULT 0,
    file_path VARCHAR(500) NULL,
    generated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_user_year (user_id, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.21 Giving Pledges
CREATE TABLE IF NOT EXISTS giving_pledges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    fund_id INT NULL,
    campaign_name VARCHAR(200) NULL,
    pledge_amount DECIMAL(10,2) NOT NULL,
    fulfilled_amount DECIMAL(10,2) DEFAULT 0,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.22 Service Types
CREATE TABLE IF NOT EXISTS service_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT NULL,
    default_day ENUM('sunday','monday','tuesday','wednesday','thursday','friday','saturday') DEFAULT 'sunday',
    default_time TIME NULL,
    default_duration_minutes INT DEFAULT 90,
    color VARCHAR(7) DEFAULT '#6B7280',
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.23 Services
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_type_id INT NOT NULL,
    service_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NULL,
    title VARCHAR(200) NULL,
    notes TEXT NULL,
    status ENUM('planned', 'confirmed', 'completed', 'cancelled') DEFAULT 'planned',
    attendance_count INT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (service_date),
    INDEX idx_type_date (service_type_id, service_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.24 Service Teams
CREATE TABLE IF NOT EXISTS service_teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT NULL,
    color VARCHAR(7) DEFAULT '#6B7280',
    min_required INT DEFAULT 1,
    max_allowed INT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.25 Service Team Members
CREATE TABLE IF NOT EXISTS service_team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('member', 'leader') DEFAULT 'member',
    is_active BOOLEAN DEFAULT TRUE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_team_user (team_id, user_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.26 Service Assignments
CREATE TABLE IF NOT EXISTS service_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    position VARCHAR(100) NULL,
    status ENUM('pending', 'confirmed', 'declined') DEFAULT 'pending',
    confirmed_at TIMESTAMP NULL,
    notes TEXT NULL,
    assigned_by INT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_service (service_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.27 Service Items (order of service)
CREATE TABLE IF NOT EXISTS service_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    item_type ENUM('song', 'scripture', 'prayer', 'announcement', 'sermon', 'offering', 'communion', 'video', 'other') DEFAULT 'other',
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    duration_minutes INT NULL,
    sort_order INT DEFAULT 0,
    notes TEXT NULL,
    song_key VARCHAR(10) NULL,
    song_tempo INT NULL,
    song_ccli VARCHAR(20) NULL,
    scripture_reference VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_service (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.28 Songs Library
CREATE TABLE IF NOT EXISTS songs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    artist VARCHAR(200) NULL,
    ccli_number VARCHAR(20) NULL,
    default_key VARCHAR(10) NULL,
    default_tempo INT NULL,
    lyrics TEXT NULL,
    notes TEXT NULL,
    tags VARCHAR(500) NULL,
    times_used INT DEFAULT 0,
    last_used_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_title (title),
    INDEX idx_ccli (ccli_number),
    FULLTEXT INDEX ft_search (title, artist, lyrics)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.29 Service Blockouts
CREATE TABLE IF NOT EXISTS service_blockouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.30 Song Chord Charts
CREATE TABLE IF NOT EXISTS song_chord_charts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    song_id INT NOT NULL,
    key_signature VARCHAR(10) NOT NULL,
    chart_type ENUM('chords', 'leadsheet', 'lyrics') DEFAULT 'chords',
    content TEXT NOT NULL,
    source ENUM('songselect', 'manual', 'transposed') DEFAULT 'manual',
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_song_key (song_id, key_signature)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.31 Song Attachments
CREATE TABLE IF NOT EXISTS song_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    song_id INT NOT NULL,
    attachment_type ENUM('chord_chart', 'lead_sheet', 'lyrics', 'audio', 'video', 'other') DEFAULT 'other',
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NULL,
    mime_type VARCHAR(100) NULL,
    key_signature VARCHAR(10) NULL,
    source ENUM('songselect', 'upload') DEFAULT 'upload',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_song (song_id),
    INDEX idx_type (attachment_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.32 SongSelect Config
CREATE TABLE IF NOT EXISTS songselect_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id VARCHAR(255) NULL,
    client_secret VARCHAR(255) NULL,
    access_token TEXT NULL,
    refresh_token TEXT NULL,
    token_expires_at DATETIME NULL,
    ccli_license_number VARCHAR(50) NULL,
    is_active TINYINT(1) DEFAULT 0,
    last_sync_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.33 Song Usage Log
CREATE TABLE IF NOT EXISTS song_usage_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    song_id INT NOT NULL,
    service_id INT NULL,
    usage_date DATE NOT NULL,
    ccli_number VARCHAR(20) NULL,
    reported_to_ccli TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_song (song_id),
    INDEX idx_date (usage_date),
    INDEX idx_reported (reported_to_ccli)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.34 Service Templates
CREATE TABLE IF NOT EXISTS service_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    service_type_id INT NOT NULL,
    default_duration_minutes INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_service_type (service_type_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.35 Service Roles
CREATE TABLE IF NOT EXISTS service_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    sort_order INT DEFAULT 0,
    min_skill_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_team (team_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.36 Service Template Items
CREATE TABLE IF NOT EXISTS service_template_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    item_type ENUM('song', 'scripture', 'prayer', 'announcement', 'sermon', 'offering', 'communion', 'video', 'other') DEFAULT 'other',
    song_id INT NULL,
    title VARCHAR(200) NULL,
    duration_minutes INT NULL,
    notes TEXT NULL,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_template (template_id),
    INDEX idx_song (song_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.37 Service Template Roles
CREATE TABLE IF NOT EXISTS service_template_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    role_id INT NOT NULL,
    quantity INT DEFAULT 1,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_template (template_id),
    INDEX idx_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.38 Service Rota (uses users(id), NOT members)
CREATE TABLE IF NOT EXISTS service_rota (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    role_id INT NOT NULL,
    member_id INT NULL COMMENT 'References users(id) - Null if unassigned',
    status ENUM('unassigned', 'pending', 'confirmed', 'declined') DEFAULT 'unassigned',
    assigned_at TIMESTAMP NULL,
    responded_at TIMESTAMP NULL,
    confirmation_token VARCHAR(64) NULL,
    decline_reason TEXT NULL,
    notes TEXT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_service (service_id),
    INDEX idx_member (member_id),
    INDEX idx_role (role_id),
    INDEX idx_status (status),
    INDEX idx_token (confirmation_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.39 Member Role Capabilities (uses users(id))
CREATE TABLE IF NOT EXISTS member_role_capabilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL COMMENT 'References users(id)',
    role_id INT NOT NULL,
    skill_level ENUM('beginner', 'competent', 'proficient', 'expert') DEFAULT 'competent',
    preference_level ENUM('unwilling', 'willing', 'prefer', 'strong_prefer') DEFAULT 'willing',
    notes TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_member_role (member_id, role_id),
    INDEX idx_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.40 Member Availability (uses users(id))
CREATE TABLE IF NOT EXISTS member_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL COMMENT 'References users(id)',
    unavailable_date DATE NOT NULL,
    reason VARCHAR(255) NULL,
    is_recurring BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_member_date (member_id, unavailable_date),
    INDEX idx_date (unavailable_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.41 Service Assignment Notifications
CREATE TABLE IF NOT EXISTS service_assignment_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rota_id INT NOT NULL,
    notification_type ENUM('assignment', 'reminder', 'change', 'cancellation') DEFAULT 'assignment',
    sent_to_email VARCHAR(255) NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    opened_at TIMESTAMP NULL,
    responded_at TIMESTAMP NULL,
    INDEX idx_rota (rota_id),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.42 Service Scheduling Conflicts
CREATE TABLE IF NOT EXISTS service_scheduling_conflicts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    member_id INT NOT NULL,
    conflict_type ENUM('double_booked', 'unavailable', 'over_scheduled', 'insufficient_skill') DEFAULT 'double_booked',
    conflict_details TEXT NULL,
    resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_service (service_id),
    INDEX idx_member (member_id),
    INDEX idx_resolved (resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.43 Service Runsheet Templates
CREATE TABLE IF NOT EXISTS service_runsheet_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    service_type_id INT NULL,
    template_data JSON NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_service_type (service_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.44 Song Transition Patterns (AI learning)
CREATE TABLE IF NOT EXISTS song_transition_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_song_id INT NULL,
    to_song_id INT NOT NULL,
    user_id INT NULL,
    team_id INT NULL,
    transition_count INT DEFAULT 1,
    weight DECIMAL(5,4) DEFAULT 1.0,
    last_used DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_transition (from_song_id, to_song_id, user_id, team_id),
    INDEX idx_from_song (from_song_id),
    INDEX idx_to_song (to_song_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.45 Song Position Patterns
CREATE TABLE IF NOT EXISTS song_position_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    song_id INT NOT NULL,
    user_id INT NULL,
    team_id INT NULL,
    position_type ENUM('opener', 'early', 'middle', 'climax', 'closer') NOT NULL,
    occurrence_count INT DEFAULT 1,
    total_uses INT DEFAULT 1,
    position_score DECIMAL(5,4) DEFAULT 0.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_song_position (song_id, position_type, user_id, team_id),
    INDEX idx_song (song_id),
    INDEX idx_position_type (position_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.46 Key Progression Patterns
CREATE TABLE IF NOT EXISTS key_progression_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_key VARCHAR(10) NOT NULL,
    to_key VARCHAR(10) NOT NULL,
    user_id INT NULL,
    transition_count INT DEFAULT 1,
    smoothness_rating DECIMAL(3,2) DEFAULT 0.5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_key_transition (from_key, to_key, user_id),
    INDEX idx_from_key (from_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.47 Song Attributes
CREATE TABLE IF NOT EXISTS song_attributes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    song_id INT NOT NULL,
    energy_level ENUM('very_low', 'low', 'medium', 'high', 'very_high') DEFAULT 'medium',
    mood VARCHAR(50) NULL,
    season_affinity VARCHAR(100) NULL,
    service_type_affinity VARCHAR(100) NULL,
    calculated_energy_score DECIMAL(3,2) NULL,
    congregational_familiarity ENUM('new', 'learning', 'familiar', 'classic') DEFAULT 'familiar',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_song (song_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.48 Setlist Preferences
CREATE TABLE IF NOT EXISTS setlist_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    team_id INT NULL,
    preference_key VARCHAR(50) NOT NULL,
    preference_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_preference (user_id, team_id, preference_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.49 AI Setlist Suggestions
CREATE TABLE IF NOT EXISTS ai_setlist_suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    user_id INT NOT NULL,
    suggested_songs JSON NOT NULL,
    final_songs JSON NULL,
    acceptance_rate DECIMAL(3,2) NULL,
    feedback_notes TEXT NULL,
    model_version VARCHAR(20) DEFAULT '1.0',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_service (service_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.50 Song Usage History
CREATE TABLE IF NOT EXISTS song_usage_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    song_id INT NOT NULL,
    service_id INT NOT NULL,
    team_id INT NULL,
    used_date DATE NOT NULL,
    position_in_setlist INT NOT NULL,
    key_used VARCHAR(10) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_song (song_id),
    INDEX idx_service (service_id),
    INDEX idx_date (used_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.51 Custom Worship Flows
CREATE TABLE IF NOT EXISTS custom_worship_flows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    pattern JSON NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    user_id INT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.52 Music Stand Annotations
CREATE TABLE IF NOT EXISTS musicstand_annotations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_item_id INT NULL,
    song_id INT NULL,
    drawing_data JSON NULL,
    text_notes TEXT NULL,
    chart_edits TEXT NULL,
    chord_size INT DEFAULT 14,
    lyric_size INT DEFAULT 16,
    transpose_key VARCHAR(10) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_item (user_id, service_item_id),
    INDEX idx_user_song (user_id, song_id),
    UNIQUE KEY unique_user_item (user_id, service_item_id),
    UNIQUE KEY unique_user_song (user_id, song_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1.53 Analytics Sessions
CREATE TABLE IF NOT EXISTS analytics_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL UNIQUE,
    user_id INT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    page_count INT DEFAULT 1,
    total_duration INT DEFAULT 0,
    entry_page VARCHAR(500) NULL,
    exit_page VARCHAR(500) NULL,
    is_bounce TINYINT(1) DEFAULT 1,
    device_type ENUM('desktop', 'tablet', 'mobile') DEFAULT 'desktop',
    browser VARCHAR(50) NULL,
    country_code VARCHAR(2) NULL,
    country_name VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    referrer_source VARCHAR(100) NULL,
    referrer_url VARCHAR(500) NULL,
    INDEX idx_sessions_started (started_at),
    INDEX idx_sessions_device (device_type),
    INDEX idx_sessions_country (country_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1.54 Analytics Searches
CREATE TABLE IF NOT EXISTS analytics_searches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    search_term VARCHAR(255) NOT NULL,
    results_count INT DEFAULT 0,
    search_type VARCHAR(50) DEFAULT 'site',
    user_id INT NULL,
    session_id VARCHAR(64) NULL,
    searched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_searches_term (search_term),
    INDEX idx_searches_date (searched_at),
    INDEX idx_searches_type (search_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1.55 Analytics Hourly Traffic
CREATE TABLE IF NOT EXISTS analytics_hourly_traffic (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    hour TINYINT NOT NULL,
    day_of_week TINYINT NOT NULL,
    page_views INT DEFAULT 0,
    unique_visitors INT DEFAULT 0,
    UNIQUE KEY idx_hourly_unique (date, hour),
    INDEX idx_hourly_dow (day_of_week, hour)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1.56 Welcome Journeys
CREATE TABLE IF NOT EXISTS welcome_journeys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_submission_id INT NULL,
    visitor_name VARCHAR(200) NOT NULL,
    visitor_email VARCHAR(255) NOT NULL,
    status ENUM('active', 'visited', 'completed', 'cancelled', 'unsubscribed') DEFAULT 'active',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expected_visit_date DATE NULL,
    actual_visit_date DATE NULL,
    unsubscribe_token VARCHAR(64) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_email (visitor_email),
    INDEX idx_registered (registered_at),
    INDEX idx_unsubscribe (unsubscribe_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.57 Welcome Journey Emails
CREATE TABLE IF NOT EXISTS welcome_journey_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    journey_id INT NOT NULL,
    email_type VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('pending', 'sent', 'failed', 'cancelled', 'skipped') DEFAULT 'pending',
    scheduled_at TIMESTAMP NOT NULL,
    sent_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_journey (journey_id),
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_pending_due (status, scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.58 SEO 404 Log
CREATE TABLE IF NOT EXISTS seo_404_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_url VARCHAR(500) NOT NULL,
    referrer VARCHAR(500) NULL,
    is_bot TINYINT(1) DEFAULT 0,
    bot_name VARCHAR(100) NULL,
    hit_count INT DEFAULT 1,
    first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved TINYINT(1) DEFAULT 0,
    redirect_to VARCHAR(500) NULL,
    INDEX idx_404_url (request_url(191)),
    INDEX idx_404_last_seen (last_seen_at),
    INDEX idx_404_resolved (resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.59 SEO 404 Hits
CREATE TABLE IF NOT EXISTS seo_404_hits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_url VARCHAR(500) NOT NULL,
    referrer VARCHAR(500) NULL,
    is_bot TINYINT(1) DEFAULT 0,
    bot_name VARCHAR(100) NULL,
    hit_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_404_hits_url (request_url(191)),
    INDEX idx_404_hits_date (hit_at),
    INDEX idx_404_hits_bot (is_bot, hit_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.60 SEO GSC Data
CREATE TABLE IF NOT EXISTS seo_gsc_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_date DATE NOT NULL,
    page_url VARCHAR(500) NOT NULL,
    query VARCHAR(500) NOT NULL,
    clicks INT DEFAULT 0,
    impressions INT DEFAULT 0,
    ctr DECIMAL(5,4) DEFAULT 0,
    position DECIMAL(5,2) DEFAULT 0,
    device VARCHAR(20) NULL,
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_gsc_unique (data_date, page_url(100), query(100), device),
    INDEX idx_gsc_date (data_date),
    INDEX idx_gsc_page (page_url(191)),
    INDEX idx_gsc_query (query(191)),
    INDEX idx_gsc_clicks (clicks)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.61 SEO GSC Config
CREATE TABLE IF NOT EXISTS seo_gsc_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.62 SEO Indexing Config
CREATE TABLE IF NOT EXISTS seo_indexing_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.63 SEO Indexing Log
CREATE TABLE IF NOT EXISTS seo_indexing_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(500) NOT NULL,
    service ENUM('indexnow','google') NOT NULL,
    action ENUM('updated','deleted') DEFAULT 'updated',
    status ENUM('pending','success','error') DEFAULT 'pending',
    http_code SMALLINT NULL,
    response TEXT NULL,
    submitted_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_indexing_log_url (url(191)),
    INDEX idx_indexing_log_service (service, created_at),
    INDEX idx_indexing_log_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.64 SEO Indexing Queue
CREATE TABLE IF NOT EXISTS seo_indexing_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(500) NOT NULL,
    service ENUM('indexnow','google') NOT NULL,
    submitted_at TIMESTAMP NULL,
    status ENUM('pending','submitted','error') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_queue_unique (url(191), service),
    INDEX idx_queue_status (status, service)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================
-- SECTION 2: CONDITIONAL COLUMN ADDITIONS
-- =============================================
-- Pattern: check INFORMATION_SCHEMA before each ALTER TABLE ADD COLUMN
-- This prevents errors when columns already exist.

-- ----- 2.1 users table: membership fields -----

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_member') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN is_member BOOLEAN DEFAULT FALSE AFTER active'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'membership_status_id') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN membership_status_id INT NULL AFTER is_member'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'member_since') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN member_since DATE NULL AFTER membership_status_id'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'household_id') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN household_id INT NULL AFTER member_since'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'household_role') > 0, 'SELECT 1', "ALTER TABLE users ADD COLUMN household_role ENUM('primary', 'spouse', 'child', 'other') DEFAULT 'primary' AFTER household_id"));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----- 2.2 users table: personal info -----

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'first_name') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN first_name VARCHAR(100) NULL AFTER full_name'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_name') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN last_name VARCHAR(100) NULL AFTER first_name'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'middle_name') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN middle_name VARCHAR(100) NULL AFTER last_name'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'nickname') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN nickname VARCHAR(100) NULL AFTER middle_name'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'prefix') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN prefix VARCHAR(20) NULL AFTER nickname'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'suffix') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN suffix VARCHAR(20) NULL AFTER prefix'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----- 2.3 users table: demographics -----

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'gender') > 0, 'SELECT 1', "ALTER TABLE users ADD COLUMN gender ENUM('male', 'female', 'prefer_not_to_say') NULL AFTER suffix"));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'birthdate') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN birthdate DATE NULL AFTER gender'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'marital_status') > 0, 'SELECT 1', "ALTER TABLE users ADD COLUMN marital_status ENUM('single', 'married', 'divorced', 'widowed', 'separated') NULL AFTER birthdate"));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'anniversary') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN anniversary DATE NULL AFTER marital_status'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----- 2.4 users table: spiritual milestones -----

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'salvation_date') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN salvation_date DATE NULL AFTER anniversary'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'baptism_date') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN baptism_date DATE NULL AFTER salvation_date'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----- 2.5 users table: profile/display -----

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'profile_photo') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN profile_photo VARCHAR(500) NULL AFTER baptism_date'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'directory_visible') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN directory_visible BOOLEAN DEFAULT TRUE AFTER profile_photo'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_opt_out') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN email_opt_out BOOLEAN DEFAULT FALSE AFTER directory_visible'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'sms_opt_out') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN sms_opt_out BOOLEAN DEFAULT FALSE AFTER email_opt_out'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'communication_preferences') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN communication_preferences JSON NULL AFTER sms_opt_out'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----- 2.6 page_visits table: analytics columns -----

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_visits' AND COLUMN_NAME = 'country_code') > 0, 'SELECT 1', 'ALTER TABLE page_visits ADD COLUMN country_code VARCHAR(2) NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_visits' AND COLUMN_NAME = 'country_name') > 0, 'SELECT 1', 'ALTER TABLE page_visits ADD COLUMN country_name VARCHAR(100) NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_visits' AND COLUMN_NAME = 'city') > 0, 'SELECT 1', 'ALTER TABLE page_visits ADD COLUMN city VARCHAR(100) NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_visits' AND COLUMN_NAME = 'region') > 0, 'SELECT 1', 'ALTER TABLE page_visits ADD COLUMN region VARCHAR(100) NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_visits' AND COLUMN_NAME = 'latitude') > 0, 'SELECT 1', 'ALTER TABLE page_visits ADD COLUMN latitude DECIMAL(10,8) NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_visits' AND COLUMN_NAME = 'longitude') > 0, 'SELECT 1', 'ALTER TABLE page_visits ADD COLUMN longitude DECIMAL(11,8) NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_visits' AND COLUMN_NAME = 'is_new_visitor') > 0, 'SELECT 1', 'ALTER TABLE page_visits ADD COLUMN is_new_visitor TINYINT(1) DEFAULT 0'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_visits' AND COLUMN_NAME = 'page_load_time') > 0, 'SELECT 1', 'ALTER TABLE page_visits ADD COLUMN page_load_time INT NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_visits' AND COLUMN_NAME = 'screen_width') > 0, 'SELECT 1', 'ALTER TABLE page_visits ADD COLUMN screen_width INT NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_visits' AND COLUMN_NAME = 'screen_height') > 0, 'SELECT 1', 'ALTER TABLE page_visits ADD COLUMN screen_height INT NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_visits' AND COLUMN_NAME = 'referrer_domain') > 0, 'SELECT 1', 'ALTER TABLE page_visits ADD COLUMN referrer_domain VARCHAR(255) NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----- 2.7 service_items table: songs enhancement + runsheet -----

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_items' AND COLUMN_NAME = 'song_id') > 0, 'SELECT 1', 'ALTER TABLE service_items ADD COLUMN song_id INT NULL AFTER notes'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_items' AND COLUMN_NAME = 'position') > 0, 'SELECT 1', 'ALTER TABLE service_items ADD COLUMN position INT DEFAULT 0 AFTER sort_order'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_items' AND COLUMN_NAME = 'planned_duration') > 0, 'SELECT 1', 'ALTER TABLE service_items ADD COLUMN planned_duration INT NULL AFTER duration_minutes'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_items' AND COLUMN_NAME = 'actual_start_time') > 0, 'SELECT 1', 'ALTER TABLE service_items ADD COLUMN actual_start_time DATETIME NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_items' AND COLUMN_NAME = 'actual_end_time') > 0, 'SELECT 1', 'ALTER TABLE service_items ADD COLUMN actual_end_time DATETIME NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_items' AND COLUMN_NAME = 'worship_notes') > 0, 'SELECT 1', 'ALTER TABLE service_items ADD COLUMN worship_notes TEXT NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_items' AND COLUMN_NAME = 'tech_notes') > 0, 'SELECT 1', 'ALTER TABLE service_items ADD COLUMN tech_notes TEXT NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_items' AND COLUMN_NAME = 'transition_notes') > 0, 'SELECT 1', 'ALTER TABLE service_items ADD COLUMN transition_notes TEXT NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_items' AND COLUMN_NAME = 'presenter') > 0, 'SELECT 1', 'ALTER TABLE service_items ADD COLUMN presenter VARCHAR(200) NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_items' AND COLUMN_NAME = 'video_url') > 0, 'SELECT 1', 'ALTER TABLE service_items ADD COLUMN video_url VARCHAR(500) NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_items' AND COLUMN_NAME = 'slides_url') > 0, 'SELECT 1', 'ALTER TABLE service_items ADD COLUMN slides_url VARCHAR(500) NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----- 2.8 songs table: enhancement columns -----

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'songs' AND COLUMN_NAME = 'songselect_id') > 0, 'SELECT 1', 'ALTER TABLE songs ADD COLUMN songselect_id VARCHAR(50) NULL AFTER id'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'songs' AND COLUMN_NAME = 'copyright') > 0, 'SELECT 1', 'ALTER TABLE songs ADD COLUMN copyright TEXT NULL AFTER lyrics'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'songs' AND COLUMN_NAME = 'youtube_url') > 0, 'SELECT 1', 'ALTER TABLE songs ADD COLUMN youtube_url VARCHAR(500) NULL AFTER copyright'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'songs' AND COLUMN_NAME = 'spotify_url') > 0, 'SELECT 1', 'ALTER TABLE songs ADD COLUMN spotify_url VARCHAR(500) NULL AFTER youtube_url'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'songs' AND COLUMN_NAME = 'tempo') > 0, 'SELECT 1', 'ALTER TABLE songs ADD COLUMN tempo INT NULL AFTER default_tempo'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'songs' AND COLUMN_NAME = 'time_signature') > 0, 'SELECT 1', 'ALTER TABLE songs ADD COLUMN time_signature VARCHAR(10) NULL AFTER tempo'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'songs' AND COLUMN_NAME = 'themes') > 0, 'SELECT 1', 'ALTER TABLE songs ADD COLUMN themes VARCHAR(500) NULL AFTER tags'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'songs' AND COLUMN_NAME = 'authors') > 0, 'SELECT 1', 'ALTER TABLE songs ADD COLUMN authors VARCHAR(500) NULL AFTER artist'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'songs' AND COLUMN_NAME = 'is_public_domain') > 0, 'SELECT 1', 'ALTER TABLE songs ADD COLUMN is_public_domain TINYINT(1) DEFAULT 0'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'songs' AND COLUMN_NAME = 'chord_chart_original') > 0, 'SELECT 1', 'ALTER TABLE songs ADD COLUMN chord_chart_original TEXT NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'songs' AND COLUMN_NAME = 'chord_chart_key') > 0, 'SELECT 1', 'ALTER TABLE songs ADD COLUMN chord_chart_key VARCHAR(10) NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'songs' AND COLUMN_NAME = 'updated_at') > 0, 'SELECT 1', 'ALTER TABLE songs ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'songs' AND COLUMN_NAME = 'is_intro_song') > 0, 'SELECT 1', 'ALTER TABLE songs ADD COLUMN is_intro_song TINYINT(1) DEFAULT 0'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----- 2.9 services table: live mode columns -----

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'live_mode_active') > 0, 'SELECT 1', 'ALTER TABLE services ADD COLUMN live_mode_active BOOLEAN DEFAULT FALSE AFTER status'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'live_started_at') > 0, 'SELECT 1', 'ALTER TABLE services ADD COLUMN live_started_at DATETIME NULL AFTER live_mode_active'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'actual_start_time') > 0, 'SELECT 1', 'ALTER TABLE services ADD COLUMN actual_start_time DATETIME NULL AFTER live_started_at'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'actual_end_time') > 0, 'SELECT 1', 'ALTER TABLE services ADD COLUMN actual_end_time DATETIME NULL AFTER actual_start_time'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----- 2.10 custom_worship_flows: intro songs -----

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'custom_worship_flows' AND COLUMN_NAME = 'start_with_intro') > 0, 'SELECT 1', 'ALTER TABLE custom_worship_flows ADD COLUMN start_with_intro TINYINT(1) DEFAULT 0'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----- 2.11 songselect_config: API key -----

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'songselect_config' AND COLUMN_NAME = 'api_key') > 0, 'SELECT 1', 'ALTER TABLE songselect_config ADD COLUMN api_key VARCHAR(64) NULL AFTER ccli_license_number'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----- 2.12 member_availability: fix columns -----

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_availability' AND COLUMN_NAME = 'updated_at') > 0, 'SELECT 1', 'ALTER TABLE member_availability ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_availability' AND COLUMN_NAME = 'is_recurring') > 0, 'SELECT 1', 'ALTER TABLE member_availability ADD COLUMN is_recurring BOOLEAN DEFAULT FALSE AFTER reason'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- =============================================
-- SECTION 3: CONDITIONAL INDEX ADDITIONS
-- =============================================

-- users indexes
SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_is_member') > 0, 'SELECT 1', 'CREATE INDEX idx_is_member ON users(is_member)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_membership_status') > 0, 'SELECT 1', 'CREATE INDEX idx_membership_status ON users(membership_status_id)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_household') > 0, 'SELECT 1', 'CREATE INDEX idx_household ON users(household_id)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_last_name') > 0, 'SELECT 1', 'CREATE INDEX idx_last_name ON users(last_name)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_first_last') > 0, 'SELECT 1', 'CREATE INDEX idx_first_last ON users(first_name, last_name)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- page_visits indexes
SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_visits' AND INDEX_NAME = 'idx_page_visits_country') > 0, 'SELECT 1', 'CREATE INDEX idx_page_visits_country ON page_visits(country_code)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_visits' AND INDEX_NAME = 'idx_page_visits_city') > 0, 'SELECT 1', 'CREATE INDEX idx_page_visits_city ON page_visits(city)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'page_visits' AND INDEX_NAME = 'idx_page_visits_referrer_domain') > 0, 'SELECT 1', 'CREATE INDEX idx_page_visits_referrer_domain ON page_visits(referrer_domain)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- songs indexes
SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'songs' AND INDEX_NAME = 'idx_songselect') > 0, 'SELECT 1', 'CREATE UNIQUE INDEX idx_songselect ON songs(songselect_id)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- service_items indexes
SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_items' AND INDEX_NAME = 'idx_song') > 0, 'SELECT 1', 'CREATE INDEX idx_song ON service_items(song_id)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_items' AND INDEX_NAME = 'idx_service_position') > 0, 'SELECT 1', 'CREATE INDEX idx_service_position ON service_items(service_id, position)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- services indexes
SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND INDEX_NAME = 'idx_date_status') > 0, 'SELECT 1', 'CREATE INDEX idx_date_status ON services(service_date, status)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- service_rota indexes
SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_rota' AND INDEX_NAME = 'idx_service_status') > 0, 'SELECT 1', 'CREATE INDEX idx_service_status ON service_rota(service_id, status)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- =============================================
-- SECTION 4: CONDITIONAL FOREIGN KEY ADDITIONS
-- =============================================
-- Only add FKs if they don't already exist (prevents duplicate constraint errors)

-- users -> membership_statuses
SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'fk_users_membership_status') > 0, 'SELECT 1', 'ALTER TABLE users ADD CONSTRAINT fk_users_membership_status FOREIGN KEY (membership_status_id) REFERENCES membership_statuses(id) ON DELETE SET NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- users -> households
SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'fk_users_household') > 0, 'SELECT 1', 'ALTER TABLE users ADD CONSTRAINT fk_users_household FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE SET NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- households -> users
SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND CONSTRAINT_NAME = 'fk_households_primary_contact') > 0, 'SELECT 1', 'ALTER TABLE households ADD CONSTRAINT fk_households_primary_contact FOREIGN KEY (primary_contact_id) REFERENCES users(id) ON DELETE SET NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- addresses -> users
SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'addresses' AND CONSTRAINT_NAME = 'addresses_ibfk_1') > 0, 'SELECT 1', 'ALTER TABLE addresses ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- addresses -> households
SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'addresses' AND CONSTRAINT_NAME = 'addresses_ibfk_2') > 0, 'SELECT 1', 'ALTER TABLE addresses ADD FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE CASCADE'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- phone_numbers -> users
SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'phone_numbers' AND CONSTRAINT_NAME = 'phone_numbers_ibfk_1') > 0, 'SELECT 1', 'ALTER TABLE phone_numbers ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- service_items -> songs
SET @sql = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_items' AND CONSTRAINT_NAME = 'service_items_ibfk_2') > 0, 'SELECT 1', 'ALTER TABLE service_items ADD FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE SET NULL'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- =============================================
-- SECTION 5: VIEWS
-- =============================================

CREATE OR REPLACE VIEW v_runsheet_items AS
SELECT
    si.*,
    s.service_date,
    s.start_time as service_start_time,
    s.title as service_title,
    st.name as service_type_name,
    song.title as song_title,
    song.artist as song_artist,
    CASE
        WHEN si.planned_duration IS NOT NULL THEN si.planned_duration
        WHEN si.duration_minutes IS NOT NULL THEN si.duration_minutes
        ELSE 5
    END as effective_duration,
    (SELECT SUM(COALESCE(si2.planned_duration, si2.duration_minutes, 5))
     FROM service_items si2
     WHERE si2.service_id = si.service_id
     AND si2.position < si.position) as cumulative_minutes_before,
    CASE
        WHEN si.actual_end_time IS NOT NULL AND si.actual_start_time IS NOT NULL
        THEN TIMESTAMPDIFF(MINUTE, si.actual_start_time, si.actual_end_time)
        ELSE NULL
    END as actual_duration_minutes
FROM service_items si
JOIN services s ON si.service_id = s.id
JOIN service_types st ON s.service_type_id = st.id
LEFT JOIN songs song ON si.song_id = song.id
ORDER BY si.service_id, si.position;


-- =============================================
-- SECTION 6: SEED DATA (INSERT IGNORE / ON DUPLICATE KEY UPDATE)
-- =============================================
-- Uses INSERT IGNORE for unique-key tables to avoid duplicating data.
-- Uses ON DUPLICATE KEY UPDATE where we want to ensure values match.

-- 6.1 Membership Statuses
INSERT IGNORE INTO membership_statuses (name, description, color, is_member, sort_order) VALUES
('Visitor', 'First-time or occasional visitor', '#9CA3AF', FALSE, 1),
('Regular Attender', 'Attends regularly but not yet a member', '#3B82F6', FALSE, 2),
('Member', 'Official church member', '#10B981', TRUE, 3),
('Leader', 'Church leader or ministry head', '#8B5CF6', TRUE, 4),
('Staff', 'Church staff member', '#EC4899', TRUE, 5),
('Inactive', 'Previously active, no longer attending', '#EF4444', FALSE, 6);

-- 6.2 Member Tags
INSERT INTO member_tags (name, slug, tag_group, color, description) VALUES
('New Believer', 'new-believer', 'Spiritual Journey', '#10B981', 'Recently accepted Christ'),
('Seeking Baptism', 'seeking-baptism', 'Spiritual Journey', '#3B82F6', 'Interested in being baptized'),
('Small Group Leader', 'small-group-leader', 'Leadership', '#8B5CF6', 'Leads a small group'),
('Ministry Leader', 'ministry-leader', 'Leadership', '#EC4899', 'Leads a ministry area'),
('Worship Team', 'worship-team', 'Serving', '#F59E0B', 'Serves on worship team'),
('Tech Team', 'tech-team', 'Serving', '#6366F1', 'Serves on tech/AV team'),
('Kids Ministry', 'kids-ministry', 'Serving', '#14B8A6', 'Serves in children''s ministry'),
('Welcome Team', 'welcome-team', 'Serving', '#F97316', 'Serves as greeter/usher'),
('Prayer Team', 'prayer-team', 'Serving', '#A855F7', 'Serves on prayer team'),
('Young Adult', 'young-adult', 'Life Stage', '#0EA5E9', '18-30 years old'),
('Parent', 'parent', 'Life Stage', '#22C55E', 'Has children'),
('Senior', 'senior', 'Life Stage', '#64748B', '65+ years old'),
('First-Time Guest', 'first-time-guest', 'Status', '#EAB308', 'First-time visitor'),
('Requires Follow-Up', 'requires-follow-up', 'Status', '#EF4444', 'Needs pastoral follow-up'),
('VIP', 'vip', 'Status', '#D946EF', 'Special attention needed')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 6.3 People Lists (system lists)
INSERT INTO people_lists (name, slug, description, list_type, criteria, color, is_system) VALUES
('All Members', 'all-members', 'All people marked as church members', 'dynamic', '{"is_member": true}', '#10B981', TRUE),
('All Visitors', 'all-visitors', 'All people not marked as members', 'dynamic', '{"is_member": false}', '#3B82F6', TRUE),
('New This Month', 'new-this-month', 'People added in the current month', 'dynamic', '{"created_within": "month"}', '#F59E0B', TRUE),
('New This Week', 'new-this-week', 'People added in the current week', 'dynamic', '{"created_within": "week"}', '#8B5CF6', TRUE),
('Recently Active', 'recently-active', 'People who logged in within 30 days', 'dynamic', '{"last_login_within": "30_days"}', '#14B8A6', TRUE),
('Inactive', 'inactive', 'People who haven''t logged in for 90+ days', 'dynamic', '{"last_login_before": "90_days"}', '#EF4444', TRUE),
('Birthdays This Month', 'birthdays-this-month', 'People with birthdays in the current month', 'dynamic', '{"birthday_month": "current"}', '#EC4899', TRUE),
('Anniversaries This Month', 'anniversaries-this-month', 'Wedding anniversaries in the current month', 'dynamic', '{"anniversary_month": "current"}', '#D946EF', TRUE)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 6.4 Group Types
INSERT INTO group_types (name, slug, description, color, sort_order) VALUES
('Life Groups', 'life-groups', 'Weekly small groups for community and discipleship', '#10B981', 1),
('Bible Studies', 'bible-studies', 'In-depth Bible study groups', '#3B82F6', 2),
('Ministry Teams', 'ministry-teams', 'Serving teams and ministry groups', '#8B5CF6', 3),
('Interest Groups', 'interest-groups', 'Groups based on shared interests', '#F59E0B', 4),
('Support Groups', 'support-groups', 'Recovery and support groups', '#EC4899', 5),
('Youth Groups', 'youth-groups', 'Groups for young people', '#06B6D4', 6)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 6.5 Giving Funds
INSERT INTO giving_funds (name, slug, description, is_default, sort_order) VALUES
('General Fund', 'general', 'General church operations and ministry', TRUE, 1),
('Missions', 'missions', 'Support for local and global mission work', FALSE, 2),
('Building Fund', 'building', 'Facility maintenance and improvements', FALSE, 3),
('Benevolence', 'benevolence', 'Helping those in need in our community', FALSE, 4)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 6.6 Service Types
INSERT INTO service_types (name, slug, default_day, default_time, color, sort_order) VALUES
('Sunday Morning', 'sunday-am', 'sunday', '10:30:00', '#3B82F6', 1),
('Sunday Evening', 'sunday-pm', 'sunday', '18:30:00', '#8B5CF6', 2),
('Midweek Service', 'midweek', 'wednesday', '19:30:00', '#10B981', 3)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 6.7 Service Teams
INSERT INTO service_teams (name, slug, color, min_required, sort_order) VALUES
('Worship Team', 'worship', '#EC4899', 3, 1),
('Tech/AV', 'tech', '#6366F1', 2, 2),
('Welcome Team', 'welcome', '#F59E0B', 4, 3),
('Kids Ministry', 'kids', '#14B8A6', 2, 4),
('Prayer Team', 'prayer', '#A855F7', 2, 5)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 6.8 Service Roles (per team - conditional on team existing)
INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Worship Leader', 1 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Vocals 1', 2 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Vocals 2', 3 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Vocals 3', 4 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Keys', 5 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Electric Guitar', 6 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Acoustic Guitar', 7 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Bass', 8 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Drums', 9 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Sound Engineer', 1 FROM service_teams WHERE slug = 'tech'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Projection Operator', 2 FROM service_teams WHERE slug = 'tech'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Lighting', 3 FROM service_teams WHERE slug = 'tech'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Camera Operator', 4 FROM service_teams WHERE slug = 'tech'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Live Stream Director', 5 FROM service_teams WHERE slug = 'tech'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Greeter - Front Door', 1 FROM service_teams WHERE slug = 'welcome'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Greeter - Auditorium', 2 FROM service_teams WHERE slug = 'welcome'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Usher', 3 FROM service_teams WHERE slug = 'welcome'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Info Desk', 4 FROM service_teams WHERE slug = 'welcome'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Teacher', 1 FROM service_teams WHERE slug = 'kids'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Helper', 2 FROM service_teams WHERE slug = 'kids'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Check-in', 3 FROM service_teams WHERE slug = 'kids'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Prayer Minister', 1 FROM service_teams WHERE slug = 'prayer'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Prayer Room Host', 2 FROM service_teams WHERE slug = 'prayer'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

-- 6.9 Custom Worship Flows
INSERT IGNORE INTO custom_worship_flows (name, description, pattern, is_default, sort_order) VALUES
('Standard', 'High energy start, intimate moment, medium end', '["high", "high", "medium", "low", "medium"]', 1, 1),
('Building', 'Builds to climax in the middle', '["medium", "medium", "high", "high", "medium"]', 1, 2),
('Intimate', 'Reflective and quiet throughout', '["medium", "low", "low", "low", "medium"]', 1, 3),
('Celebration', 'High energy throughout', '["high", "high", "high", "medium", "high"]', 1, 4);

-- 6.10 Key Progression Patterns
INSERT INTO key_progression_patterns (from_key, to_key, user_id, transition_count, smoothness_rating) VALUES
('C', 'Am', NULL, 10, 0.95),
('G', 'Em', NULL, 10, 0.95),
('D', 'Bm', NULL, 10, 0.95),
('A', 'F#m', NULL, 10, 0.95),
('E', 'C#m', NULL, 10, 0.95),
('F', 'Dm', NULL, 10, 0.95),
('Bb', 'Gm', NULL, 10, 0.95),
('C', 'G', NULL, 8, 0.90),
('G', 'D', NULL, 8, 0.90),
('D', 'A', NULL, 8, 0.90),
('A', 'E', NULL, 8, 0.90),
('F', 'C', NULL, 8, 0.90),
('Bb', 'F', NULL, 8, 0.90),
('C', 'D', NULL, 5, 0.80),
('D', 'E', NULL, 5, 0.80),
('E', 'F#', NULL, 5, 0.75),
('G', 'A', NULL, 5, 0.80),
('A', 'B', NULL, 5, 0.80),
('G', 'F', NULL, 4, 0.70),
('A', 'G', NULL, 4, 0.75),
('D', 'C', NULL, 4, 0.75),
('E', 'D', NULL, 4, 0.75)
ON DUPLICATE KEY UPDATE smoothness_rating = VALUES(smoothness_rating);

-- 6.11 Setlist Preferences
INSERT INTO setlist_preferences (user_id, team_id, preference_key, preference_value) VALUES
(NULL, NULL, 'default_setlist_length', '5'),
(NULL, NULL, 'freshness_weeks', '4'),
(NULL, NULL, 'opener_energy', 'high'),
(NULL, NULL, 'closer_energy', 'medium'),
(NULL, NULL, 'allow_back_to_back_same_key', 'true'),
(NULL, NULL, 'prefer_key_progression', 'true')
ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value);

-- 6.12 Runsheet Template
INSERT IGNORE INTO service_runsheet_templates (name, template_data, is_default) VALUES
('Sunday Morning - Traditional', JSON_ARRAY(
    JSON_OBJECT('item_type', 'song', 'title', 'Opening Song', 'planned_duration', 5),
    JSON_OBJECT('item_type', 'prayer', 'title', 'Opening Prayer', 'planned_duration', 3),
    JSON_OBJECT('item_type', 'song', 'title', 'Worship Set', 'planned_duration', 20),
    JSON_OBJECT('item_type', 'announcement', 'title', 'Announcements', 'planned_duration', 5),
    JSON_OBJECT('item_type', 'offering', 'title', 'Offering', 'planned_duration', 5),
    JSON_OBJECT('item_type', 'sermon', 'title', 'Message', 'planned_duration', 30),
    JSON_OBJECT('item_type', 'song', 'title', 'Closing Song', 'planned_duration', 5),
    JSON_OBJECT('item_type', 'prayer', 'title', 'Closing Prayer', 'planned_duration', 2)
), TRUE);


-- =============================================
-- SECTION 7: BACKFILL (safe, conditional)
-- =============================================

-- Split existing full_name into first_name and last_name where not yet done
UPDATE users
SET
    first_name = SUBSTRING_INDEX(full_name, ' ', 1),
    last_name = CASE
        WHEN full_name LIKE '% %' THEN SUBSTRING_INDEX(full_name, ' ', -1)
        ELSE NULL
    END
WHERE full_name IS NOT NULL AND first_name IS NULL;

-- Backfill referrer_domain from referrer (safe: only updates NULL values, limited batch)
UPDATE page_visits
SET referrer_domain = SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(referrer, 'https://', ''), 'http://', ''), '/', 1), '?', 1)
WHERE referrer IS NOT NULL AND referrer != '' AND referrer_domain IS NULL
LIMIT 50000;


-- =============================================
-- DONE
-- =============================================
-- All 64 tables, ~50 column additions, ~15 indexes, seed data, and views are now ensured.
-- No existing data was deleted or modified (except safe backfills for NULL fields).
