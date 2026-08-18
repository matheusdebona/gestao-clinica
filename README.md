# gestao-clinica

Clinical management platform — API-first.

## Current status

**Phase 1 — definition.** Stack, auth, and permission model are documented; implementation has not started yet.

| Document | Purpose |
| --- | --- |
| [docs/stack-definition.md](./docs/stack-definition.md) | Construction stack, Sanctum auth, permission-first authorization, infra |
| [docs/phase-1-todo.md](./docs/phase-1-todo.md) | Actionable checklist for scaffolding, login, and RBAC-by-permission |

## Target stack (locked)

- **API:** Laravel 13 · PHP 8.5
- **Auth:** Laravel Sanctum (Bearer tokens)
- **Authz:** `spatie/laravel-permission` — permission checks on routes; roles only as optional groups
- **DB:** PostgreSQL 18
- **Cache / queue:** Redis
- **Files:** MinIO (S3-compatible) → cloud S3 later

## Next step

Implement Phase 1 from `docs/phase-1-todo.md` after open questions in the stack definition are answered (clients, tenancy, locale, etc.).
