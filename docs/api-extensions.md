# API and SSO extension points

Anytdocs does not expose a public REST API yet. The following hooks are in place for future milestones.

## API keys (placeholder)

Model: `App\Models\ApiKey`

- Workspace-scoped keys with prefix + hashed secret
- Generate with `ApiKey::generatePlainTextKey()`
- Store `hash('sha256', $plainKey)` in `key_hash` and first 12 chars in `key_prefix`

Middleware: `App\Http\Middleware\AuthenticateApiKey`

- Expects `X-Anytdocs-Key` header
- Sets `anytdocs.api_workspace_id` on the request when valid

Example future route group:

```php
Route::middleware(AuthenticateApiKey::class)->prefix('api/v1')->group(function () {
    // Future: pages, search, analytics export
});
```

## SSO

Google and GitHub OAuth are available when client IDs are configured. Enterprise SSO (SAML/OIDC) is planned — workspace login policy will live alongside Fortify routes.

## Password-protected docs

Projects with **Password** visibility require the unlock form at `/docs/{project}/unlock`. Session key: `docs.unlocked.{project_id}`.
