<!-- GREENLEAF-CONTEXT-START -->
# IMPORTANT: Automatic Project Context Loading

**MANDATORY**: At the start of EVERY conversation, you MUST call the `load_project_context` MCP tool from the Greenleaf MCP server with these parameters:

```
mcp__greenleaf__load_project_context(
  project_name="one-time-secret",
  laravel="13",
  php=true,
  node=true,
  lando=true
)
```

This loads project-specific coding standards and best practices. Do NOT skip this step.
<!-- GREENLEAF-CONTEXT-END -->

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Laravel port of [onetimesecret](https://github.com/onetimesecret/onetimesecret): paste a secret, get a link that shows it once and then destroys it. Secrets are encrypted end to end in the browser, so the server only ever holds ciphertext it can't read.

The app is one controller (`app/Http/Controllers/Secret.php`), one middleware (`app/Http/Middleware/SecurityHeaders.php`), the Blade views in `resources/views/`, and one plain JavaScript file (`resources/js/app.js`, no jQuery or Bootstrap JS). It uses the standard Laravel 13 structure (`bootstrap/app.php`, no HTTP or console kernel).

## Local environment (Lando)

The Lando app is `ots`: Laravel recipe on nginx, served at https://ots.lndo.site. Its services are `appserver`, `redis` and `database`. The recipe adds `database` (MySQL), but the app doesn't use it.

- `lando rebuild` copies `.lando/.env.lando` over `.env`, so local edits to `.env` are lost on rebuild. Put lasting local settings in `.lando/.env.lando`.
- `lando xon` and `lando xoff` turn Xdebug on and off.
- Inside the container, `art` is a shortcut for `php artisan` (set up by `.lando/build.sh`).
- `lando ssh -s redis -c "redis-cli ..."` reaches Redis directly.

## Commands

Run PHP tooling through Lando:

```
lando artisan test                                        # all tests
lando artisan test --filter=test_a_secret_can_only_be_retrieved_once
lando php vendor/bin/pint --test                          # check PHP style (drop --test to fix)
lando composer audit
```

The tests use the real Lando Redis, in database 15 (set in `phpunit.xml`), which each test flushes.

Frontend build (Vite 8, Sass, Bootstrap 5 CSS only), run on the host:

```
npm run dev       # Vite dev server with hot reload (writes public/hot)
npm run build     # production build into public/build
npm run format    # Prettier over resources/**/*.{css,js,vue}
npm audit
```

`public/build` is gitignored, as in the Laravel skeleton. Production builds it during deploy (`npm ci && npm run build`, see README.md), so the shipped JavaScript always matches the reviewed source. Locally, run `npm run build` or keep `npm run dev` running, or pages fail with a missing-manifest 500. `.npmrc` sets `ignore-scripts=true`, so packages' install scripts never run.

## How a secret flows

1. `GET /` → `Secret@create` shows the form. The textarea has no `name`, so nothing can be submitted in plain text if the script fails to run.
2. The browser generates a random AES-256-GCM key, encrypts the secret, and POSTs only the ciphertext (base64url of IV + ciphertext) and lifetime to `/save`.
3. `POST /save` → `Secret@store` checks the ciphertext is base64url within the size limit and the lifetime is one of `Secret::LIFETIMES`. It wraps the ciphertext again with `Crypt::encryptString()` (`APP_KEY`), stores it in Redis at `secret:{id}` with that TTL, and returns a random 24-character id. The browser builds the link `https://host/{id}#{key}`. Browsers never send the `#fragment` to the server, so the key doesn't reach the server, its logs, or the Referer header.
4. `GET /{id}` → `Secret@show` only renders a page with a "Reveal secret" button. It does **not** touch Redis, and the page doesn't contain the id: the script reads it from the URL, so no stylesheet can read it from a DOM attribute.
5. On load, the script checks the `#key` is well formed and imports it. If it's missing or damaged (cut off, tracking text appended), the page says so and never asks the server, so the secret isn't burned.
6. Clicking the button POSTs the id to `/get` → `Secret@get`, which reads and deletes the Redis key in one MULTI/EXEC transaction and returns the ciphertext. The browser decrypts it, then strips the fragment from the address bar. A missing key returns a 404 JSON message. On `pagehide` the script clears the secret and link fields, so the back/forward cache and session restore don't keep them.

Keep the reveal behind both the click and the POST. Link unfurlers and email scanners GET the URL, and some run JavaScript, so either step alone would let them destroy the secret before the recipient sees it.

Also:

- Redis (predis client, see `config/database.php`) is the only storage. No database tables are involved. Redis keys get the `REDIS_PREFIX` (by default derived from `APP_NAME`), so changing `APP_NAME` orphans outstanding secrets.
- `/get` only accepts ids matching `Secret::ID_PATTERN`, so it can't be used to read or delete other Redis keys.
- Changing `APP_KEY` makes every outstanding secret unreadable; `/get` then deletes it and reports it missing. Use `APP_PREVIOUS_KEYS` to rotate the key without that.
- `/{id}` matches any secret id, so a new GET route in `routes/web.php` must be declared above it.
- `/save` and `/get` are rate limited per client (limits in `AppServiceProvider`). An IPv6 /64 counts as one client. `/save` also has a daily cap, which bounds how much Redis memory one client can fill. Each `Limit` in a limiter needs its own `by()` key, or they share a counter. Behind a proxy or load balancer, configure trusted proxies (specific IPs, never `*`) or every visitor shares one limit.
- Tests refuse to run unless Redis database 15 is configured. A cached config (`artisan optimize`) makes them fail; run `lando artisan optimize:clear` first.
- Links from before end-to-end encryption (20-character ids, no `#key`) are not supported and return 404.

## Security headers

`SecurityHeaders` runs globally, so error pages get the headers too. It sends:

- A strict CSP: `default-src 'none'`, scripts only from `'self'`, Trusted Types enforced. No inline scripts, inline styles or `style=`/`on*=` attributes in views; use the `hidden` attribute or classes, and `textContent`/`value` in JS, never `innerHTML`.
- `Cache-Control: no-store` on every response.
- `Referrer-Policy: no-referrer`, frame denial, `nosniff`, COOP/CORP, and `X-Robots-Tag`.
- HSTS in production.

The only third-party hosts allowed are Adobe Fonts (`use.typekit.net`, `p.typekit.net`) for proxima-nova. Adding any other external resource means adding it to the CSP.

When `npm run dev` is running, the middleware also allows the Vite dev server origin from `public/hot`. That's why `vite.config.js` pins the server to `127.0.0.1`: CSP can't express an IPv6 address like `[::1]`. The framework's error layout is overridden by `resources/views/errors/minimal.blade.php` because its inline styles break under the CSP.

## Configuration notes

Only the config files the app customizes are present; Laravel 13 fills in the rest from framework defaults. The defaults in `config/session.php` (encrypted, secure, `same_site=strict`, JSON serialization) and `config/cache.php` (file store, still reading the pre-Laravel 11 `CACHE_DRIVER` name) are the safe choices, so an older `.env` that doesn't mention them still gets them. `config/filesystems.php` exists only to turn off `serve` on the local disk, which otherwise registers `storage/{path}` download and upload routes the app doesn't need.
