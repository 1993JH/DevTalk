<?php
/**
 * DevTalk API extension — route handlers.
 */

/** POST /api/login   {"username": "...", "password": "..."} */
function api_login(): never
{
    $body     = api_body();
    $username = trim((string) ($body['username'] ?? ''));
    $password = (string) ($body['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        api_error(404, 'No account with that username.');
    }
    if (!api_password_matches($password, $user['password'])) {
        api_error(401, 'Incorrect password.');
    }

    $now   = time();
    $token = jwt_encode([
        'iss'      => 'devtalk',
        'sub'      => (int) $user['id'],
        'username' => $user['username'],
        'email'    => $user['email'],
        'role'     => api_role($user),
        'iat'      => $now,
        'exp'      => $now + API_JWT_LIFETIME,
    ]);

    api_json([
        'token'      => $token,
        'token_type' => 'Bearer',
        'expires_in' => API_JWT_LIFETIME,
        'user'       => ['id' => (int) $user['id'], 'username' => $user['username'], 'role' => api_role($user)],
    ]);
}

/** GET /api/me */
function api_get_me(): never
{
    api_json(api_profile(api_require_user()));
}

/** PATCH /api/users/me   {"bio": "..."} */
function api_update_me(): never
{
    $me   = api_require_user();
    $body = api_body();

    // Accept the friendlier "role" field used in API responses.
    if (array_key_exists('role', $body)) {
        $body['is_admin'] = $body['role'] === 'admin' ? 1 : 0;
        unset($body['role']);
    }

    // Update every field the client sent that is a real column.
    $columns = array_column(db()->query('PRAGMA table_info(users)')->fetchAll(), 'name');
    $sets = [];
    $values = [];
    foreach ($body as $field => $value) {
        if ($field !== 'id' && in_array($field, $columns, true)) {
            $sets[]   = "$field = ?";
            $values[] = $value;
        }
    }
    if (!$sets) {
        api_error(400, 'Nothing to update.');
    }
    $values[] = $me['id'];
    db()->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);

    api_json(api_profile(api_find_user((int) $me['id'])));
}

/** GET /api/users/{id} */
function api_get_user(int $id): never
{
    api_require_user();
    $user = api_find_user($id) ?? api_error(404, 'User not found.');
    $user['role'] = api_role($user);
    api_json($user);
}

/** GET /api/messages — the caller's inbox and sent messages */
function api_list_messages(): never
{
    $me   = api_require_user();
    $stmt = db()->prepare(
        'SELECT m.id, s.username AS "from", r.username AS "to", m.subject, m.body, m.created_at
           FROM messages m
           JOIN users s ON s.id = m.sender_id
           JOIN users r ON r.id = m.recipient_id
          WHERE m.sender_id = ? OR m.recipient_id = ?
          ORDER BY m.created_at DESC, m.id DESC'
    );
    $stmt->execute([$me['id'], $me['id']]);
    api_json(['messages' => $stmt->fetchAll()]);
}

/** GET /api/messages/{id} */
function api_get_message(int $id): never
{
    api_require_user();
    $stmt = db()->prepare(
        'SELECT m.id, s.username AS "from", r.username AS "to", m.subject, m.body, m.created_at
           FROM messages m
           JOIN users s ON s.id = m.sender_id
           JOIN users r ON r.id = m.recipient_id
          WHERE m.id = ?'
    );
    $stmt->execute([$id]);
    $message = $stmt->fetch() ?: api_error(404, 'Message not found.');
    api_json($message);
}
