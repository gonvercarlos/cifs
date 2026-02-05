<?php
// config.php
// Database configuration from environment variables
$db_host = getenv('DB_HOST') ?: 'qadb.hallon.es';
$db_port = getenv('DB_PORT') ?: '5432';
$db_name = getenv('DB_NAME') ?: 'eprensa';
$db_user = getenv('DB_USER') ?: 'postgres';
$db_pass = getenv('DB_PASS') ?: 'Ugqh=9nHn)10';

// Define BASE_PATH constant for URL generation
define('BASE_PATH', rtrim(getenv('BASE_PATH') ?: '', '/'));

try {
    // PDO para Postgres
    $dsn = "pgsql:host={$db_host};port={$db_port};dbname={$db_name}";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false, // usar prepares nativos cuando sea posible
    ]);
} catch (Exception $e) {
    // En producción muestra un mensaje genérico y registra el error en logs
    die("Error de conexión a base de datos.");
}
session_start();
// CSRF token sencillo
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16));
}
