<?php
// api/auth.php  — handles login, signup, logout, session check
require_once 'config.php';
session_start();

// ── bcrypt compat: Python uses $2b$, PHP uses $2y$ ────────
function verifyPassword(string $plain, string $hash): bool {
    // Normalize $2b$ → $2y$ so PHP's password_verify works
    if (str_starts_with($hash, '$2b$')) {
        $hash = '$2y$' . substr($hash, 4);
    }
    return password_verify($plain, $hash);
}

$action = $_GET['action'] ?? '';

// ── POST /api/auth.php?action=login ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $body  = getBody();
    $email = trim($body['email'] ?? '');
    $pass  = $body['password'] ?? '';
    $role  = $body['role'] ?? 'patient';   // 'patient' or 'staff'

    if (!$email || !$pass) {
        respond(['error' => 'Email and password are required.'], 400);
    }

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND role = ?');
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();

    if (!$user || !verifyPassword($pass, $user['password'])) {
        respond(['error' => 'Invalid email or password.'], 401);
    }

    unset($user['password']);
    $_SESSION['user'] = $user;
    respond(['success' => true, 'user' => $user]);
}

// ── POST /api/auth.php?action=signup ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'signup') {
    $body       = getBody();
    $name       = trim($body['name'] ?? '');
    $email      = trim($body['email'] ?? '');
    $pass       = $body['password'] ?? '';
    $student_id = trim($body['student_id'] ?? '');

    if (!$name || !$email || !$pass) {
        respond(['error' => 'Name, email and password are required.'], 400);
    }
    if (strlen($pass) < 6) {
        respond(['error' => 'Password must be at least 6 characters.'], 400);
    }

    $db   = getDB();
    $check = $db->prepare('SELECT id FROM users WHERE email = ?');
    $check->execute([$email]);
    if ($check->fetch()) {
        respond(['error' => 'Email already registered.'], 409);
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password, role, student_id) VALUES (?, ?, ?, "patient", ?)'
    );
    $stmt->execute([$name, $email, $hash, $student_id]);
    $id = $db->lastInsertId();

    $user = ['id' => $id, 'name' => $name, 'email' => $email, 'role' => 'patient', 'student_id' => $student_id];
    $_SESSION['user'] = $user;
    respond(['success' => true, 'user' => $user]);
}

// ── GET /api/auth.php?action=session ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'session') {
    if (!empty($_SESSION['user'])) {
        respond(['loggedIn' => true, 'user' => $_SESSION['user']]);
    }
    respond(['loggedIn' => false]);
}

// ── POST /api/auth.php?action=logout ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout') {
    session_destroy();
    respond(['success' => true]);
}

respond(['error' => 'Invalid action'], 400);
