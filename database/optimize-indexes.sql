-- Performance Optimization Indexes
-- Run these queries to add indexes that improve query performance
-- Note: Duplicate index errors can be safely ignored

-- User reading history - frequently queried by user_id and study_id
ALTER TABLE user_reading_history ADD INDEX idx_user_reading_history_user (user_id);
ALTER TABLE user_reading_history ADD INDEX idx_user_reading_history_user_study (user_id, study_id);
ALTER TABLE user_reading_history ADD INDEX idx_user_reading_history_last_read (user_id, last_read_at DESC);

-- User highlights - frequently queried by user_id and study_id
ALTER TABLE user_highlights ADD INDEX idx_user_highlights_user (user_id);
ALTER TABLE user_highlights ADD INDEX idx_user_highlights_user_study (user_id, study_id);

-- User saved studies
ALTER TABLE user_saved_studies ADD INDEX idx_user_saved_studies_user (user_id);

-- Reading plan progress
ALTER TABLE user_reading_plan_progress ADD INDEX idx_reading_plan_progress_user (user_id);
ALTER TABLE user_reading_plan_progress ADD INDEX idx_reading_plan_progress_active (user_id, completed_at, is_paused);

-- Reading plan completions
ALTER TABLE user_reading_plan_completions ADD INDEX idx_reading_plan_completions_user (user_id, plan_id);

-- Sermons - common browse queries
ALTER TABLE sermons ADD INDEX idx_sermons_visible_date (visible, sermon_date DESC);
ALTER TABLE sermons ADD INDEX idx_sermons_series (series_id, visible, display_order);
ALTER TABLE sermons ADD INDEX idx_sermons_featured (is_featured, featured_location, visible);
ALTER TABLE sermons ADD INDEX idx_sermons_slug (slug);

-- Sermon series
ALTER TABLE sermon_series ADD INDEX idx_sermon_series_visible (visible, display_order);
ALTER TABLE sermon_series ADD INDEX idx_sermon_series_slug (slug);

-- Page visits (analytics) - date-based queries
ALTER TABLE page_visits ADD INDEX idx_page_visits_date (visited_at);
ALTER TABLE page_visits ADD INDEX idx_page_visits_session (session_id);
ALTER TABLE page_visits ADD INDEX idx_page_visits_page (page_url, visited_at);

-- Bible studies
ALTER TABLE bible_studies ADD INDEX idx_bible_studies_book_chapter (book_id, chapter);
ALTER TABLE bible_studies ADD INDEX idx_bible_studies_status (status);

-- Users
ALTER TABLE users ADD INDEX idx_users_role (role, active);

-- User sessions (for remember me tokens)
ALTER TABLE user_sessions ADD INDEX idx_user_sessions_token (session_token);
ALTER TABLE user_sessions ADD INDEX idx_user_sessions_expires (expires_at);

-- Blog posts
ALTER TABLE blog_posts ADD INDEX idx_blog_posts_status (status, published_at DESC);

-- CMS content blocks
ALTER TABLE cms_content_blocks ADD INDEX idx_cms_blocks_page (page_identifier, block_key);

-- Form submissions
ALTER TABLE form_submissions ADD INDEX idx_form_submissions_type (form_type, submitted_at DESC);
ALTER TABLE form_submissions ADD INDEX idx_form_submissions_processed (processed);
