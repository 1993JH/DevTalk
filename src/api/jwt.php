<?php
/**
 * DevTalk API extension — minimal JSON Web Token (JWT) support, HS256 only.
 *
 * A JWT has three base64url-encoded parts:  header . payload . signature
 * The header and payload are only ENCODED, not encrypted: anyone holding the
 * token can read them. The signature (an HMAC made with a server-side secret)
 * is what stops a client from changing them.
 */

define('API_JWT_KEY_FILE', __DIR__ . '/../../storage/jwt.key');
define('API_JWT_LIFETIME', 8 * 60 * 60);   // 8 hours

function jwt_b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function jwt_b64url_decode(string $data): string|false
{
    $pad = strlen($data) % 4;
    if ($pad) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return base64_decode(strtr($data, '-_', '+/'), true);
}

function jwt_secret(): string
{
    // The signing key lives outside public/ and is created by
    // `composer reset-db` (database/api_seed.php). It is never committed.
    $key = is_readable(API_JWT_KEY_FILE) ? trim((string) file_get_contents(API_JWT_KEY_FILE)) : '';
    if (strlen($key) < 32) {
        throw new RuntimeException('JWT signing key missing. Run: composer reset-db');
    }
    return $key;
}

function jwt_encode(array $claims): string
{
    $header  = jwt_b64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = jwt_b64url_encode(json_encode($claims, JSON_UNESCAPED_SLASHES));
    $sig     = hash_hmac('sha256', "$header.$payload", jwt_secret(), true);
    return "$header.$payload." . jwt_b64url_encode($sig);
}

/** Returns the token's claims, or null if it is malformed, forged or expired. */
function jwt_decode(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$h, $p, $s] = $parts;

    $header = json_decode((string) jwt_b64url_decode($h), true);
    // Only accept the one algorithm we issue. Never trust "alg": "none".
    if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
        return null;
    }

    $expected = hash_hmac('sha256', "$h.$p", jwt_secret(), true);
    $given    = jwt_b64url_decode($s);
    if ($given === false || !hash_equals($expected, $given)) {
        return null;   // payload was changed, or signed with a different key
    }

    $claims = json_decode((string) jwt_b64url_decode($p), true);
    if (!is_array($claims) || ($claims['exp'] ?? 0) < time()) {
        return null;
    }
    return $claims;
}
