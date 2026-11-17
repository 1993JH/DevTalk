<?php
/**
 * DevTalk API extension — Cross-Origin Resource Sharing (CORS) policy.
 *
 * Called once, at the top of public/api/index.php, before any route runs.
 *
 * Background: DevTalk's product team plans a separate JavaScript front end
 * that will be developed at http://localhost:5173 and needs to call this API
 * from that origin. While testing it, a developer kept hitting the browser
 * error "The value of the 'Access-Control-Allow-Origin' header must not be
 * the wildcard '*' when the request's credentials mode is 'include'", and
 * "fixed" it with the code below.
 *
 * LAB 8, PART A: this is the file you fix.
 */

/**
 * Explicit allow-list of origins permitted to read API responses from a
 * browser. DevTalk's own pages (including messages.php) are same-origin and
 * never appear here. The only cross-origin caller is the planned front end.
 */
const API_ALLOWED_ORIGINS = [
    'http://localhost:5173',
];

function api_cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // A cache must never reuse one origin's CORS response for another, so every
    // response varies on Origin — whether or not we end up allowing it.
    header('Vary: Origin');

    // Exact string match against the allow-list. in_array() with strict mode is
    // a byte-for-byte comparison: no substring, prefix/suffix, or regex tricks,
    // and the literal string "null" is not on the list, so a null origin (from a
    // sandboxed iframe, a data: URL, etc.) can never be allowed.
    $allowed = $origin !== '' && in_array($origin, API_ALLOWED_ORIGINS, true);

    if ($allowed) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    }

    // Answer CORS preflight requests (sent by the browser before a PATCH,
    // a JSON POST, or any request with an Authorization header). Only an
    // allowed origin receives the Access-Control-* method/header permissions;
    // any other origin gets a bare 204 with no CORS headers.
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        if ($allowed) {
            header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Max-Age: 600');
        }
        http_response_code(204);
        exit;
    }
}
