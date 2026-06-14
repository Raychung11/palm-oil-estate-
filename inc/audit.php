<?php
/**
 * inc/audit.php
 * Activity / audit logging helper.
 */

/**
 * Record an action in user_activity_logs.
 *
 * @param PDO         $pdo
 * @param int|null    $userId
 * @param string      $action       Short verb, e.g. 'login', 'create', 'update'
 * @param string|null $module       Module name, e.g. 'users'
 * @param string|null $description  Human-readable detail
 */
function log_activity(PDO $pdo, ?int $userId, string $action, ?string $module = null, ?string $description = null): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO user_activity_logs
                (user_id, action, module, description, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $userId,
            $action,
            $module,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Throwable $e) {
        // Auditing must never break the main request.
    }
}
