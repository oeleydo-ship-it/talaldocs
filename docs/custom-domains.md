# Custom domains

Custom domains let you serve public documentation from your own hostname (for example `docs.example.com`).

## Public URLs

When a hostname is **verified and active**, that host serves the project's public docs at the root (GitBook/ReadMe style):

| Host | Path | Result |
| --- | --- | --- |
| `docs.example.com` | `/` | Project docs home (default version, locale, and welcome/first page) |
| `docs.example.com` | `/latest/en/welcome` | That docs page |
| `docs.example.com` | `/directory`, `/announcements`, `/changelog` | Hub pages |
| `{subdomain}.{APP_DOMAIN}` | `/` | Same rewrite for tenant subdomains |
| `{APP_DOMAIN}` (app / marketing site) | `/` | Marketing homepage |
| `{APP_DOMAIN}` | `/docs/{subdomain}/...` | Path-based docs (always available) |

Unverified or failed custom hosts show a pending/failed page. They do not serve marketing mixed with that project's docs.

Marketing, login, dashboard, `/platform`, and the installer stay on the **app domain** (`APP_URL` / `APP_DOMAIN`).

## Verification flow

1. Add the hostname in **Project settings → Domains**.
2. Create DNS records:
   - **TXT** ownership record shown in settings (Cloudflare SSL for SaaS) and/or `_anytdocs-challenge.{hostname}` = `anytdocs-verify={token}`
   - **CNAME** `{hostname}` → `fallback.{APP_DOMAIN}` (or the platform Cloudflare fallback origin)
3. Click **Verify now** or wait for the background job. Status moves through:
   - **Pending** — waiting for first check
   - **Verifying** — DNS lookup in progress
   - **Active** — TXT and CNAME verified
   - **Failed** — records missing or incorrect (see error message)

## Primary domain

Mark an active domain as **primary**. When visitors use the platform subdomain (`{subdomain}.{APP_DOMAIN}`), they are redirected (301) to the primary custom domain in production.

## Host resolver (localhost limitation)

`ResolvePublicHost` middleware rewrites bare paths on project subdomains/custom hosts to `/docs/{subdomain}/...` before routing. It duplicates the request so Laravel routing sees the rewritten path (setting `REQUEST_URI` alone is not enough).

On **localhost** (`php artisan serve`), host-based paths are **not** rewritten. Always use path URLs:

```
http://localhost:8000/docs/{subdomain}/{version}/{locale}/{page}
```

Host-based docs require Herd/Valet, Caddy, or production DNS. See [dns-wildcard.md](dns-wildcard.md).

## Production checklist

- Point customer CNAME to the SSL for SaaS **fallback origin** (`fallback.{APP_DOMAIN}`), not the tenant subdomain
- Terminate TLS (Cloudflare SSL for SaaS or Caddy on-demand)
- Keep `APP_DOMAIN` / platform **App domain** aligned with your wildcard zone
- Keep `APP_URL` on the **app/marketing** host (`https://talaldocs.com`), never on a customer hostname
- Origin (nginx) must receive the **custom hostname** as `Host` (Cloudflare SSL for SaaS default). If the origin only sees `fallback.{APP_DOMAIN}`, pass `X-Forwarded-Host: {custom-hostname}`
- Do not rewrite `Host` to the apex app domain at the proxy, or every custom host will show marketing
