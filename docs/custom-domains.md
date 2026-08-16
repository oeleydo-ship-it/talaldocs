# Custom domains

Custom domains let you serve public documentation from your own hostname (for example `docs.example.com`).

## Verification flow

1. Add the hostname in **Project settings → Domains**.
2. Create DNS records:
   - **TXT** `_anytdocs-challenge.{hostname}` = `anytdocs-verify={token}`
   - **CNAME** `{hostname}` → `{project-subdomain}.{APP_DOMAIN}`
3. Click **Verify now** or wait for the background job. Status moves through:
   - **Pending** — waiting for first check
   - **Verifying** — DNS lookup in progress
   - **Active** — TXT and CNAME verified
   - **Failed** — records missing or incorrect (see error message)

## Primary domain

Mark an active domain as **primary**. When visitors use the platform subdomain (`{subdomain}.{APP_DOMAIN}`), they are redirected (301) to the primary custom domain in production.

## Host resolver (localhost limitation)

`ResolvePublicHost` middleware rewrites bare paths on project subdomains/custom hosts to `/docs/{subdomain}/...` before routing.

On **localhost** (`php artisan serve`), host-based paths are **not** rewritten. Always use path URLs:

```
http://localhost:8000/docs/{subdomain}/{version}/{locale}/{page}
```

Host-based docs require Herd/Valet, Caddy, or production DNS. See [dns-wildcard.md](dns-wildcard.md).

## Production checklist

- Point customer CNAME to your Anytdocs origin
- Terminate TLS (Cloudflare SSL for SaaS or Caddy on-demand)
- Keep `APP_DOMAIN` aligned with your wildcard zone
