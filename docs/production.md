# Production deployment

This guide covers deploying Anytdocs to a production environment with PostgreSQL, Redis, background workers, Stripe webhooks, and wildcard tenant subdomains.

## Stack overview

| Component | Purpose |
|-----------|---------|
| PHP 8.3+ / Laravel 13 | Application server |
| PostgreSQL | Primary database (FTS for public docs search) |
| Redis | Cache, sessions, queues |
| Node.js 20+ | Frontend asset build (`npm run build`) |
| Queue worker | Custom domain verification, AI doc generation |
| Scheduler | Expired invitation cleanup, domain rechecks |
| Mail provider | Verification, password reset, invitations |
| Stripe | Billing checkout, portal, webhooks |
| S3-compatible storage | Editor image uploads |
| Cloudflare (recommended) | Wildcard DNS + SSL for SaaS / custom domains |

## Environment checklist

Copy `.env.example` to `.env` and set:

```env
APP_NAME=Anytdocs
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.anytdocs.com
APP_DOMAIN=anytdocs.com

# Leave empty only for php artisan serve on localhost
SESSION_DOMAIN=.anytdocs.com
SANCTUM_STATEFUL_DOMAINS=app.anytdocs.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=anytdocs
DB_USERNAME=anytdocs
DB_PASSWORD=

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@anytdocs.com
MAIL_FROM_NAME="${APP_NAME}"

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=

STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_PRO=price_...
STRIPE_PRICE_BUSINESS=price_...

OPENAI_API_KEY=sk-...

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
```

## Shared env and storage (CloudDeck / Capistrano-style)

Keep a **shared** `.env` (and `storage/`) outside each release so `composer install` → `package:discover` and later migrate/cache use PostgreSQL, not a missing SQLite file from `.env.example`.

Required DB keys:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_db
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

Platform settings are loaded from the DB on boot; if the connection is unavailable during discover, the app continues with config defaults.

## Build and migrate

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=PlanSeeder
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Queue worker

Run a persistent queue worker (Supervisor, systemd, or your platform's worker service):

```bash
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

Jobs that require the queue:

- `VerifyCustomDomainJob` — DNS verification after adding a custom domain
- `GenerateAiDocumentationJob` — AI documentation import from a website URL

Failed jobs are stored in the `failed_jobs` table. Retry from **Platform → System** or:

```bash
php artisan queue:retry all
```

## Scheduler

Add to crontab (one entry):

```cron
* * * * * cd /var/www/anytdocs && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled tasks include maintenance commands registered in `routes/console.php`.

## Stripe webhooks

1. In the Stripe Dashboard, add endpoint: `https://app.anytdocs.com/stripe/webhook`
2. Subscribe to events:
   - `checkout.session.completed`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
3. Copy the signing secret to `STRIPE_WEBHOOK_SECRET`

The handler updates workspace plans and Stripe customer IDs. Without Stripe keys, billing falls back to local plan changes for testing.

## Wildcard DNS and tenant subdomains

Public docs resolve at `{subdomain}.{APP_DOMAIN}` when DNS points to your app.

1. Add a wildcard A/AAAA or CNAME record: `*.anytdocs.com` → your load balancer
2. Configure your web server (nginx/Caddy) to accept wildcard hostnames
3. Set `APP_DOMAIN=anytdocs.com` and `SESSION_DOMAIN=.anytdocs.com`

See [dns-wildcard.md](dns-wildcard.md) for local development notes.

## Custom domains (Cloudflare SSL for SaaS)

For customer custom domains (`docs.customer.com`):

1. Customer adds CNAME → `{subdomain}.{APP_DOMAIN}` and TXT verification record (shown in project settings)
2. Click **Verify now** in the app (dispatches `VerifyCustomDomainJob`)
3. Use **Cloudflare SSL for SaaS** or on-demand TLS (Caddy) for automatic certificates on custom hostnames

See [custom-domains.md](custom-domains.md) for the full flow.

## Health checks

After deploy, verify:

- [ ] `GET /` returns the marketing home page
- [ ] Registration + email verification (or OAuth) works
- [ ] Queue worker is running (`php artisan queue:monitor` or Platform → System)
- [ ] `POST /stripe/webhook` returns 400 without a valid signature (proves route is live)
- [ ] A published project loads at `/docs/{subdomain}/latest/en/{slug}` or subdomain host
- [ ] Image uploads write to S3 and render in public docs

## Local vs production session domain

| Environment | `SESSION_DOMAIN` |
|-------------|------------------|
| `php artisan serve` on localhost | *(empty)* |
| Herd/Valet at `anytdocs.test` | `.anytdocs.test` |
| Production | `.yourdomain.com` |

## Optional: mail queue

Invitation and auth emails use Laravel's mailer. To queue outbound mail, set `QUEUE_CONNECTION=redis` and implement `ShouldQueue` on mailables, or configure your mail provider's async delivery.

## Security notes

- Never set `APP_DEBUG=true` in production
- Restrict `/platform` to platform admins (`is_platform_admin`)
- Keep `STRIPE_WEBHOOK_SECRET` and `OPENAI_API_KEY` out of version control
- Use HTTPS everywhere; set `APP_URL` to your canonical HTTPS origin
