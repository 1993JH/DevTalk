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
