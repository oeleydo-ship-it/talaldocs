# Local development

## Stack

- Laravel 13, PHP 8.3+, Inertia 3, React 19, Tailwind 4
- SQLite by default in `.env.example`
- PostgreSQL is the production database

## PostgreSQL

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=anytdocs
DB_USERNAME=anytdocs
DB_PASSWORD=secret
```

Then run `php artisan migrate --seed`.

## Laravel Herd

Point the site at this directory and use `anytdocs.test` as `APP_URL` / `APP_DOMAIN`. Set `SESSION_DOMAIN=.anytdocs.test` so future subdomain docs can share the session cookie when needed.

## OAuth

Leave GitHub and Google client IDs empty to hide social buttons. Fill them to enable Socialite.

## Mail

`MAIL_MAILER=log` writes verification and reset emails to `storage/logs/laravel.log`.
