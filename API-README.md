# DevTalk API Extension (Lab 8)

Adds a small JSON API and a JavaScript **Messages (beta)** client to DevTalk.
Merge it by copying the contents of this folder into the root of your DevTalk
repository; it only adds new files. Then follow the **Setup** section of the
Lab 8 handout (update `reset-db` in `composer.json`, ignore `storage/`, and run
`composer reset-db`).

## Files

```
public/api/index.php       # front controller: routes every /api/... URL
public/messages.php        # Messages (beta) page
public/assets/messages.js  # Messages client (stores its token in localStorage)
src/api/api.php            # shared helpers: JSON responses, who is calling
src/api/cors.php           # CORS policy
src/api/jwt.php            # JSON Web Token create/verify (HS256)
src/api/handlers.php       # one function per endpoint
database/api_seed.php      # messages table + seed data + JWT signing key
```

## Endpoints

| Method | Path | Body |
|---|---|---|
| POST | `/api/login` | `{"username": "...", "password": "..."}` |
| GET | `/api/me` | |
| GET | `/api/messages` | |
| GET | `/api/messages/{id}` | |
| GET | `/api/users/{id}` | |
| PATCH | `/api/users/me` | `{"bio": "..."}` |

Authentication: `Authorization: Bearer <token>` from `/api/login`, or the
DevTalk session cookie if you are logged in to the website.

The JWT signing key is created at `storage/jwt.key` the first time
`composer reset-db` runs. It is a secret: never commit it.

