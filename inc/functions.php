<?php
/**
 * inc/functions.php
 * Shared helper functions for Estate BOS.
 */

/**
 * Escape output for safe HTML rendering (XSS protection).
 */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Build an absolute URL within the app, respecting BASE_URL.
 */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Redirect to an internal path and stop execution.
 */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Read a request value (GET/POST) with a default fallback.
 */
function input(string $key, $default = '')
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

/**
 * Store a one-time flash message ('success' | 'danger' | 'warning' | 'info').
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Render and clear all pending flash messages as Bootstrap alerts.
 */
function render_flash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $html = '';
    foreach ($_SESSION['flash'] as $flash) {
        $html .= '<div class="alert alert-' . e($flash['type'])
            . ' alert-dismissible fade show" role="alert">'
            . e($flash['message'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
            . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/**
 * Read the current page number from the query string (>= 1).
 */
function current_page(): int
{
    $page = (int)($_GET['page'] ?? 1);
    return $page < 1 ? 1 : $page;
}

/**
 * Render a Bootstrap pagination control.
 *
 * @param int    $total    Total record count
 * @param int    $perPage  Records per page
 * @param int    $page     Current page
 * @param string $basePath Path used for page links (query string preserved)
 */
function render_pagination(int $total, int $perPage, int $page, string $basePath): string
{
    $pages = (int)ceil($total / max(1, $perPage));
    if ($pages <= 1) {
        return '';
    }

    // Preserve existing query params except `page`.
    $query = $_GET;
    $link = function (int $p) use ($query, $basePath): string {
        $query['page'] = $p;
        return url($basePath) . '?' . http_build_query($query);
    };

    $html = '<nav><ul class="pagination justify-content-center">';
    $html .= '<li class="page-item' . ($page <= 1 ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . e($link(max(1, $page - 1))) . '">Previous</a></li>';

    for ($i = 1; $i <= $pages; $i++) {
        $html .= '<li class="page-item' . ($i === $page ? ' active' : '') . '">'
            . '<a class="page-link" href="' . e($link($i)) . '">' . $i . '</a></li>';
    }

    $html .= '<li class="page-item' . ($page >= $pages ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . e($link(min($pages, $page + 1))) . '">Next</a></li>';
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Format a datetime string for display, or return a dash when empty.
 */
function fmt_datetime(?string $value, string $format = 'd M Y, h:i A'): string
{
    if (empty($value)) {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : '—';
}

/**
 * Format a numeric value for display, or a dash when null/empty.
 */
function num($value, int $decimals = 2): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    return number_format((float)$value, $decimals);
}

/**
 * Normalise a form field to a decimal value or null (for nullable columns).
 */
function to_decimal_or_null($value): ?float
{
    $value = trim((string)$value);
    return $value === '' ? null : (float)$value;
}

/**
 * Normalise a form field to an int value or null (for nullable columns).
 */
function to_int_or_null($value): ?int
{
    $value = trim((string)$value);
    return $value === '' ? null : (int)$value;
}

/**
 * Convert a kilogram value to tonnes for display.
 */
function kg_to_tonnes($kg, int $decimals = 2): string
{
    if ($kg === null || $kg === '') {
        return '—';
    }
    return number_format((float)$kg / 1000, $decimals);
}

/**
 * Handle a single uploaded file safely.
 *
 * Validates the upload, checks size and extension against a whitelist,
 * moves it into UPLOAD_PATH/$subdir and returns the stored relative path
 * (e.g. 'harvest/abc123.jpg'). Returns null when no file was provided.
 * Throws RuntimeException on a genuine validation failure.
 *
 * @param array  $file        A single $_FILES entry
 * @param string $subdir      Sub-folder under uploads/ (e.g. 'harvest')
 * @param array  $allowedExt  Lowercase extensions without the dot
 * @param int    $maxBytes    Maximum allowed size
 */
function handle_upload(array $file, string $subdir, array $allowedExt = ['jpg','jpeg','png','webp','pdf'], int $maxBytes = 5242880): ?string
{
    // No file selected is not an error — the field is optional.
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed. Please try again.');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('File is too large (max ' . round($maxBytes / 1048576) . ' MB).');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('File type not allowed. Allowed: ' . implode(', ', $allowedExt) . '.');
    }

    $dir = UPLOAD_PATH . '/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create the upload folder.');
    }

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }

    return $subdir . '/' . $name;
}

/**
 * Render a small Bootstrap status badge.
 */
function status_badge(string $status): string
{
    $map = [
        'active'    => 'success',
        'inactive'  => 'secondary',
        'pending'   => 'warning',
        'approved'  => 'success',
        'rejected'  => 'danger',
        // Block / GIS status colours (blueprint Section 14).
        'normal'    => 'success',
        'attention' => 'warning',
        'critical'  => 'danger',
        'scheduled' => 'primary',
    ];
    $color = $map[strtolower($status)] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . e(ucfirst($status)) . '</span>';
}
