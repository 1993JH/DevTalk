# DevTalk — Patch Log

Patches applied during Lab 5. Each entry maps back to a finding in the Lab 4
Vulnerability Log.

---

## Patch #1: Parameterize login query (A05 Injection — SQLi)

**Lab 4 finding:** #1 — A05 Injection — SQL (`public/login.php:9-10`)

### Fix
Replaced the concatenated `query()` with a prepared statement and bound both
values as parameters, so the input is always treated as data, never SQL:

```php
$stmt = db()->prepare(
    'SELECT * FROM users WHERE username = ? AND password = ?'
);
$stmt->execute([$username, $password]);
$row = $stmt->fetch();
```


## Patch #2: Escape user-generated output (A05 Injection — XSS)

**Lab 4 finding:** #2 — A05 Injection — Cross-Site Scripting
(`public/topic.php:50` topic body, `public/topic.php:81` comment body,
`public/search.php:30` reflected query; stored case noted at
`database/schema.sql:23`).

### Fix
Encode every user-controlled value on output. Topic and comment bodies now go
through `nl2br(htmlspecialchars(...))` (line breaks preserved, markup inert),
and the search page wraps `q` in `htmlspecialchars(...)`:

```php
<div class="topic-body"><?= nl2br(htmlspecialchars($topic['body'])) ?></div>
<div class="mt-1"><?= nl2br(htmlspecialchars($c['body'])) ?></div>
<p ...>Results for: <?= htmlspecialchars($q) ?></p>
```

## Patch #3: Enforce ownership on state-changing actions (A01 — IDOR)

**Lab 4 finding:** #3 — A01 Broken Access Control — IDOR (missing ownership
check): `public/delete_topic.php:5-8`, `public/edit_topic.php:5-27`,
`public/delete_comment.php:5-9`, and the bio update in `public/profile.php:6-12`.


### Fix
Added `current_user_id()`, `is_admin()`, and `require_owner($ownerId)` helpers
(`src/helpers.php`). Each endpoint now loads the row, and calls
`require_owner()` (owner or admin, else HTTP 403) before mutating:

```php
$topic = /* SELECT user_id FROM topics WHERE id = ? */;
require_owner((int) $topic['user_id']);   // delete_topic / edit_topic
$comment = /* SELECT user_id FROM comments WHERE id = ? */;
require_owner((int) $comment['user_id']); // delete_comment
```

`profile.php` now ignores any posted `user_id` and always updates
`current_user_id()`'s own bio, and the bio form is shown only on one's own profile.

## Patch #4: Anti-CSRF tokens on all state changes (A01 — CSRF)

**Lab 4 finding:** #4 — A01 Broken Access Control — CSRF (no anti-forgery
token; destructive actions over GET): `delete_topic.php` and
`delete_comment.php` triggered by plain GET links (`topic.php:57`,
`topic.php:78`), plus the POST handlers in `create_topic.php`,
`edit_topic.php`, `add_comment.php`, and `profile.php`.


### Fix
Added `csrf_token()`, `csrf_field()`, and `require_csrf()` (`src/helpers.php`),
using a per-session random token compared with `hash_equals()`.

- `delete_topic.php` / `delete_comment.php` now **require POST**, call
  `require_csrf()`, and read ids from POST. The topic/comment "Delete" controls
  in `topic.php` became POST forms carrying the token.
- `create_topic.php`, `edit_topic.php`, `add_comment.php`, and `profile.php`
  call `require_csrf()` at the top of their POST branch, and every form embeds
  `csrf_field()`.


## Patch #5: Validate server-side image fetch (A01 — SSRF)

**Lab 4 finding:** #5 — A01 Broken Access Control — SSRF (unvalidated
server-side fetch): `public/fetch_image.php:4-15`.


### Fix
`fetch_image.php` now, before fetching:
- requires the scheme to be `http`/`https` (blocks `file://`, `gopher://`, …);
- resolves the host (`gethostbynamel()` + `dns_get_record()`) and rejects the
  request if **any** resolved IP is private or reserved via
  `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE` (covers loopback,
  RFC1918, link-local, and `169.254.169.254`);
- disables redirects (`follow_location=0`, `max_redirects=0`) so a public URL
  cannot bounce to an internal one;
- returns the body only if its sniffed MIME type is `image/*`.

