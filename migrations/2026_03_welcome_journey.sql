-- Welcome Journey Migration
-- Created: 2026-03-23
-- Purpose: Add tables for automated welcome email sequences
--
-- Run this migration on production:
--   mysql -u [username] -p [database_name] < migrations/2026_03_welcome_journey.sql

-- ============================================
-- Welcome Journey Tables
-- ============================================

-- Main journey tracking table
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
    INDEX idx_unsubscribe (unsubscribe_token),
    FOREIGN KEY (form_submission_id) REFERENCES form_submissions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual email tracking
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
    INDEX idx_pending_due (status, scheduled_at),
    FOREIGN KEY (journey_id) REFERENCES welcome_journeys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Verification
-- ============================================
SELECT 'Migration complete: welcome_journeys and welcome_journey_emails tables created' AS status;
