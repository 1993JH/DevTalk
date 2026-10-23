# DevTalk — Patch Log

Patches applied during Lab 5. Each entry maps back to a finding in the Lab 4
Vulnerability Log.

---

## Patch #1: Parameterize login query (A05 Injection — SQLi)

**Lab 4 finding:** #1 — A05 Injection — SQL (`public/login.php:9-10`)

### Exploit (re-confirmed before patch)
`login.php` built its query by concatenating the raw POST fields:

```php
$sql = "SELECT * FROM users
        WHERE username = '$username' AND password = '$password'";
$row = db()->query($sql)->fetch();
```

Submitting `username = admin'--` with any password commented out the password
check and logged in as `admin`:

```
POST username=admin'--  password=x   ->  302 redirect to index.php
index.php then shows profile.php?id=1 (admin) — authentication bypassed
```

A lone `'` also triggered a `PDOException` whose handler echoed the full SQL
statement back to the browser, confirming the injection point.

### Root problem
Not the auth bypass itself, but the cause: **user input was concatenated into
the SQL string instead of being passed as a bound parameter**, so input could
alter the query's logic. A secondary issue was the error handler leaking the
raw SQL in the response.

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

The `catch` block now returns a generic message instead of echoing the query,
removing the information leak.

### Re-test (after patch)
- `username=admin'--` / `password=x` → **200, no redirect**, page still shows
  "Invalid username or password." and the nav still offers **Log in / Register**
  (not authenticated). See `docs/screenshots/patch1-login-sqli-blocked.png`.
- `username='` → generic "Invalid username or password." with no SQL leaked.
- **Normal use:** `admin` / `Sup3rSecret!` → 302 redirect to `index.php`,
  logged in as admin (`profile.php?id=1`). Login still works for valid users.

### Notes / out of scope
Passwords remain stored in plaintext (seeded that way by the instructor,
Lab 4 finding on A02); moving to `password_hash()`/`password_verify()` is a
separate patch and is intentionally not bundled here.

---

## Patch #1 (cont.): Parameterize search query (A05 Injection — SQLi)

**Lab 4 finding:** #1 — A05 Injection — SQL (additional injection point in the
same class as the login SQLi; `public/search.php:9-10`)

### Exploit (re-confirmed before patch)
`search.php` concatenated the raw `q` GET param into a `LIKE` clause:

```php
$sql = "SELECT id, title, body FROM topics
        WHERE title LIKE '%$q%' OR body LIKE '%$q%' ORDER BY created_at DESC";
$results = db()->query($sql)->fetchAll();
```

Searching `q=%' OR '1'='1` closed the `LIKE` string and injected an always-true
condition, returning **all 5 topics** instead of matches (normal `q=php` → 1).
A lone `'` broke the query and the handler echoed the full SQL back to the page.

### Root problem
User input was concatenated into the SQL string instead of being bound as a
parameter, so input could alter the query's logic. The error handler also
leaked the raw SQL.

### Fix
Prepared statement with the `%…%` wildcards wrapped around the bound value, so
`q` is always data, never SQL:

```php
$like = '%' . $q . '%';
$stmt = db()->prepare(
    'SELECT id, title, body FROM topics
     WHERE title LIKE ? OR body LIKE ? ORDER BY created_at DESC'
);
$stmt->execute([$like, $like]);
$results = $stmt->fetchAll();
```

The `catch` block now returns a generic message instead of echoing the query.

### Re-test (after patch)
- `q=%' OR '1'='1` → **0 results**, "No matches found" (payload treated as a
  literal string). See `docs/screenshots/patch1-search-sqli-blocked.png`.
- `q='` → generic "Search failed." with no SQL leaked.
- **Normal use:** `q=php` → matching topic(s) still returned. Search still works.

### Notes / out of scope
The `Results for: <?= $q ?>` line reflects `q` unescaped (reflected XSS); that
is a separate Lab 4 finding and is fixed in Patch #2.

---

## Patch #2: Escape user-generated output (A05 Injection — XSS)

**Lab 4 finding:** #2 — A05 Injection — Cross-Site Scripting
(`public/topic.php:50` topic body, `public/topic.php:81` comment body,
`public/search.php:30` reflected query; stored case noted at
`database/schema.sql:23`).

### Exploit (re-confirmed before patch)
Topic/comment bodies were echoed raw, and the search page reflected `q` raw:

```php
<div class="topic-body"><?= $topic['body'] ?></div>   // topic.php:50
<div class="mt-1"><?= $c['body'] ?></div>             // topic.php:81
<p ...>Results for: <?= $q ?></p>                      // search.php:30
```

A topic or comment whose body was `<script>alert(1)</script>` executed for
every visitor (stored XSS); `search.php?q=<script>alert(3)</script>` executed
reflected. Confirmed on a seeded topic containing `<script>alert(1)</script>`.

### Root problem
User-controlled data was written into HTML without output encoding, so markup
in the data was interpreted as part of the page. The schema even documents the
"HTML allowed" body as the intended stored-XSS sink; that "feature" *is* the
vulnerability.

### Fix
Encode every user-controlled value on output. Topic and comment bodies now go
through `nl2br(htmlspecialchars(...))` (line breaks preserved, markup inert),
and the search page wraps `q` in `htmlspecialchars(...)`:

```php
<div class="topic-body"><?= nl2br(htmlspecialchars($topic['body'])) ?></div>
<div class="mt-1"><?= nl2br(htmlspecialchars($c['body'])) ?></div>
<p ...>Results for: <?= htmlspecialchars($q) ?></p>
```

The "(HTML allowed)" hints on the create/edit forms were corrected to
"(plain text)". Images are still supported through the `image_url` preview.

### Re-test (after patch)
- Topic `#6` body `<script>alert(1)</script>` and its comment
  `<script>alert(2)</script>` → rendered as **literal text, 0 raw `<script>`
  tags, 2 escaped**; no script executes. See
  `docs/screenshots/patch2-xss-blocked.png`.
- `search.php?q=<script>alert(3)</script>` → reflected as escaped text.
- **Normal use:** ordinary topic/comment text displays normally with line
  breaks preserved.

### Notes / out of scope
Existing rows stored before the patch are still rendered safely because
encoding happens on output, not input.

---

## Patch #3: Enforce ownership on state-changing actions (A01 — IDOR)

**Lab 4 finding:** #3 — A01 Broken Access Control — IDOR (missing ownership
check): `public/delete_topic.php:5-8`, `public/edit_topic.php:5-27`,
`public/delete_comment.php:5-9`, and the bio update in `public/profile.php:6-12`.

### Exploit (re-confirmed before patch)
These endpoints checked only that *a* user was logged in, never that the user
*owned* the object. Logged in as `bob`, every one of these succeeded against
objects owned by others:

```
GET  delete_topic.php?id=1          -> deletes admin's topic
GET  edit_topic.php?id=1 (+POST)    -> edits admin's topic
GET  delete_comment.php?id=1        -> deletes alice's comment
POST profile.php  user_id=1&bio=... -> overwrites admin's bio (IDOR via POST field)
```

### Root problem
Authentication without **object-level authorization**: the target id came
straight from the request and was trusted. For the bio, the row updated was
taken from a POST `user_id`, so any value could be supplied.

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
`current_user_id()`'s own bio, and the bio form is shown only on one's own
profile.

### Re-test (after patch)  — all as `bob`
- `delete_topic.php?id=1` → **403**, topic 1 still present.
- `edit_topic.php?id=1` → **403**.
- `delete_comment.php?id=1` → **403**, comment 1 still present.
- `profile.php` POST `user_id=1&bio=HACKED` → admin bio **unchanged**; only
  bob's own bio changed. See `docs/screenshots/patch3-idor-blocked.png`.
- **Normal use:** `bob` editing his own topic 3 → **200** (still works).

---

## Patch #4: Anti-CSRF tokens on all state changes (A01 — CSRF)

**Lab 4 finding:** #4 — A01 Broken Access Control — CSRF (no anti-forgery
token; destructive actions over GET): `delete_topic.php` and
`delete_comment.php` triggered by plain GET links (`topic.php:57`,
`topic.php:78`), plus the POST handlers in `create_topic.php`,
`edit_topic.php`, `add_comment.php`, and `profile.php`.

### Exploit (re-confirmed before patch)
Deletes were GET requests with no token, so a victim merely loading:

```html
<img src="http://localhost:8000/delete_topic.php?id=3">
```

deleted their topic. None of the POST handlers checked a token either, so any
cross-site form could drive them.

### Root problem
State-changing requests carried nothing an attacker's page could not forge:
destructive actions used GET, and no request proved it originated from the
app's own forms.

### Fix
Added `csrf_token()`, `csrf_field()`, and `require_csrf()` (`src/helpers.php`),
using a per-session random token compared with `hash_equals()`.

- `delete_topic.php` / `delete_comment.php` now **require POST**, call
  `require_csrf()`, and read ids from POST. The topic/comment "Delete" controls
  in `topic.php` became POST forms carrying the token.
- `create_topic.php`, `edit_topic.php`, `add_comment.php`, and `profile.php`
  call `require_csrf()` at the top of their POST branch, and every form embeds
  `csrf_field()`.

### Re-test (after patch) — as `bob`, target his own topic 3
- `GET delete_topic.php?id=3` → **302 redirect, no delete** (topic still there).
- `POST delete_topic.php id=3` with **no token** → **403** "Invalid or missing
  security token." See `docs/screenshots/patch4-csrf-blocked.png`.
- `POST` with the **valid token** read from the page → **302, topic deleted**.
- **Normal use:** the valid-token delete (and the token-bearing create/edit/
  comment/bio forms) work exactly as before.

---

## Patch #5: Validate server-side image fetch (A01 — SSRF)

**Lab 4 finding:** #5 — A01 Broken Access Control — SSRF (unvalidated
server-side fetch): `public/fetch_image.php:4-15`.

### Exploit (re-confirmed before patch)
`fetch_image.php` passed any `url` straight to `file_get_contents()` with
`follow_location` on, so the server fetched whatever it was told:

```
fetch_image.php?url=http://127.0.0.1:8123/login.php   -> internal app
fetch_image.php?url=http://169.254.169.254/latest/... -> cloud metadata
fetch_image.php?url=file:///etc/passwd                -> local file read
fetch_image.php?url=gopher://127.0.0.1:6379/...       -> protocol smuggling
```

### Root problem
No validation of the destination: any scheme, host, or address was accepted,
turning the preview feature into a request proxy into the server's own network.

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

### Re-test (after patch)
- `url=http://127.0.0.1:8123/...` → **403**; `http://localhost:...` → **403**.
- `url=http://169.254.169.254/latest/meta-data/...` → **403** "Refusing to
  fetch a private or reserved address." See
  `docs/screenshots/patch5-ssrf-blocked.png`.
- `url=http://10.0.0.1/` → **403**; `file:///etc/passwd` and `gopher://…` →
  **400** "Only http(s) image URLs are allowed."
- **Normal use:** a real public image URL (`gravatar.com/...png`) → **200**,
  `Content-Type: image/png` — preview still works.

### Notes / out of scope
A determined attacker could still attempt DNS rebinding (resolve public on the
check, internal on the fetch — a TOCTOU window). Fully closing that needs
pinning the validated IP for the connection; it is noted but not bundled here.
