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
