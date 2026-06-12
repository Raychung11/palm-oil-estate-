<?php
/**
 * config/app.php
 * Global application settings for Estate BOS.
 */

// Display name shown in the UI
define('APP_NAME', 'Estate BOS');
define('APP_TAGLINE', 'Palm Oil Estate Business Operating System');

// Base URL path (folder under public_html). Use '' if installed at domain root.
// Example for /public_html/estate-bos/  ->  '/estate-bos'
define('BASE_URL', '');

// Absolute filesystem path to the project root (no trailing slash)
define('BASE_PATH', dirname(__DIR__));

// Upload directory (kept inside the project, protected by .htaccess)
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');

// Session timeout in seconds (30 minutes of inactivity)
define('SESSION_TIMEOUT', 1800);

// Default pagination size for list pages
define('PER_PAGE', 15);

// Timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// AI assistant — Phase 1 is rule-based (offline). Set AI_API_ENABLED to true
// and provide a key to enable an optional LLM provider (Phase 2). Even when
// enabled, the assistant only answers from system data per the safety rules.
define('AI_API_ENABLED', false);
define('AI_API_PROVIDER', 'claude'); // 'claude' | 'openai'
define('AI_API_KEY', '');

// Error reporting — turn display off in production on Hostinger
define('APP_DEBUG', false);
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}
