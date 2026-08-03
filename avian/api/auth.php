<?php
// In-page admin authentication endpoint. It never emits WWW-Authenticate;
// callers receive JSON so the frontend can retain its styled password prompt.

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/auth-session.php';

$action = (string)($_GET['action'] ?? 'status');

if ($action === 'status' || $action === 'verify') {
    if (!avian_admin_authenticated()) {
        http_response_code(401);
        echo json_encode(['authenticated' => false]);
        exit;
    }
    echo json_encode(['authenticated' => true]);
    exit;
}

if ($action === 'login') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required']);
        exit;
    }
    avian_require_admin_request_header();
    $body = json_decode((string)file_get_contents('php://input'), true);
    $password = is_array($body) ? (string)($body['password'] ?? '') : '';
    $configured = avian_admin_password();
    if ($configured === null || $configured === '') {
        http_response_code(503);
        echo json_encode(['error' => 'admin password not configured']);
        exit;
    }
    if (!hash_equals($configured, $password)) {
        // Small fixed delay slows online guessing without identifying which
        // part of the credential was wrong.
        usleep(250000);
        http_response_code(401);
        echo json_encode(['error' => 'invalid password']);
        exit;
    }
    avian_start_admin_session();
    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    $_SESSION['authenticated_at'] = time();
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'logout') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required']);
        exit;
    }
    avian_require_admin_request_header();
    avian_start_admin_session();
    $_SESSION = [];
    session_destroy();
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'unknown action']);
