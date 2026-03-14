-- Alive Church CMS Database Schema
-- MySQL/MariaDB

-- Users table for admin authentication
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin', 'editor') DEFAULT 'editor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    active BOOLEAN DEFAULT TRUE,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Site settings (key-value pairs)
CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'textarea', 'number', 'boolean', 'json') DEFAULT 'text',
    setting_group VARCHAR(50) DEFAULT 'general',
    display_name VARCHAR(100),
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pages table for dynamic page management
CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) UNIQUE NOT NULL,
    title VARCHAR(200) NOT NULL,
    meta_description TEXT,
    content LONGTEXT,
    template VARCHAR(50) DEFAULT 'default',
    published BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    INDEX idx_slug (slug),
    INDEX idx_published (published),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Page sections for flexible page building
CREATE TABLE IF NOT EXISTS page_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    section_type VARCHAR(50) NOT NULL,
    section_order INT DEFAULT 0,
    heading VARCHAR(200),
    subheading VARCHAR(200),
    content LONGTEXT,
    image_url VARCHAR(500),
    background_image VARCHAR(500),
    button_text VARCHAR(100),
    button_url VARCHAR(500),
    additional_data JSON,
    visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_page (page_id),
    INDEX idx_order (section_order),
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Navigation menu items
CREATE TABLE IF NOT EXISTS navigation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    menu_order INT DEFAULT 0,
    parent_id INT NULL,
    css_class VARCHAR(100),
    visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order (menu_order),
    INDEX idx_parent (parent_id),
    FOREIGN KEY (parent_id) REFERENCES navigation(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ministries
CREATE TABLE IF NOT EXISTS ministries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    summary TEXT,
    description LONGTEXT,
    image_url VARCHAR(500),
    display_order INT DEFAULT 0,
    visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order (display_order),
    INDEX idx_visible (visible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Groups
CREATE TABLE IF NOT EXISTS groups_list (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    schedule VARCHAR(200),
    location VARCHAR(200),
    image_url VARCHAR(500),
    signup_url VARCHAR(500),
    category VARCHAR(50),
    display_order INT DEFAULT 0,
    visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Serve opportunities
CREATE TABLE IF NOT EXISTS serve_opportunities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    commitment VARCHAR(200),
    areas JSON,
    image_url VARCHAR(500),
    display_order INT DEFAULT 0,
    visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Next steps
CREATE TABLE IF NOT EXISTS next_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    copy TEXT,
    link VARCHAR(500),
    icon VARCHAR(100),
    display_order INT DEFAULT 0,
    visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sermon series
CREATE TABLE IF NOT EXISTS sermon_series (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    image_url VARCHAR(500),
    date_range VARCHAR(100),
    start_date DATE,
    end_date DATE,
    message_count INT DEFAULT 0,
    display_order INT DEFAULT 0,
    visible BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    featured_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_order (display_order),
    INDEX idx_featured (is_featured, featured_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual sermons
CREATE TABLE IF NOT EXISTS sermons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    series_id INT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(150) UNIQUE,
    speaker VARCHAR(100),
    speaker_user_id INT NULL,
    sermon_date DATE,
    length VARCHAR(50),
    duration_seconds INT,
    video_id VARCHAR(100),
    youtube_video_id VARCHAR(50),
    audio_url VARCHAR(500),
    thumbnail_url VARCHAR(500),
    description TEXT,
    transcript LONGTEXT,
    is_featured BOOLEAN DEFAULT FALSE,
    featured_location VARCHAR(50) DEFAULT NULL,
    featured_order INT DEFAULT 0,
    youtube_fetched_at TIMESTAMP NULL,
    youtube_data JSON,
    display_order INT DEFAULT 0,
    visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_series (series_id),
    INDEX idx_date (sermon_date),
    INDEX idx_slug (slug),
    INDEX idx_youtube_id (youtube_video_id),
    INDEX idx_speaker_user (speaker_user_id),
    INDEX idx_featured (is_featured, featured_location, featured_order),
    FOREIGN KEY (series_id) REFERENCES sermon_series(id) ON DELETE SET NULL,
    FOREIGN KEY (speaker_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sermon to Bible study links (for showing related sermons on study pages)
CREATE TABLE IF NOT EXISTS sermon_study_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sermon_id INT NOT NULL,
    study_id INT NOT NULL,
    link_type ENUM('auto_suggested', 'admin_confirmed', 'admin_added') DEFAULT 'auto_suggested',
    relevance_score DECIMAL(5,2) DEFAULT 0,
    verse_reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    confirmed_by INT NULL,
    UNIQUE KEY unique_link (sermon_id, study_id),
    INDEX idx_sermon (sermon_id),
    INDEX idx_study (study_id),
    INDEX idx_confirmed (link_type),
    FOREIGN KEY (sermon_id) REFERENCES sermons(id) ON DELETE CASCADE,
    FOREIGN KEY (study_id) REFERENCES bible_studies(id) ON DELETE CASCADE,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sermon topic tags (for categorizing sermons by life topics)
CREATE TABLE IF NOT EXISTS sermon_topic_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sermon_id INT NOT NULL,
    topic_id INT NOT NULL,
    relevance_score DECIMAL(5,2) DEFAULT 0,
    auto_tagged BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tag (sermon_id, topic_id),
    INDEX idx_sermon (sermon_id),
    INDEX idx_topic (topic_id),
    FOREIGN KEY (sermon_id) REFERENCES sermons(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id) REFERENCES bible_study_topics(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sermon scripture references (detected from transcript)
CREATE TABLE IF NOT EXISTS sermon_scripture_refs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sermon_id INT NOT NULL,
    book_id INT NOT NULL,
    chapter INT NOT NULL,
    verse_start INT NULL,
    verse_end INT NULL,
    context_snippet VARCHAR(500),
    auto_detected BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sermon (sermon_id),
    INDEX idx_book_chapter (book_id, chapter),
    FOREIGN KEY (sermon_id) REFERENCES sermons(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES bible_books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Media library
CREATE TABLE IF NOT EXISTS media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    width INT NULL,
    height INT NULL,
    alt_text VARCHAR(255),
    caption TEXT,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (file_type),
    INDEX idx_uploaded_by (uploaded_by),
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Form submissions
CREATE TABLE IF NOT EXISTS form_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_type VARCHAR(50) NOT NULL,
    form_data JSON NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    processed BOOLEAN DEFAULT FALSE,
    notes TEXT,
    INDEX idx_type (form_type),
    INDEX idx_date (submitted_at),
    INDEX idx_processed (processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log for admin actions
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_date (created_at),
    INDEX idx_entity (entity_type, entity_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Content blocks - stores all editable content (CMS)
CREATE TABLE IF NOT EXISTS content_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_slug VARCHAR(100) NOT NULL,
    block_key VARCHAR(100) NOT NULL,
    content_type ENUM('text', 'html', 'image', 'link', 'json') DEFAULT 'html',
    content LONGTEXT,
    metadata JSON,
    version INT DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    UNIQUE KEY unique_block (page_slug, block_key),
    INDEX idx_page (page_slug),
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Content revisions for undo/history
CREATE TABLE IF NOT EXISTS content_revisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    block_id INT NOT NULL,
    content LONGTEXT,
    version INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    INDEX idx_block (block_id),
    INDEX idx_version (block_id, version),
    FOREIGN KEY (block_id) REFERENCES content_blocks(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Global content blocks (footer, header elements, etc.)
CREATE TABLE IF NOT EXISTS global_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    block_key VARCHAR(100) UNIQUE NOT NULL,
    content_type ENUM('text', 'html', 'image', 'link', 'json') DEFAULT 'html',
    content LONGTEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    INDEX idx_key (block_key),
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reusable content components (testimonials, team members, etc.)
CREATE TABLE IF NOT EXISTS content_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    component_type VARCHAR(50) NOT NULL,
    title VARCHAR(200),
    content JSON NOT NULL,
    display_order INT DEFAULT 0,
    visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (component_type),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Page visit tracking for analytics
CREATE TABLE IF NOT EXISTS page_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_url VARCHAR(500) NOT NULL,
    page_title VARCHAR(255) DEFAULT NULL,
    referrer VARCHAR(500) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    session_id VARCHAR(64) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    device_type ENUM('desktop', 'tablet', 'mobile') DEFAULT 'desktop',
    browser VARCHAR(50) DEFAULT NULL,
    visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_visited_at (visited_at),
    INDEX idx_page_url (page_url(100)),
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_device_type (device_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blog posts
CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    excerpt TEXT,
    content LONGTEXT,
    featured_image VARCHAR(500),
    category_id INT,
    author_id INT,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_published (published_at),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blog categories
CREATE TABLE IF NOT EXISTS blog_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blog tags
CREATE TABLE IF NOT EXISTS blog_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blog post tags junction table
CREATE TABLE IF NOT EXISTS blog_post_tags (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blog comments
CREATE TABLE IF NOT EXISTS blog_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NULL,
    parent_id INT NULL,
    author_name VARCHAR(100) NULL,
    author_email VARCHAR(255) NULL,
    content TEXT NOT NULL,
    status ENUM('pending', 'approved', 'spam') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_post (post_id),
    INDEX idx_user (user_id),
    INDEX idx_parent (parent_id),
    INDEX idx_status (status),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES blog_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Profanity filter words
CREATE TABLE IF NOT EXISTS profanity_words (
    id INT AUTO_INCREMENT PRIMARY KEY,
    word VARCHAR(100) NOT NULL,
    category ENUM('profanity', 'slur', 'sexual', 'threat', 'other') DEFAULT 'profanity',
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_word (word),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Page blocks for block-based page builder
CREATE TABLE IF NOT EXISTS page_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_slug VARCHAR(100) NOT NULL,
    block_uuid VARCHAR(36) NOT NULL,
    block_type VARCHAR(50) NOT NULL,
    block_data JSON NOT NULL,
    display_order INT DEFAULT 0,
    visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    UNIQUE KEY unique_block (page_slug, block_uuid),
    INDEX idx_page_order (page_slug, display_order),
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Image processing queue for background optimization
CREATE TABLE IF NOT EXISTS image_processing_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_path VARCHAR(500) NOT NULL,
    image_type ENUM('general', 'avatar', 'hero') DEFAULT 'general',
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    result JSON NULL,
    error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Image variants tracking (for responsive images)
CREATE TABLE IF NOT EXISTS image_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_id INT NULL,
    original_path VARCHAR(500) NOT NULL,
    variant_name VARCHAR(50) NOT NULL,
    variant_path VARCHAR(500) NOT NULL,
    format VARCHAR(10) NOT NULL,
    width INT NULL,
    height INT NULL,
    file_size INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_media (media_id),
    INDEX idx_original (original_path(255)),
    UNIQUE KEY unique_variant (original_path(255), variant_name, format),
    FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
