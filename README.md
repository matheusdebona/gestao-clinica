# gestao-clinica

Multi-tenant clinical + commercial management platform — API-first.

## Current status

**Definition phase.** Stack, tenancy, and commercial domain are documented; application scaffolding has not started yet.

| Document | Purpose |
| --- | --- |
| [docs/visao-da-plataforma.md](./docs/visao-da-plataforma.md) | **Visão completa em português** (para validar alinhamento) |
| [docs/stack-definition.md](./docs/stack-definition.md) | Laravel 13 / PHP 8.5, Sanctum, Redis, Postgres 18, MinIO, permissions |
| [docs/phase-1-todo.md](./docs/phase-1-todo.md) | Foundation checklist: Docker, login, permissions, clinic skeleton |
| [docs/domain-model.md](./docs/domain-model.md) | Products, stock, protocols, clients, payments, sales, budgets, docs, multi-tenant clinic |
| [docs/domain-roadmap.md](./docs/domain-roadmap.md) | Phased TODOs for the commercial core |

## Target stack (locked)

- **API:** Laravel 13 · PHP 8.5
- **Auth:** Laravel Sanctum (Bearer tokens)
- **Authz:** Permission-first (`spatie/laravel-permission`); roles only as optional groups
- **Tenant:** Clinic — all commercial data clinic-scoped
- **DB:** PostgreSQL 18 · **Cache/queue:** Redis · **Files:** MinIO (S3)

## Domain (locked direction)

```text
Clinic → Products → Protocols → Clients → Payments
       → Sales (no stock) → Contract → Treatment (stock + real cost)
```

## Next step

Read [docs/visao-da-plataforma.md](./docs/visao-da-plataforma.md), answer open questions, then implement Phase 1 (`docs/phase-1-todo.md`).
