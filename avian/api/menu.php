<?php
// AvianVisitors - drawer menu items.
//
// Returns the list of links shown in the side drawer when a user clicks
// the menu button. The live JS expects {items: [{label, href, native}]}.
//
// A failed session check returns JSON 401 without a WWW-Authenticate header,
// allowing the frontend to show its styled password field instead of a
// browser-native Basic-auth dialog.

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/auth-session.php';
avian_require_admin_session();

// All four items are in-app overlays. `native: true` tells the FE to
// route via `#admin=<section>` rather than opening a new window. We
// deliberately don't link out to BirdNET-Pi's stock pages: the hardened
// Caddy configuration disables them, and the github link lives in the drawer
// footer next to "built by teddy".
echo json_encode([
    'items' => [
        ['label' => 'settings', 'href' => '/#admin=settings', 'native' => true],
        ['label' => 'system',   'href' => '/#admin=system',   'native' => true],
        ['label' => 'logs',     'href' => '/#admin=logs',     'native' => true],
        ['label' => 'tools',    'href' => '/#admin=tools',    'native' => true],
    ],
]);
