# Anytdocs

Multi-tenant documentation SaaS built with Laravel 13, Inertia.js 3, React 19, and Tailwind CSS.

The product includes auth and onboarding plus the editor, public docs site, custom domains, branding, versions/languages, members, visibility, analytics, billing limits, workspace audit logs, and a platform super admin area.

## Requirements

- PHP 8.3+
- Composer
- Node.js 20+
- PostgreSQL (recommended) or SQLite for local development

## Local setup

```bash
composer setup
```

Or step by step:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run dev
```

In another terminal:

```bash
php artisan serve
```

For background jobs (AI documentation generation, domain verification, etc.), either set `QUEUE_CONNECTION=sync` in `.env` or run the full dev stack, which includes a queue listener:

```bash
composer dev
```

If you use `QUEUE_CONNECTION=database` or `redis` with only `php artisan serve`, start a worker separately: `php artisan queue:work`.

Visit [http://localhost:8000](http://localhost:8000). Keep `APP_URL=http://localhost:8000` and `SESSION_DOMAIN=` empty when using `php artisan serve`. Set `SESSION_DOMAIN=.yourdomain.test` only with Laravel Herd/Valet on a wildcard host.

Local mail uses `MAIL_MAILER=log`, so verification emails are written to `storage/logs/laravel.log`. In `APP_ENV=local` (not testing), new registrations are auto-verified.

Demo users after seeding:

- Email: `test@example.com` / Password: `password`
- Email: `admin@anytdocs.test` / Password: `password` (platform super admin)

If you already registered as `pinoycurl@gmail.com`, that account is marked verified. Use the password you chose at registration. After login: unverified users go to verification, verified users without a workspace go to onboarding, and onboarded users go to the dashboard.

## Platform super admin

Super admins manage the entire platform separately from workspace owners.

- URL: [http://localhost:8000/platform](http://localhost:8000/platform)
- Settings: [http://localhost:8000/platform/settings](http://localhost:8000/platform/settings)
- Login: `admin@anytdocs.test` / `password`
- Legacy `/admin` redirects to `/platform`

Tabs: **Overview**, **Users**, **Workspaces**, **Plans**, **Domains**, **Reports** (content moderation), **System** (queue, cache, env health), **Settings** (**General**: app name + app domain, logo/favicon; Platform, DNS, AI, SMTP, Payment).

Capabilities include user/workspace search, suspend/restore, verify email, plan limit editing, all custom domains (retry/disconnect), content report moderation, impersonation (audit logged), system health (queue/failed jobs, clear cache, mail/Stripe status), and **platform-wide AI provider settings** (OpenAI, Kimi/Moonshot, or custom OpenAI-compatible API).

## Editor media (Markdown)

In the project editor toolbar:

- **Image** — upload a file or paste an external URL (`![alt](url)`)
- **Video** — YouTube, Vimeo, Loom, direct `.mp4` URLs, or raw iframe embed (allowlisted hosts only)
- **Callouts** — tip, warning, and info boxes (`:::tip` … `:::`)
- **Blockquote**, **horizontal rule**, and **Link** dialog (text + URL)

Public docs render responsive 16:9 video embeds and styled callouts. Autosave picks up inserted content automatically.

## Custom domains

In **Project settings → Domains**:

1. Enter your hostname (e.g. `docs.example.com`) and click **Add domain**.
2. Add DNS records (copy buttons provided):
   - **CNAME** `docs.example.com` → `{subdomain}.{APP_DOMAIN}`
   - **TXT** `_anytdocs-challenge.docs.example.com` = `anytdocs-verify={token}`
3. Click **Verify now** (runs `VerifyCustomDomainJob`).
4. When status is **Active**, click **Make primary** to redirect the platform subdomain to your custom domain in production.

On localhost, public docs stay at `/docs/{project-slug}/...`. In production with a verified primary domain, visitors on `{subdomain}.{APP_DOMAIN}` are redirected to the custom host.

Super admins can retry failed domains under **Platform → Domains**.

## Marketing site

Public pages (no auth required):

- `/` — Home
- `/features` — Product features
- `/pricing` — Plans
- `/examples` — Example layouts
- `/contact` — Contact form

## Authentication

Email/password registration, verification, and password reset are enabled by default.

Google and GitHub buttons appear on login and registration only when `GOOGLE_CLIENT_ID` / `GITHUB_CLIENT_ID` and matching secrets are set. Callback URLs:

- `{APP_URL}/auth/google/callback`
- `{APP_URL}/auth/github/callback`

## Public docs and wildcard subdomains

On localhost, public docs are served at `/docs/{project-slug}/{version}/{locale}/{page}`. Host-based routing also resolves `{subdomain}.{APP_DOMAIN}` and verified custom domains when DNS points here. See [docs/dns-wildcard.md](docs/dns-wildcard.md) and [docs/custom-domains.md](docs/custom-domains.md).

Public docs include full-text search (PostgreSQL FTS when available), **Cmd/Ctrl+K search modal**, **AI Ask** (Guide and Classic headers — answers grounded in published docs when `OPENAI_API_KEY` is set), code block copy buttons, page feedback (Yes/No + comment), light/dark/system theme toggle, and **documentation templates** (Classic or Guide).

### Documentation templates

In **Project settings → Template** (or **Branding** for colors/fonts):

- **Classic** — wide layout with optional width toggle, inline search, and sticky table of contents
- **Guide** — grouped sidebar, breadcrumbs, hero title + optional page subtitle, numbered step cards (from ordered lists), search modal (`⌘K` / `Ctrl+K`), **Ask AI** button, copy page link, optional **Edit on GitHub** URL, sticky TOC, and “Powered by {app name}” footer (hidden on plans with advanced branding)

Set `docs_template` per project (`classic` | `gitbook`). New workspaces get a Guide-style **Welcome** page with numbered quickstart steps and an optional **subtitle** field in the editor.

## Billing

Plan limits are enforced on the server. With empty `STRIPE_KEY` / `STRIPE_SECRET`, the billing UI shows **Configure Stripe** and still changes the workspace plan locally so you can test limits.

When Stripe is configured:

- Checkout opens for plans with `STRIPE_PRICE_*` values
- Customer portal for workspaces with a Stripe customer ID
- Webhook `POST /stripe/webhook` verifies signatures and updates plans (`STRIPE_WEBHOOK_SECRET`)

## AI configuration (platform admin)

AI features (documentation generation and public docs **Ask**) use a **single platform-wide** API key and model shared by all tenant workspaces. Configure in **Platform → Settings → AI configuration** (`/platform/settings`).

| Field | Description |
|-------|-------------|
| Enable AI | Master toggle for generation and Ask |
| Provider | **OpenAI**, **Kimi (Moonshot)**, or **Custom** (any OpenAI-compatible API) |
| API key | Stored encrypted; UI shows last 4 characters only |
| Base URL | Auto-filled per provider; editable for Custom |
| Model | Preset list per provider plus custom slug |

**Provider defaults:**

- **OpenAI** — `https://api.openai.com/v1` — models: `gpt-4o-mini`, `gpt-4o`, `gpt-4-turbo`, `gpt-3.5-turbo`
- **Kimi (Moonshot)** — `https://api.moonshot.cn/v1` — models: `kimi-k3`, `kimi-k2`, `moonshot-v1-8k`, `moonshot-v1-32k`, `moonshot-v1-128k`
- **Custom** — set your own base URL and model slug

Use **Test connection** to verify credentials before saving. Changes are audit-logged.

For local development without the UI, `.env` values still work as a fallback when no platform key is saved:

```env
OPENAI_API_KEY=sk-...
AI_MODEL=gpt-4o-mini
AI_PROVIDER=openai
AI_BASE_URL=https://api.openai.com/v1
```

Optional: `AI_ENABLED`, `AI_TIMEOUT`, `AI_FETCH_TIMEOUT`, `AI_MAX_FETCH_BYTES`, `AI_FREE_MONTHLY_LIMIT`.

## AI documentation generation

Generate draft documentation from a public website URL using an OpenAI-compatible API.

Configure AI in **Platform → Settings** (recommended) or add to `.env` for local fallback:

How to trigger:

- **Onboarding** — optional “Import from your website” card (when platform AI is enabled)
- **Project page** or **Editor** — **Generate with AI** button (Sparkles icon)

Plan limits:

- **Free** — 1 AI generation per workspace per month (saved as drafts by default)
- **Pro+** — unlimited AI generations (`ai_generation` plan feature)

The job fetches public page content (respecting robots.txt, timeout, and max size), calls the LLM, and creates Welcome / Getting started / Setup / Features / FAQ pages in the current project.

Platform admins can see AI configuration status and usage counts on `/platform` → Overview, and manage the provider under **Settings**.

## AI Ask on public docs

Visitors can click **Ask** in the Guide or Classic public docs header to get answers grounded in the project's published documentation (default version and locale).

Requires platform AI to be enabled with a valid API key. When AI is not configured, the Ask button still appears but shows a tooltip and the modal explains that AI is not configured.

### AI knowledge index

Published pages are automatically chunked and indexed when you **Publish** from the editor. The index powers Ask AI retrieval (section headings + plain-text chunks from published markdown).

- **Project settings → Template → AI knowledge index** — view indexed page/chunk counts and click **Rebuild AI index**
- **CLI:** `php artisan docs:index platform-admin` or `php artisan docs:index --all`
- Pages are removed from the index when unpublished

- Endpoint: `POST /docs/{project}/ask` with `{ "question": "..." }`
- Rate limit: 10 requests per minute per IP
- Returns `{ answer, answer_html, sources[] }` with links to relevant doc pages

## Milestone status

| Milestone | Status |
|-----------|--------|
| Auth, onboarding, editor (Markdown) | Done |
| Public docs, search, feedback, templates (Classic / Guide) | Done |
| Custom domains (TXT + CNAME verify, primary redirect) | Done |
| Stripe checkout, portal, webhooks | Done |
| Editor: split preview, image upload, archive/restore, revision diff | Done |
| Editor: image/video URL embeds, callouts, link dialog | Done |
| Visual editor mode (WYSIWYG toggle) | Done |
| Workspace switcher (multi-workspace) | Done |
| Invitations: accept page, resend, cancel | Done |
| Platform super admin (full tabs + settings) | Done |
| Custom domains (DNS copy, verify, primary redirect) | Done |
| Analytics charts + CSV export (no PII in PageView) | Done |
| API key + SSO extension points | Done (hooks documented) |
| TipTap block editor | Deferred |
| Wildcard TLS automation | Deferred |

See [docs/production.md](docs/production.md) for deployment. See [docs/api-extensions.md](docs/api-extensions.md) for API/SSO hooks.

## Tests

```bash
php artisan test
```

## Production notes

Use PostgreSQL, Redis, an S3-compatible disk, a real mailer, and HTTPS. See [docs/production.md](docs/production.md) for the full deployment checklist (queue worker, scheduler, Stripe webhooks, wildcard DNS, Cloudflare SSL for SaaS).

Deferred for a later milestone: TipTap block editor, wildcard TLS automation.
