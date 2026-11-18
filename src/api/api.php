<?php
/**
 * DevTalk API extension — shared helpers for the JSON API.
 *
 * Including DevTalk's own src/helpers.php means every API response gets the
 * same security headers and session-cookie settings as the rest of the site,
 * and the API can recognise a user who is already logged in to the website.
 */

require_once __DIR__ . '/../helpers.php';   // db(), session, security headers
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/cors.php';

/** Send a JSON response and stop. */
function api_json(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function api_error(int $status, string $message): never
{
    api_json(['error' => $message], $status);
}

/** Decode a JSON request body into an array. */
function api_body(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) {
        api_error(400, 'Request body must be a JSON object.');
    }
    return $data;
}

function api_bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = $value;
            }
        }
    }
    return preg_match('/^Bearer\s+(\S+)$/i', $header, $m) ? $m[1] : null;
}

function api_find_user(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Work out who is calling the API.
 *
 * 1. A JWT in "Authorization: Bearer <token>" (used by the Messages client), or
 * 2. the DevTalk session cookie, so pages on the main website can call the
 *    API for a user who logged in through login.php.
 */
function api_user(): ?array
{
    $token = api_bearer_token();
    if ($token !== null) {
        $claims = jwt_decode($token);
        return $claims ? api_find_user((int) $claims['sub']) : null;
    }
    if (isset($_SESSION['user_id'])) {
        return api_find_user((int) $_SESSION['user_id']);
    }
    return null;
}

function api_require_user(): array
{
    $user = api_user();
    if ($user === null) {
        api_error(401, api_bearer_token() !== null ? 'Invalid or expired token.' : 'Authentication required.');
    }
    return $user;
}

function api_role(array $user): string
{
    return !empty($user['is_admin']) ? 'admin' : 'user';
}

/** The caller's own profile, as returned by /api/me. */
function api_profile(array $user): array
{
    return [
        'id'         => (int) $user['id'],
        'username'   => $user['username'],
        'email'      => $user['email'],
        'bio'        => $user['bio'],
        'role'       => api_role($user),
        'created_at' => $user['created_at'],
    ];
}

/**
 * Check a password against the stored value. Works whether your Lab 5 copy
 * stores password_hash() hashes (bonus) or the original plaintext.
 */
function api_password_matches(string $given, string $stored): bool
{
    if (password_get_info($stored)['algo'] !== null) {
        return password_verify($given, $stored);
    }
    return hash_equals($stored, $given);
}
