<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'jhs96');
define('DB_USER', 'jhs96user');
define('DB_PASS', 'REDACTED');
define('ADMIN_PASSWORD', 'Rockets1996!');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', '/uploads/');

function db() {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function is_admin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}
