-- User Member Fields Migration
-- Created: 2026-03-23
-- Purpose: Add membership-related fields to existing users table
--
-- Run this migration on production:
--   mysql -u [username] -p [database_name] < migrations/2026_03_23_003_user_member_fields.sql
--
-- IMPORTANT: Run migrations 001 and 002 first (membership_statuses and households)

-- ============================================
-- Extend Users Table with Member Fields
-- ============================================

-- First, update the role enum to include 'user' for regular members
-- Keep 'member' for backwards compatibility with existing data
ALTER TABLE users
    MODIFY COLUMN role ENUM('admin', 'editor', 'user', 'member') DEFAULT 'user';

-- Add membership fields
ALTER TABLE users
    ADD COLUMN is_member BOOLEAN DEFAULT FALSE AFTER active,
    ADD COLUMN membership_status_id INT NULL AFTER is_member,
    ADD COLUMN member_since DATE NULL AFTER membership_status_id,
    ADD COLUMN household_id INT NULL AFTER member_since,
    ADD COLUMN household_role ENUM('primary', 'spouse', 'child', 'other') DEFAULT 'primary' AFTER household_id;

-- Add personal information fields
ALTER TABLE users
    ADD COLUMN first_name VARCHAR(100) NULL AFTER full_name,
    ADD COLUMN last_name VARCHAR(100) NULL AFTER first_name,
    ADD COLUMN middle_name VARCHAR(100) NULL AFTER last_name,
    ADD COLUMN nickname VARCHAR(100) NULL AFTER middle_name,
    ADD COLUMN prefix VARCHAR(20) NULL COMMENT 'Mr., Mrs., Dr., Rev., etc.' AFTER nickname,
    ADD COLUMN suffix VARCHAR(20) NULL COMMENT 'Jr., III, PhD, etc.' AFTER prefix;

-- Add demographic fields
ALTER TABLE users
    ADD COLUMN gender ENUM('male', 'female', 'prefer_not_to_say') NULL AFTER suffix,
    ADD COLUMN birthdate DATE NULL AFTER gender,
    ADD COLUMN marital_status ENUM('single', 'married', 'divorced', 'widowed', 'separated') NULL AFTER birthdate,
    ADD COLUMN anniversary DATE NULL AFTER marital_status;

-- Add spiritual milestones
ALTER TABLE users
    ADD COLUMN salvation_date DATE NULL AFTER anniversary,
    ADD COLUMN baptism_date DATE NULL AFTER salvation_date;

-- Add profile/display settings
ALTER TABLE users
    ADD COLUMN profile_photo VARCHAR(500) NULL AFTER baptism_date,
    ADD COLUMN directory_visible BOOLEAN DEFAULT TRUE COMMENT 'Show in member directory' AFTER profile_photo,
    ADD COLUMN email_opt_out BOOLEAN DEFAULT FALSE AFTER directory_visible,
    ADD COLUMN sms_opt_out BOOLEAN DEFAULT FALSE AFTER email_opt_out,
    ADD COLUMN communication_preferences JSON NULL COMMENT 'Detailed comm preferences' AFTER sms_opt_out;

-- Add indexes for common queries
ALTER TABLE users
    ADD INDEX idx_is_member (is_member),
    ADD INDEX idx_membership_status (membership_status_id),
    ADD INDEX idx_household (household_id),
    ADD INDEX idx_last_name (last_name),
    ADD INDEX idx_first_last (first_name, last_name);

-- Add foreign keys
ALTER TABLE users
    ADD CONSTRAINT fk_users_membership_status
        FOREIGN KEY (membership_status_id) REFERENCES membership_statuses(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_users_household
        FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE SET NULL;

-- Now add the foreign key from households to users
ALTER TABLE households
    ADD CONSTRAINT fk_households_primary_contact
        FOREIGN KEY (primary_contact_id) REFERENCES users(id) ON DELETE SET NULL;

-- ============================================
-- Migrate existing full_name to first/last name
-- ============================================

-- Split existing full_name into first_name and last_name where possible
UPDATE users
SET
    first_name = SUBSTRING_INDEX(full_name, ' ', 1),
    last_name = CASE
        WHEN full_name LIKE '% %' THEN SUBSTRING_INDEX(full_name, ' ', -1)
        ELSE NULL
    END
WHERE full_name IS NOT NULL AND first_name IS NULL;

-- ============================================
-- Verification
-- ============================================
SELECT 'Migration complete: users table extended with member fields' AS status;

-- Show the updated structure
DESCRIBE users;
