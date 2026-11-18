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

function api_cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin !== '') {
        // "Works for every front end we'll ever build!"
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    }

    // Answer CORS preflight requests (sent by the browser before a PATCH,
    // a JSON POST, or any request with an Authorization header).
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 600');
        http_response_code(204);
        exit;
    }
}
