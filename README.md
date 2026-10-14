# One Time Secret

_`A laravel version of https://github.com/onetimesecret/onetimesecret.`_

Stop sending sensitive info in clear text.  This allows you to create a one use link to pass long to who you're trying
to share with.  Once viewed one time the information is destroyed.

Secrets are encrypted in your browser (AES-256-GCM) before they are sent. The key is in the part of the link after the
`#`, which browsers never send to the server, so the server only stores ciphertext it cannot read.

## Requirements

- PHP 8.4 and Redis
- HTTPS: browsers only allow the Web Crypto API on secure pages
- Node 20.19+ or 22.12+ and npm, on the server as well: the frontend is built during deploy

## Deploying

```
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize
```

`public/build` is not committed, so stop the deploy if `npm ci` or `npm run build` fails. Without
`public/build/manifest.json` every page returns a 500. `npm ci` installs exactly what `package-lock.json` lists, and
`.npmrc` stops packages' install scripts from running.

Production `.env` should set `APP_ENV=production`, `APP_DEBUG=false`, its own `APP_KEY`, `APP_URL`, and the Redis
connection (`REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`). See `.env.example`.
