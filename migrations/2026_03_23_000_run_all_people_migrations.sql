-- ============================================
-- PEOPLE MODULE MIGRATIONS - RUN ALL
-- ============================================
-- Created: 2026-03-23
-- Purpose: Master migration file for People/Members module (Phase 1)
--
-- This file sources all the individual migrations in the correct order.
-- Run this single file to apply all People module migrations.
--
-- USAGE:
--   mysql -u [username] -p [database_name] < migrations/2026_03_23_000_run_all_people_migrations.sql
--
-- OR run each file individually in order:
--   1. 2026_03_23_001_membership_statuses.sql
--   2. 2026_03_23_002_households.sql
--   3. 2026_03_23_003_user_member_fields.sql
--   4. 2026_03_23_004_addresses.sql
--   5. 2026_03_23_005_phone_numbers.sql
--   6. 2026_03_23_006_member_tags.sql
--   7. 2026_03_23_007_user_notes.sql
--
-- ============================================

-- NOTE: MySQL's SOURCE command only works in mysql client, not in SQL files.
-- For a combined migration, run this in your terminal:
--
-- cat migrations/2026_03_23_001_membership_statuses.sql \
--     migrations/2026_03_23_002_households.sql \
--     migrations/2026_03_23_003_user_member_fields.sql \
--     migrations/2026_03_23_004_addresses.sql \
--     migrations/2026_03_23_005_phone_numbers.sql \
--     migrations/2026_03_23_006_member_tags.sql \
--     migrations/2026_03_23_007_user_notes.sql \
--     | mysql -u [username] -p [database_name]
--
-- Or use the PHP migration runner: php migrations/run.php

SELECT '=== PEOPLE MODULE MIGRATION INSTRUCTIONS ===' AS info;
SELECT 'Run migrations in this order:' AS step_1;
SELECT '1. 2026_03_23_001_membership_statuses.sql' AS migration_1;
SELECT '2. 2026_03_23_002_households.sql' AS migration_2;
SELECT '3. 2026_03_23_003_user_member_fields.sql' AS migration_3;
SELECT '4. 2026_03_23_004_addresses.sql' AS migration_4;
SELECT '5. 2026_03_23_005_phone_numbers.sql' AS migration_5;
SELECT '6. 2026_03_23_006_member_tags.sql' AS migration_6;
SELECT '7. 2026_03_23_007_user_notes.sql' AS migration_7;
