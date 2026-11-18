<?php
/**
 * DevTalk API extension — front controller for every /api/... URL.
 *
 * PHP's built-in server (composer start) sends any /api/... path that is not a
 * real file to this script, so /api/users/2 arrives here with the path intact.
 */

require_once __DIR__ . '/../../src/api/api.php';
require_once __DIR__ . '/../../src/api/handlers.php';

api_cors();   // CORS policy: src/api/cors.php

$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
$path   = '/' . trim(preg_replace('#^/api#', '', $path), '/');

try {
    switch (true) {
        case $method === 'POST'  && $path === '/login':
            api_login();
        case $method === 'GET'   && $path === '/me':
            api_get_me();
        case $method === 'PATCH' && $path === '/users/me':
            api_update_me();
        case $method === 'GET'   && (bool) preg_match('#^/users/(\d+)$#', $path, $m):
            api_get_user((int) $m[1]);
        case $method === 'GET'   && $path === '/messages':
            api_list_messages();
        case $method === 'GET'   && (bool) preg_match('#^/messages/(\d+)$#', $path, $m):
            api_get_message((int) $m[1]);
        default:
            api_error(404, 'No such endpoint.');
    }
} catch (Throwable $e) {
    error_log('DevTalk API error: ' . $e->getMessage());
    api_error(500, 'Server error.');
}
