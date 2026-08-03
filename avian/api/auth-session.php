<?php
// Shared session authentication for AvianVisitors' in-page admin lock.
// This intentionally returns JSON 401 responses without WWW-Authenticate so
// browsers never replace the custom UI with a native Basic-auth dialog.

declare(strict_types=1);

const AVIAN_ADMIN_SESSION = 'avian_admin';

function avian_request_is_https(): bool {
    if (isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    $forwarded = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return in_array('https', array_map('trim', explode(',', $forwarded)), true);
}

function avian_start_admin_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name(AVIAN_ADMIN_SESSION);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => avian_request_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_cache_limiter('');
    session_start();
}

function avian_admin_password(): ?string {
    $path = dirname(__DIR__, 2) . '/birdnet.conf';
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) return null;
    foreach ($lines as $line) {
        if (!preg_match('/^\s*CADDY_PWD\s*=\s*(.*?)\s*$/', $line, $m)) continue;
        $value = $m[1];
        if (strlen($value) >= 2 && $value[0] === "'" && substr($value, -1) === "'") {
            return substr($value, 1, -1);
        }
        if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
            return stripcslashes(substr($value, 1, -1));
        }
        return $value;
    }
    return null;
}

function avian_admin_authenticated(): bool {
    avian_start_admin_session();
    return ($_SESSION['authenticated'] ?? false) === true;
}

function avian_require_admin_session(): void {
    if (avian_admin_authenticated()) return;
    http_response_code(401);
    echo json_encode(['error' => 'authentication required']);
    exit;
}

function avian_require_admin_request_header(): void {
    // A non-simple header forces a cross-origin browser request to preflight.
    // This service sends no CORS permission, so forms on another origin cannot
    // submit authenticated state changes using the admin's session cookie.
    if (($_SERVER['HTTP_X_AVIAN_ADMIN'] ?? '') !== '1') {
        http_response_code(403);
        echo json_encode(['error' => 'admin request header required']);
        exit;
    }
}
