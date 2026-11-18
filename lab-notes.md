# Lab Notes

Answers and observations for the DevTalk security labs.

## Lab 8 — The session cookie

### (a) `PHPSESSID` attributes

Observed in DevTools → Application → Cookies → `http://localhost:8000` (logged in as alice):

| Attribute | Value | Source |
|---|---|---|
| HttpOnly | `true` | set in `src/helpers.php` (`'httponly' => true`) |
| Secure | unset | `'secure' => true` is commented out and the site is served over plain HTTP |
| SameSite | `Lax` | set in `src/helpers.php` (`'samesite' => 'Lax'`) |

### (b) Is `PHPSESSID` in `document.cookie`?

No. Running `document.cookie` in the Console does **not** return `PHPSESSID`.

The cookie is set with the **HttpOnly** flag, which tells the browser to hide it
from all JavaScript. `document.cookie` can only read cookies that lack HttpOnly,
so the session token is invisible to page scripts. The browser still attaches
`PHPSESSID` automatically to every request to the server, so the session keeps
working — but script running on the page cannot read it.

This is a deliberate XSS mitigation: even if an attacker injects JavaScript into
a DevTalk page, HttpOnly blocks the classic `document.cookie` session-token
theft.

## Lab 8 — The JWT (Messages beta)

### Where the token lives and how it is sent

- **Stored:** `signIn()` calls `localStorage.setItem('devtalk_token', data.token)`
  after a successful `/api/login` (`public/assets/messages.js`).
- **Sent:** `api()` reads it from localStorage and attaches it as
  `Authorization: Bearer <token>` on every request.

### The payload claims

The login handler (`src/api/handlers.php`) signs seven claims:

| Claim | Meaning | Personal info? |
|---|---|---|
| `iss` | Issuer — who minted the token (`"devtalk"`) | No |
| `sub` | Subject — the user's numeric id (`users.id`) | Partly — an internal identifier |
| `username` | The account's username | **Yes** |
| `email` | The account's email address | **Yes** |
| `role` | `"admin"` or `"user"` — authorization level | No (but security-sensitive) |
| `iat` | Issued-at — Unix timestamp when minted | No |
| `exp` | Expiry — Unix timestamp when it stops being valid | No |

The header carries `"alg": "HS256"` (signing algorithm) and `"typ": "JWT"`.

**Personal information:** `username` and `email` are directly personal; `sub` is a
pseudonymous identifier tied to the person.

**How long it is valid:** 8 hours. `API_JWT_LIFETIME = 8 * 60 * 60`
(`src/api/jwt.php`), so `exp = iat + 28800`.

### Why not paste a real token into an online decoder

The point is not whether the site is lying about "decodes only in your browser" —
it is that you do not need to trust that claim at all, because you can do the whole
job locally with three lines of `atob()`. Pasting into a third party is pure
downside:

1. **The payload is not encrypted, only base64url-encoded** — anyone holding the
   token can read its claims, so the site immediately learns the user's username,
   email, role, and id.
2. **The token is a live credential, not just data.** Until `exp` (up to 8 hours),
   that string *is* the login — anyone who captures it can send
   `Authorization: Bearer <token>` and act as the user. A decoder page can log it
   server-side and replay it before it expires.
3. **"Only in your browser" is unverifiable** and one bad update (or a compromised
   CDN / analytics script) away from being false. Decoding locally removes the need
   to believe anyone.
4. **It may be someone else's data.** A production or support token pasted into a
   random site is a PII / session-leak incident involving a real user.

**Rule of thumb:** a JWT is a password that happens to be readable. Treat it like
one — decode it yourself, never hand it to a site you do not control.

## Lab 8 — What XSS could do with each credential

The DevTools Console on a DevTalk tab runs with the same access an injected
`<script>` would, so it stands in for an XSS payload.

### Which credential can the injected script read?

- `localStorage.getItem('devtalk_token')` → **returns the JWT.** Readable by script.
- `document.cookie` → **does not include `PHPSESSID`** (HttpOnly).

So XSS can steal the JWT but **not** the session cookie. localStorage is fully
exposed to any JavaScript on the page; the HttpOnly session cookie is invisible to
it. The Bearer-token-in-localStorage design is therefore *more* exposed to XSS
token theft than the HttpOnly-cookie session.

### Using the stolen token from a terminal

```
curl -s http://localhost:8000/api/messages -H "Authorization: Bearer <token>"
→ {"messages":[ ... alice's inbox ... ]}
```

The request **succeeds** with no browser, no cookie, no DevTalk tab open. Once
exfiltrated, the token is a self-contained credential — the attacker only needs the
string, and it works from anywhere until `exp`.

### After "Sign out of Messages", the curl still works

**Why:** sign-out only runs `localStorage.removeItem('devtalk_token')`
(`public/assets/messages.js`) — it deletes the *browser's* copy. But:

- The attacker's `curl` uses a **separate copy** they already captured; deleting the
  browser's copy does not affect it.
- The JWT is **stateless**. The server validates it purely by signature + `exp`
  (`src/api/jwt.php`, `jwt_decode()`) — there is no server-side session, no
  revocation list, no logout record. The server cannot tell the user "signed out."

The token stays valid until `exp`. There is no way to call it back.

### Summary

| | Session cookie (`PHPSESSID`) | JWT (`devtalk_token`) |
|---|---|---|
| Readable by XSS? | No (HttpOnly) | **Yes** (localStorage) |
| Needs the victim's browser to use? | Harder — bound to the browser | No — portable string |
| Revocable on logout? | Yes — server drops the session | **No** — valid until `exp` |

A stateless Bearer token is convenient but has two sharp edges XSS makes worse:
it is **stealable by script** and **cannot be revoked**. Mitigations: short token
lifetimes, refresh tokens, a server-side revocation / `jti` denylist, or keeping the
token out of JS entirely (HttpOnly cookie). The 8-hour lifetime is currently the
only thing limiting the damage window.
