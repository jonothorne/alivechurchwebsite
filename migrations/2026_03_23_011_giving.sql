-- Giving Module Migration
-- Created: 2026-03-23
-- Purpose: Enhanced giving with funds, donation tracking, recurring gifts, and statements

-- ============================================
-- Giving Funds
-- ============================================
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

-- ============================================
-- Donations
-- ============================================
CREATE TABLE IF NOT EXISTS donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'NULL for guest donations',
    fund_id INT NULL,

    -- Payment details
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'GBP',
    payment_method VARCHAR(50) NULL COMMENT 'card, bank_transfer, cash, check',
    stripe_payment_id VARCHAR(255) NULL,
    stripe_customer_id VARCHAR(255) NULL,

    -- Donor info (for guests or override)
    donor_email VARCHAR(255) NOT NULL,
    donor_name VARCHAR(200) NULL,
    donor_address TEXT NULL,

    -- Gift Aid (UK)
    gift_aid BOOLEAN DEFAULT FALSE,
    gift_aid_declaration_date DATE NULL,

    -- Frequency
    frequency ENUM('one-time', 'weekly', 'monthly', 'yearly') DEFAULT 'one-time',
    recurring_id INT NULL COMMENT 'Link to recurring_donations',

    -- Status
    status ENUM('pending', 'completed', 'failed', 'refunded', 'cancelled') DEFAULT 'pending',
    notes TEXT NULL,

    -- Timestamps
    donated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,

    INDEX idx_user (user_id),
    INDEX idx_fund (fund_id),
    INDEX idx_status (status),
    INDEX idx_date (donated_at),
    INDEX idx_stripe (stripe_payment_id),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (fund_id) REFERENCES giving_funds(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Recurring Donations
-- ============================================
CREATE TABLE IF NOT EXISTS recurring_donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    fund_id INT NULL,

    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'GBP',
    frequency ENUM('weekly', 'monthly', 'yearly') DEFAULT 'monthly',

    -- Stripe subscription
    stripe_subscription_id VARCHAR(255) NULL,
    stripe_customer_id VARCHAR(255) NULL,

    -- Donor info
    donor_email VARCHAR(255) NOT NULL,
    donor_name VARCHAR(200) NULL,

    gift_aid BOOLEAN DEFAULT FALSE,

    -- Schedule
    next_payment_date DATE NULL,
    last_payment_date DATE NULL,
    total_given DECIMAL(12,2) DEFAULT 0,
    payment_count INT DEFAULT 0,

    -- Status
    status ENUM('active', 'paused', 'cancelled', 'failed') DEFAULT 'active',
    cancelled_at TIMESTAMP NULL,
    cancel_reason VARCHAR(255) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_next_payment (next_payment_date),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (fund_id) REFERENCES giving_funds(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Giving Statements
-- ============================================
CREATE TABLE IF NOT EXISTS giving_statements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    year INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_amount DECIMAL(12,2) DEFAULT 0,
    donation_count INT DEFAULT 0,
    gift_aid_amount DECIMAL(12,2) DEFAULT 0,
    file_path VARCHAR(500) NULL COMMENT 'PDF path if generated',
    generated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_user_year (user_id, year),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Pledges (optional feature)
-- ============================================
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
    INDEX idx_status (status),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (fund_id) REFERENCES giving_funds(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Default Funds
-- ============================================
INSERT INTO giving_funds (name, slug, description, is_default, sort_order) VALUES
('General Fund', 'general', 'General church operations and ministry', TRUE, 1),
('Missions', 'missions', 'Support for local and global mission work', FALSE, 2),
('Building Fund', 'building', 'Facility maintenance and improvements', FALSE, 3),
('Benevolence', 'benevolence', 'Helping those in need in our community', FALSE, 4);

-- ============================================
-- Verification
-- ============================================
SELECT 'Migration complete: Giving module tables created' AS status;
SELECT COUNT(*) AS funds_created FROM giving_funds;
