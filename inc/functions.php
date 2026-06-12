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
 * Render a small Bootstrap status badge.
 */
function status_badge(string $status): string
{
    $map = [
        'active'   => 'success',
        'inactive' => 'secondary',
        'pending'  => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
    ];
    $color = $map[strtolower($status)] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . e(ucfirst($status)) . '</span>';
}
