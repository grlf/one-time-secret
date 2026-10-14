<!-- GREENLEAF-CONTEXT-START -->
# IMPORTANT: Automatic Project Context Loading

**MANDATORY**: At the start of EVERY conversation, you MUST call the `load_project_context` MCP tool from the Greenleaf MCP server with these parameters:

```
mcp__greenleaf__load_project_context(
  project_name="one-time-secret",
  laravel="10",
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

A Laravel port of [onetimesecret](https://github.com/onetimesecret/onetimesecret): paste a secret, get a link that shows it once and then destroys it. The whole app is one controller (`app/Http/Controllers/Secret.php`), two Blade views (`resources/views/secret/`) and a small jQuery file (`resources/js/app.js`).

## Local environment (Lando)

The Lando app is `ots`: Laravel recipe on nginx, served at https://ots.lndo.site. Its services are `appserver`, `redis` and `database`. The recipe adds `database` (MySQL), but the app doesn't use it.

- `lando rebuild` copies `.lando/.env.lando` over `.env`, so local edits to `.env` are lost on rebuild. Put lasting local settings in `.lando/.env.lando`.
- `lando xon` and `lando xoff` turn Xdebug on and off.
- Inside the container, `art` is a shortcut for `php artisan` (set up by `.lando/build.sh`).

## Commands

Run PHP tooling through Lando:

```
lando artisan test                          # all tests
lando artisan test --filter=testBasicTest   # a single test
lando php vendor/bin/phpunit                # PHPUnit directly
lando php vendor/bin/pint --test            # check PHP style (drop --test to fix)
```

The tests pass but print deprecation noise. `phpunit.xml` uses the schema from before PHPUnit 10, and Laravel 10 vendor code triggers PHP 8.4 deprecations.

Frontend build (Laravel Mix 6, Bootstrap 5, jQuery), run on the host:

```
npm run dev
npm run watch
npm run production
npm run format    # Prettier over resources/**/*.{css,js,vue}
```

The compiled `public/js/app.js`, `public/css/app.css` and `public/mix-manifest.json` are committed. After changing anything in `resources/js` or `resources/sass`, run `npm run production` and commit the output. That build adds the `?id=` version hashes the committed manifest uses.

## How a secret flows

1. `GET /` → `Secret@create` shows the form.
2. `POST /save` → `Secret@store` encrypts the secret with Laravel's `encrypt()` (keyed by `APP_KEY`). It stores the result in Redis under a random 20-character key with a TTL (the form offers 1 hour to 1 week; the controller caps it at 604800 seconds). It then redirects back with the key in the `success` flash, and the create view turns that key into the share link.
3. `GET /{key}` → `Secret@show` only renders a page with the key in a hidden form. It does **not** touch Redis.
4. On page load, the JS POSTs the key to `/get` → `Secret@get`, which reads the value from Redis, deletes it, decrypts it and returns it as plain text. A missing key returns a 404 message.

Keep the reveal behind that POST step. Link unfurlers and email scanners that GET the URL would otherwise destroy the secret before the recipient sees it.

Also:

- Redis (predis client, see `config/database.php`) is the only storage. No database tables are involved.
- Changing `APP_KEY` makes every outstanding secret undecryptable: `decrypt()` throws.
- `Secret@get` reads and then deletes with two separate Redis calls (GET, then DEL), so the operation isn't atomic.
- `/{key}` catches every GET path, so a new GET route in `routes/web.php` must be declared above it.

## Legacy skeleton on Laravel 10

The app skeleton dates from Laravel 5.8. `composer.json` was bumped to Laravel 10 and PHP 8.2 without migrating the app structure. Expect:

- String controller routes (`'Secret@create'`), which work because `RouteServiceProvider` still sets `$namespace`.
- `database/seeds` instead of `database/seeders`.
- An old-style `app/Http/Kernel.php` and old config files.
- Unused auth scaffolding: Auth controllers, the `User` model and the users/password_resets migrations, with no auth routes.

When editing, match the existing style instead of converting individual files to Laravel 10 conventions.
