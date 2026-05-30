<?php
// ── Database Configuration ──────────────────────────────
// Edit these to match your XAMPP setup
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // default XAMPP user
define('DB_PASS', '');           // default XAMPP password (empty)
define('DB_NAME', 'university_clinic');

// ── CORS headers (allow frontend to call API) ──────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ── PDO Connection ─────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
            exit();
        }
    }
    return $pdo;
}

// ── Helper: send JSON response ─────────────────────────
function respond(mixed $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit();
}

// ── Helper: get JSON body ──────────────────────────────
function getBody(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

// ── Helper: require auth session ──────────────────────
function requireAuth(): array {
    session_start();
    if (empty($_SESSION['user'])) {
        respond(['error' => 'Unauthorized'], 401);
    }
    return $_SESSION['user'];
}
