# Phase 1 — TODO (API foundation, login, permissions)

Track implementation of the stack defined in [`stack-definition.md`](./stack-definition.md).

Status legend: `[ ]` todo · `[~]` in progress · `[x]` done · `[-]` deferred

---

## A. Repository & local stack

- [x] Scaffold Laravel 13 API project (PHP 8.5) in this repository
- [x] Add Docker Compose: `app` (PHP 8.5), `postgres:18`, `redis`, `minio`
- [x] Wire `.env.example` for Postgres, Redis, Sanctum, S3/MinIO
- [x] Create MinIO bucket on first boot (`minio-init` service)
- [ ] Confirm `php artisan migrate` against PostgreSQL 18 (Compose; validate on machine with Docker)
- [ ] Confirm Redis cache/queue connectivity (Compose)
- [ ] Confirm Laravel `Storage::disk('s3')` put/get against MinIO (Compose)
- [x] Document `make up` / `composer` / `artisan` workflow in README

---

## B. Authentication (Sanctum)

- [x] Install/configure Laravel Sanctum for API token auth
- [x] Ensure `User` model uses `HasApiTokens`
- [x] Implement `POST /api/v1/auth/login` (email + password → personal access token)
- [x] Implement `POST /api/v1/auth/logout` (revoke current token)
- [x] Implement `GET /api/v1/auth/me` (user profile + effective permissions list)
- [x] Optional: `POST /api/v1/auth/logout-all`
- [x] Rate-limit login endpoint (`throttle:10,1`)
- [x] Feature tests: login success/failure, logout, me (authenticated / guest)

---

## C. Permission system (permission-first RBAC)

- [x] Install `spatie/laravel-permission` (^8)
- [x] Publish migrations/config; permission cache store configurable (Redis in Compose)
- [x] Add `HasRoles` to `User` (permissions via roles **and** direct assignment)
- [x] Define Phase 1 permission catalog seeder (`users.*`, `roles.manage`, `permissions.view`, `files.*`, `clinics.*`)
- [x] Seed optional roles `super-admin` and `admin` as **permission groups only**
- [x] Gate every protected route with `permission:...` middleware (never role-only checks)
- [x] Bootstrap first clinic admin from env (DemoClinicSeeder); optional super-admin via env
- [x] Admin APIs (permission-gated):
  - [x] List users
  - [x] Create/update user
  - [x] Assign/revoke roles (via user payload `roles`)
  - [x] Assign/revoke direct permissions (via user payload `permissions`)
  - [x] List permissions / roles
- [x] Feature tests: user without permission → 403; with permission → 200

---

## C2. Clinic tenancy skeleton

- [x] Create `clinics` migration/model (name, contact, settings JSON, `is_active`)
- [x] Add `users.clinic_id` (nullable only for platform super-admin)
- [x] Resolve current clinic from authenticated user on API requests (`clinic.resolve`)
- [x] Enforce clinic scope helper/trait for upcoming domain models (`BelongsToClinic`)
- [x] Seed demo clinic + bind clinic admin user
- [x] Permissions: `clinics.view`, `clinics.manage`
- [x] Tests: clinic user does not see users from another clinic; current clinic resolves

---

## D. API hygiene

- [x] Version routes under `/api/v1`
- [x] Force JSON responses for API routes
- [x] Use Form Requests for auth and user-admin endpoints
- [x] API Resources for user/permission payloads
- [x] Consistent 401 / 403 / 422 handling

---

## E. Security baselines (minimal for Phase 1)

- [x] Strong password validation rules (`Password::defaults`)
- [x] Never commit secrets; `.env` gitignored
- [ ] CORS config aligned with known PWA origin(s) once frontend URL is fixed
- [x] HTTPS assumed in non-local environments (document only)

---

## F. Definition of done (Phase 1)

Phase 1 is complete when all of the following are true:

1. [ ] `docker compose up` brings API + Postgres 18 + Redis + MinIO to a healthy state (needs Docker host).
2. [x] A seeded admin can **login**, call **me**, and **logout** via Sanctum tokens (covered by tests + seeder).
3. [x] A second user **without** a given permission receives **403** on that endpoint.
4. [x] Authenticated clinic user resolves a **current clinic**; tenancy helpers are ready for domain models.
5. [ ] File put/get works through the S3 disk against MinIO (Compose validation pending).
6. [x] README documents how to run and how to create the first admin.
7. [x] Automated tests cover auth + permission denial/allowance + clinic binding (`php artisan test` green).

---

## Next phases

Full checklists: [`domain-roadmap.md`](./domain-roadmap.md).

- [ ] Phase 2 — Products, catalogs, stock + low-stock
- [ ] Phase 3 — Protocols (product bundles)
- [ ] Phase 4 — Clients
- [ ] Phase 5 — Payment methods + card operators/fees
- [ ] Phase 6 — Sales (commercial; **no** stock decrement)
- [ ] Phase 7 — Budgets → convert to sale
- [ ] Phase 8 — Documents/contracts (MinIO)
- [ ] Phase 9 — Treatments → actual usage → stock + real cost
- [ ] Phase 10 — Alerts, audit, dashboards, cloud S3
- [ ] Phase 11 — Frontend **PWA** mobile-first (native later if needed)
