<?php
// File: config/database.php
// InfinityFree-safe MySQLi connection: .env support + force TCP + helpful debug.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Report mysqli errors as exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/** Load .env from project root */
function load_env(string $path): array {
    if (!is_file($path)) return [];
    $env = parse_ini_file($path, false, INI_SCANNER_RAW);
    return is_array($env) ? $env : [];
}

$root = dirname(__DIR__);                 // project root (one level up from /config)
$env  = load_env($root . '/.env');        // expects DB_* keys inside .env

// Read config (prefer .env -> real env -> sensible defaults)
$DB_HOST = $env['DB_HOST'] ?? getenv('DB_HOST') ?? '';
$DB_USER = $env['DB_USER'] ?? getenv('DB_USER') ?? '';
$DB_PASS = $env['DB_PASS'] ?? getenv('DB_PASS') ?? '';
$DB_NAME = $env['DB_NAME'] ?? getenv('DB_NAME') ?? '';
$DB_PORT = (int)($env['DB_PORT'] ?? getenv('DB_PORT') ?? 3306);
$DB_CHARSET = 'utf8mb4';

// --- InfinityFree specifics ---
// If host is empty or 'localhost', mysqli will try a UNIX socket (which doesn't exist here).
// Force TCP by using the real hostname (from InfinityFree panel) or 127.0.0.1 as a last resort.
if ($DB_HOST === '' || strtolower($DB_HOST) === 'localhost') {
    // If you know your host, put it here to be explicit:
    // $DB_HOST = 'sql303.infinityfree.com';
    // Otherwise, at least force TCP instead of socket:
    $DB_HOST = '127.0.0.1';
}

try {
    // Force TCP: pass NULL for socket and a numeric port to mysqli_real_connect
    $mysqli = mysqli_init();
    if (!$mysqli) {
        throw new RuntimeException('mysqli_init() failed');
    }

    // Optional: short timeout helps avoid hanging
    $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

    // The actual connection
    if (!$mysqli->real_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT, /*socket*/NULL, /*flags*/0)) {
        throw new RuntimeException('mysqli_real_connect() failed');
    }

    $mysqli->set_charset($DB_CHARSET);
    $conn = $mysqli;

} catch (Throwable $e) {
    error_log('[DB] Connection failed: ' . $e->getMessage());

    // Friendly message for normal users
    if (!(isset($_GET['debug']) && $_GET['debug'] == '1')) {
        die('Database Connection Error: Unable to connect to the database. Please try again later.');
    }

    // Detailed debug output (safe to view when you add ?debug=1 to the URL)
    header('Content-Type: text/plain; charset=utf-8');
    echo "DATABASE CONNECTION FAILED\n\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Host:  {$DB_HOST}\n";
    echo "Port:  {$DB_PORT}\n";
    echo "User:  {$DB_USER}\n";
    echo "DB:    {$DB_NAME}\n";

    // Try to show resolved IP (helps catch DNS issues)
    if (function_exists('gethostbyname') && $DB_HOST && !preg_match('/^\d+\.\d+\.\d+\.\d+$/', $DB_HOST)) {
        $ip = gethostbyname($DB_HOST);
        echo "Host resolves to: {$ip}\n";
    }
    exit;
}
