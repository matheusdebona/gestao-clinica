# Clinical Management — Stack Definition

Initial architecture decisions for **gestao-clinica**: an API-first clinical management platform with a **mobile-first** product constraint for clinic day-to-day use.

This document freezes the construction stack for Phase 1. Implementation follows the checklist in [`phase-1-todo.md`](./phase-1-todo.md).

Business domain (products, protocols, sales, treatments, clients, payments, multi-tenant clinics) is defined in [`domain-model.md`](./domain-model.md) and phased in [`domain-roadmap.md`](./domain-roadmap.md). Portuguese overview: [`visao-da-plataforma.md`](./visao-da-plataforma.md).

---

## 1. Goals (Phase 1)

- Define and stand up the API foundation.
- Authenticate users (login / logout / me / token lifecycle).
- Authorize access by **permission** (fine-grained), not by role name alone.
- Introduce **Clinic** as the multi-tenant holder (users bound to a clinic).
- Run locally with Docker: API + PostgreSQL + Redis + MinIO.
- Keep the API shaped for a **mobile-first** client (doctor, secretary, clinic staff on phone/tablet).

### Mobile-first (product constraint)

| Topic | Decision |
| --- | --- |
| Priority | **Mobile-first** UX for daily clinic use (doctor, secretary, others) |
| Desktop | Supported as an expansion of mobile layouts, not the primary design target |
| API | Token-based Sanctum API suitable for PWA (and native later if needed) |
| First UI | **PWA** mobile-first (installable / “Add to Home Screen”); native apps only if PWA is not enough later |
| Critical phone flows | Client search, sale, contract access, start/complete treatment, low-stock |

---

## 2. Construction stack

| Layer | Choice | Version / notes |
| --- | --- | --- |
| Runtime | PHP | **8.5** (Laravel 13 supports 8.3–8.5) |
| Framework | Laravel | **13.x** (API-only app) |
| Auth | Laravel Sanctum | First-party; SPA cookies and/or personal access tokens |
| Authorization | `spatie/laravel-permission` | Permissions as the source of truth; roles optional as permission groups |
| Database | PostgreSQL | **18** |
| Cache / queues / sessions | Redis | Cache, queue driver, rate limiting; optional session store |
| Object storage | MinIO | S3-compatible; Laravel `s3` disk via Flysystem |
| Local orchestration | Docker Compose | API + Postgres 18 + Redis + MinIO |
| Tests | Pest 4 / PHPUnit 12 | Laravel 13 default testing stack |

### Why these choices

- **Laravel 13 + PHP 8.5** — current supported line; good fit for a greenfield API.
- **Sanctum** — lighter than Passport for first-party apps; covers SPA cookie auth and mobile/API tokens without OAuth complexity.
- **Spatie Permission** — mature permission + optional role model; gates integrate with `$user->can()` and route middleware. Fits “check permission X at this endpoint,” not “is role Y?”.
- **PostgreSQL 18** — strong relational defaults for clinical data, JSON when needed, solid concurrency.
- **Redis** — cache, queues, and throttling from day one.
- **MinIO** — same S3 API as AWS/GCS/R2 later; only credentials and endpoint change.

---

## 3. High-level architecture

```text
┌─────────────┐     HTTPS / JSON      ┌──────────────────────┐
│  Clients    │ ────────────────────► │  Laravel 13 API      │
│  (SPA /     │ ◄──────────────────── │  + Sanctum           │
│   mobile)   │                       │  + Permission gates  │
└─────────────┘                       └──────────┬───────────┘
                                                 │
                    ┌────────────────────────────┼────────────────────────────┐
                    ▼                            ▼                            ▼
             PostgreSQL 18                    Redis                        MinIO
             (primary data)            (cache / queue / RL)            (files / S3)
```

Later: swap MinIO endpoint for a cloud S3 (AWS, Cloudflare R2, etc.) without changing application code paths that use the `s3` disk.

---

## 4. Authentication (Sanctum)

### Recommended model for Phase 1

| Concern | Decision |
| --- | --- |
| Primary API auth | Sanctum **personal access tokens** (`Bearer`) |
| Optional later | Cookie/session SPA auth (same-site frontend) |
| Login | `POST /api/v1/auth/login` → issue token |
| Logout | `POST /api/v1/auth/logout` → revoke current token |
| Current user | `GET /api/v1/auth/me` → user + permissions |
| Password reset | Deferred unless needed in Phase 1 |

Sanctum **token abilities** are *not* the RBAC system. They may later scope machine tokens (e.g. “read-only integration”). Human authorization always goes through Spatie permissions.

### Endpoints (Phase 1 contract)

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `POST` | `/api/v1/auth/login` | Public | Email/password → token |
| `POST` | `/api/v1/auth/logout` | Token | Revoke current token |
| `GET` | `/api/v1/auth/me` | Token | Profile + effective permissions |
| `POST` | `/api/v1/auth/logout-all` | Token | Revoke all tokens for user (optional) |

---

## 5. Authorization model (permission-first)

You asked for RBAC, but with access decided by **permission**, not by role label. That is the right model for a clinical platform where “receptionist” and “doctor” share some actions and diverge on others.

### Principles

1. **Every protected route checks a permission** (e.g. `patients.view`), never a role string in middleware.
2. **Roles are optional convenience groups** of permissions (faster onboarding). They do not replace permission checks.
3. **Users may receive permissions directly** and/or via roles. Effective access = union of both.
4. **Permission names are stable API contracts** — use dotted, resource-oriented names.

### Naming convention

```text
{resource}.{action}
```

Examples (illustrative for later modules; Phase 1 only needs auth + admin of users/permissions):

| Permission | Meaning |
| --- | --- |
| `users.view` | List/view users |
| `users.create` | Create users |
| `users.update` | Update users |
| `users.delete` | Deactivate/delete users |
| `roles.manage` | Manage roles and their permission sets |
| `permissions.view` | Inspect permission catalog |
| `files.upload` | Upload to object storage |
| `files.delete` | Delete objects |

Clinical domain permissions (`patients.*`, `appointments.*`, `records.*`, …) are defined when those modules are built — same convention.

### Package

Use **`spatie/laravel-permission`** (^8, Laravel 13 compatible):

- Tables: `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`
- Cache permissions in **Redis**
- Middleware: `permission:users.view` (and `role_or_permission:` only if ever needed — prefer pure `permission:`)

### Seeded bootstrap (Phase 1)

| Role (optional group) | Permissions |
| --- | --- |
| `super-admin` | All permissions (or Gate::before bypass for this role only) |
| `admin` | User/role/permission management + file ops |

Initial super-admin user created via seeder / artisan command (credentials from `.env`, never hardcoded in git).

---

## 6. Infrastructure defaults

### Docker Compose services

| Service | Image direction | Ports (local) |
| --- | --- | --- |
| `app` | PHP 8.5 + Composer + Laravel | `8000` |
| `postgres` | `postgres:18` | `5432` |
| `redis` | `redis:7` (or latest stable 7/8) | `6379` |
| `minio` | `minio/minio` | `9000` (API), `9001` (console) |

### Laravel `.env` mapping (conceptual)

```env
APP_ENV=local
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=gestao_clinica
DB_USERNAME=...
DB_PASSWORD=...

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=minio
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=gestao-clinica
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
```

When moving to cloud S3: change `AWS_*` / endpoint; keep `FILESYSTEM_DISK=s3`.

---

## 7. API conventions (locked for all phases)

| Concern | Standard |
| --- | --- |
| Prefix | `/api/v1` |
| Format | JSON only (`Accept: application/json`) |
| Auth | `Authorization: Bearer {token}` |
| **Input / validation** | **Form Request** on every write (and complex reads when needed) |
| **Output** | **API Resource** (or Resource collection) for every successful payload |
| Auth errors | `401` unauthenticated · `403` forbidden (permission) |
| Validation errors | `422` with Laravel’s structured `message` + `errors` bag |
| Not found | `404` |

### Why Form Request + Resource

1. **Form Request** — single place for rules, messages, and authorization hooks; the API always returns the same `422` shape the PWA can map to fields.
2. **API Resource** — stable JSON contract for the frontend (PWA); controllers do not return raw Eloquent models.
3. Controllers stay thin: validate → act → `return new XResource(...)`.

### Response shapes (target)

**Success (single):**

```json
{
  "data": {
    "id": 1,
    "name": "..."
  }
}
```

**Success (list / paginator):** Resource collection (`data` + `links` / `meta` when paginated).

**Validation error (`422`):**

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Do **not** invent a parallel error format unless we later introduce a versioned envelope for all responses; prefer Laravel’s defaults so the PWA can reuse one error mapper.

Phase 1 already follows this for auth and users; Phases 2+ must keep the same pattern.

---

## 8. Multi-tenancy (locked)

| Topic | Decision |
| --- | --- |
| Tenant | **Clinic** — holder of products, protocols, clients, sales, payments, documents |
| Users | Belong to a clinic (`users.clinic_id`); data access always clinic-scoped |
| Platform admin | Optional super-admin without clinic scope for managing clinics only |
| Domain details | See [`domain-model.md`](./domain-model.md) |

---

## 9. Explicitly out of scope (for Phase 1 code)

- Product / protocol / sale / budget modules (Phases 2–8 — see [`domain-roadmap.md`](./domain-roadmap.md))
- Frontend application
- OAuth2 / social login / SSO
- Full audit log / LGPD suite (plan early; implement after commercial core)
- Cloud S3 cutover
- WhatsApp API messaging (store numbers first)

---

## 10. Open questions (need your input)

### Platform / auth / UX

1. **Frontend repo** — Same monorepo or separate SPA/PWA repo?
2. **PWA stack** — React, Vue, or Next (or other) for the first PWA?
3. **Locale / i18n** — Portuguese (BR) primary for API messages and seeds?
4. **User identity** — Email-only login, or also CPF / employee code?
5. **Soft delete** — Prefer deactivate users over hard delete?
6. **Admin bootstrap** — Env-based seeder acceptable?

### Domain (also listed in domain-model)

7. **User ↔ clinic** — One clinic per user for now, or many?
8. **Protocol `min_price`** — Hard block or warning only?
9. **Partial payments** — Allow outstanding balance on a sale?
10. **Treatment extras with charge** — Adjust original sale payment, or only annotate on treatment?
11. **Sessions** — One sale → one treatment, or multiple sessions?
12. **Currency** — BRL only?

---

## 11. Decision summary

| Topic | Decision |
| --- | --- |
| API | Laravel 13, PHP 8.5 |
| Auth | Sanctum (Bearer tokens first) |
| Authz | Permission-first via Spatie; roles as optional groups |
| Tenant | Clinic; clinic-scoped domain data |
| DB | PostgreSQL 18 |
| Cache/queue | Redis |
| Files | MinIO (S3 API) → cloud S3 later |
| Commercial core | Products → Protocols → Sales → Contract → Treatment (stock on complete) |
| UX | **Mobile-first PWA** (doctor, secretary, clinic staff); desktop secondary; native later if needed |
| Phase 1 deliverable | Stack + login + permissions + clinic tenancy skeleton + Docker |
