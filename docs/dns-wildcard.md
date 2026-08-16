# DNS and wildcard domains

Public documentation URLs will use `{project-subdomain}.{APP_DOMAIN}` (for example `acme-docs.anytdocs.test` locally and `acme-docs.anytdocs.app` in production). Path-based URLs (`{APP_DOMAIN}/docs/{subdomain}`) are an optional project setting.

Public docs resolve by path (`/docs/{subdomain}`), reserved subdomain host, or a verified custom domain. On `php artisan serve` / localhost, use path URLs. Use `SESSION_DOMAIN=.anytdocs.test` only when the app is actually served on `*.anytdocs.test`.

## Local (Laravel Herd / Valet)

Park or link the site, then enable a wildcard:

```
herd links anytdocs
herd secure anytdocs
```

Herd/Valet typically resolve `*.anytdocs.test` to the same site. Confirm:

```
ping acme-docs.anytdocs.test
```

## Local (Caddy)

```
*.anytdocs.test, anytdocs.test {
    reverse_proxy 127.0.0.1:8000
    tls internal
}
```

Add a hosts file wildcard if your OS does not resolve `*.anytdocs.test` (some Windows setups need Acrylic DNS or similar).

## Production

1. Create an A/AAAA (or CNAME) record for the apex and `www`.
2. Create a wildcard CNAME: `*.anytdocs.app` → your Laravel origin (Forge, Cloud, or load balancer).
3. Terminate TLS for the wildcard certificate on the edge.
4. Custom customer hostnames (`docs.customer.com`) should use Cloudflare SSL for SaaS / custom hostnames, or Caddy on-demand TLS, after domain verification.

Never point two projects at the same hostname. `domains.host` and `projects.subdomain` are globally unique.
