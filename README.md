# HOF Pay

Plataforma de gestão clínica + comercial, multi-tenant — API-first, **mobile-first**. O repositório GitHub permanece [`gestao-clinica`](https://github.com/matheusdebona/gestao-clinica).

Arquitetura, domínios de produção e plano de deploy: [`docs/hofpay-architecture.md`](./docs/hofpay-architecture.md).

## Current status

**Phase 1 in progress / foundation landed:** Laravel 13 API scaffold with Sanctum auth, permission-first RBAC, clinic tenancy skeleton, and Docker Compose stack.

| Document | Purpose |
| --- | --- |
| [docs/hofpay-architecture.md](./docs/hofpay-architecture.md) | Marca HOF Pay, hosts, monorepo e deploy (VPS) |
| [docs/visao-da-plataforma.md](./docs/visao-da-plataforma.md) | Visão completa em português |
| [docs/produto-financeiro.md](./docs/produto-financeiro.md) | Dados do produto para custo, receita e margem |
| [docs/protocolo.md](./docs/protocolo.md) | Protocolo = serviço completo + preços (custo/sugerido/mínimo/especial) |
| [docs/stack-definition.md](./docs/stack-definition.md) | Stack técnica |
| [docs/phase-1-todo.md](./docs/phase-1-todo.md) | Checklist Fase 1 |
| [docs/domain-model.md](./docs/domain-model.md) | Domínio comercial |
| [docs/domain-roadmap.md](./docs/domain-roadmap.md) | Fases 2–11 |
| [docs/frontend-vue-spec.md](./docs/frontend-vue-spec.md) | Spec do frontend Vue + Soft Violet |

## Stack

- Laravel 13 · PHP 8.5
- Sanctum (Bearer) · Spatie Permission
- PostgreSQL 18 · Redis · MinIO (S3)
- Accent-insensitive `?q=` search (clients, products, protocols, sales/treatments/appointments client filters) uses the Postgres **`unaccent`** extension. The migration runs `CREATE EXTENSION IF NOT EXISTS unaccent` when the role is allowed; on managed Postgres enable `unaccent` in the provider dashboard if that statement is forbidden. SQLite tests register a PHP `unaccent()` fallback.
- Clinic multi-tenant · Vue SPA em [`apps/web`](./apps/web) (PWA later)
- Marketing em [`apps/site`](./apps/site) (Astro; `./dev site` → `:4321`)

## Quick start

```bash
./dev up
```

API: `http://localhost:8000`  
Frontend: `http://localhost:5173` (ou a próxima porta livre)  
MinIO console: `http://localhost:9001` (minio / minioSecret)

```bash
./dev down          # para API e frontend
./dev fresh         # zera o banco local (só migrations)
./dev fresh -y      # a mesma coisa, sem confirmação
./dev seed          # roda os seeders
```

Demo admin (from seed):

- Email: `admin@clinica-demo.test`
- Password: `ChangeMe!123`

### Useful Make targets

```bash
make up
make test
make artisan CMD="route:list"
```

## Frontend Vue (design system)

Kitchen sink Soft Violet em `/dev/ui` — validar cores, inputs e feedback antes das features.

O `./dev up` já sobe o Vite. Para rodar só o frontend:

```bash
cd apps/web
cp .env.example .env
npm install
npm run dev
```

Detalhes: [`apps/web/README.md`](./apps/web/README.md).

## Auth API (Phase 1)

| Method | Path | Notes |
| --- | --- | --- |
| `POST` | `/api/v1/auth/login` | `{ "email", "password" }` → token |
| `POST` | `/api/v1/auth/register` | Clínica + primeiro admin → token |
| `GET` | `/api/v1/auth/me` | Bearer token |
| `POST` | `/api/v1/auth/logout` | Revoke current token |
| `POST` | `/api/v1/auth/logout-all` | Revoke all tokens |

Permission-gated examples: `/api/v1/users`, `/api/v1/clinics/current`, `/api/v1/permissions`.

## Local tests (without Docker)

```bash
cp .env.example .env
php artisan key:generate
# use sqlite/array drivers for quick tests, or point at local Postgres/Redis
php artisan test
```

## Build order

Follow `docs/domain-roadmap.md` in sequence. Do not start Phase 2 until Phase 1 DoD in `docs/phase-1-todo.md` is complete.
