-- Reclassify bot visits that slipped into page_visits
-- Run this once to move known bot patterns from page_visits to bot_visits

-- 1. Cache-busting monitor visits (?rnd=)
INSERT INTO bot_visits (bot_name, bot_category, bot_owner, classification, user_agent, ip_address, request_url, pattern_matched, visited_at)
SELECT 'Uptime Monitor', 'Monitoring', 'Unknown', 'good', user_agent, ip_address, page_url, 'url_param:rnd', visited_at
FROM page_visits WHERE page_url LIKE '%?rnd=%' OR page_url LIKE '%&rnd=%';

DELETE FROM page_visits WHERE page_url LIKE '%?rnd=%' OR page_url LIKE '%&rnd=%';

-- 2. WAF security scanner visits (?checkwaf=)
INSERT INTO bot_visits (bot_name, bot_category, bot_owner, classification, user_agent, ip_address, request_url, pattern_matched, visited_at)
SELECT 'WAF Security Scanner', 'Security Scanner', 'Unknown', 'suspicious', user_agent, ip_address, page_url, 'url_param:checkwaf', visited_at
FROM page_visits WHERE page_url LIKE '%checkwaf=%';

DELETE FROM page_visits WHERE page_url LIKE '%checkwaf=%';

-- 3. Canary probes (?canary=)
INSERT INTO bot_visits (bot_name, bot_category, bot_owner, classification, user_agent, ip_address, request_url, pattern_matched, visited_at)
SELECT 'Canary Probe', 'Security Scanner', 'Unknown', 'suspicious', user_agent, ip_address, page_url, 'url_param:canary', visited_at
FROM page_visits WHERE page_url LIKE '%?canary=%' OR page_url LIKE '%&canary=%';

DELETE FROM page_visits WHERE page_url LIKE '%?canary=%' OR page_url LIKE '%&canary=%';

-- 4. WordPress probes (wlwmanifest.xml, wp-login, etc.)
INSERT INTO bot_visits (bot_name, bot_category, bot_owner, classification, user_agent, ip_address, request_url, pattern_matched, visited_at)
SELECT 'Vulnerability Scanner', 'Security Scanner', 'Unknown', 'suspicious', user_agent, ip_address, page_url, 'url_path:wp_probe', visited_at
FROM page_visits WHERE page_url LIKE '%wlwmanifest.xml%' OR page_url LIKE '%wp-login%' OR page_url LIKE '%wp-admin%' OR page_url LIKE '%xmlrpc.php%';

DELETE FROM page_visits WHERE page_url LIKE '%wlwmanifest.xml%' OR page_url LIKE '%wp-login%' OR page_url LIKE '%wp-admin%' OR page_url LIKE '%xmlrpc.php%';

-- 5. Server path leak attempts (/home/...)
INSERT INTO bot_visits (bot_name, bot_category, bot_owner, classification, user_agent, ip_address, request_url, pattern_matched, visited_at)
SELECT 'Vulnerability Scanner', 'Security Scanner', 'Unknown', 'suspicious', user_agent, ip_address, page_url, 'url_path:server_path', visited_at
FROM page_visits WHERE page_url LIKE '/home/%';

DELETE FROM page_visits WHERE page_url LIKE '/home/%';

-- 6. Config/credential file probes
INSERT INTO bot_visits (bot_name, bot_category, bot_owner, classification, user_agent, ip_address, request_url, pattern_matched, visited_at)
SELECT 'Vulnerability Scanner', 'Security Scanner', 'Unknown', 'suspicious', user_agent, ip_address, page_url, 'url_path:config_probe', visited_at
FROM page_visits WHERE page_url LIKE '%sftp.json%' OR page_url LIKE '%ftp-config.json%' OR page_url LIKE '%sftp-config.json%' OR page_url LIKE '%crossdomain.xml%' OR page_url LIKE '%clientaccesspolicy.xml%';

DELETE FROM page_visits WHERE page_url LIKE '%sftp.json%' OR page_url LIKE '%ftp-config.json%' OR page_url LIKE '%sftp-config.json%' OR page_url LIKE '%crossdomain.xml%' OR page_url LIKE '%clientaccesspolicy.xml%';

-- 7. Double-slash prefix probes
INSERT INTO bot_visits (bot_name, bot_category, bot_owner, classification, user_agent, ip_address, request_url, pattern_matched, visited_at)
SELECT 'Path Probe Scanner', 'Security Scanner', 'Unknown', 'suspicious', user_agent, ip_address, page_url, 'url:double_slash', visited_at
FROM page_visits WHERE page_url LIKE '//%';

DELETE FROM page_visits WHERE page_url LIKE '//%';

-- 8. Job page spider (foreign-language job URLs)
INSERT INTO bot_visits (bot_name, bot_category, bot_owner, classification, user_agent, ip_address, request_url, pattern_matched, visited_at)
SELECT 'Job Page Spider', 'Crawler', 'Unknown', 'unknown', user_agent, ip_address, page_url, 'url_path:job_spider', visited_at
FROM page_visits WHERE page_url IN (
    '/emploi', '/karriere', '/carrieres', '/stellenangebote',
    '/offres-emploi', '/stellen', '/hiring', '/jobs', '/job',
    '/careers', '/career', '/work-with-us', '/company/careers',
    '/about/careers', '/about/jobs', '/en/jobs', '/en/careers',
    '/join-us', '/join'
);

DELETE FROM page_visits WHERE page_url IN (
    '/emploi', '/karriere', '/carrieres', '/stellenangebote',
    '/offres-emploi', '/stellen', '/hiring', '/jobs', '/job',
    '/careers', '/career', '/work-with-us', '/company/careers',
    '/about/careers', '/about/jobs', '/en/jobs', '/en/careers',
    '/join-us', '/join'
);
