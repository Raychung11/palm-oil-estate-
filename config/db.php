<?php
/**
 * config/db.php
 * PDO database connection for Estate BOS.
 *
 * On Hostinger shared hosting, update the credentials below with the
 * values from hPanel -> Databases -> MySQL Databases.
 */

$DB_HOST    = 'localhost';
$DB_NAME    = 'estate_bos';
$DB_USER    = 'root';
$DB_PASS    = '';
$DB_CHARSET = 'utf8mb4';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    // Never leak credentials or stack traces to the browser.
    http_response_code(500);
    if (defined('APP_DEBUG') && APP_DEBUG) {
        die('Database connection failed: ' . $e->getMessage());
    }
    die('Database connection error. Please contact the administrator.');
}
