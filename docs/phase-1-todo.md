# Phase 1 — TODO (API foundation, login, permissions)

Track implementation of the stack defined in [`stack-definition.md`](./stack-definition.md).

Status legend: `[ ]` todo · `[~]` in progress · `[x]` done · `[-]` deferred

---

## A. Repository & local stack

- [ ] Scaffold Laravel 13 API project (PHP 8.5) in this repository
- [ ] Add Docker Compose: `app` (PHP 8.5), `postgres:18`, `redis`, `minio`
- [ ] Wire `.env.example` for Postgres, Redis, Sanctum, S3/MinIO
- [ ] Create MinIO bucket on first boot (init script or documented `mc` step)
- [ ] Confirm `php artisan migrate` against PostgreSQL 18
- [ ] Confirm Redis cache/queue connectivity
- [ ] Confirm Laravel `Storage::disk('s3')` put/get against MinIO
- [ ] Document `make up` / `composer` / `artisan` workflow in README

---

## B. Authentication (Sanctum)

- [ ] Install/configure Laravel Sanctum for API token auth
- [ ] Ensure `User` model uses `HasApiTokens`
- [ ] Implement `POST /api/v1/auth/login` (email + password → personal access token)
- [ ] Implement `POST /api/v1/auth/logout` (revoke current token)
- [ ] Implement `GET /api/v1/auth/me` (user profile + effective permissions list)
- [ ] Optional: `POST /api/v1/auth/logout-all`
- [ ] Rate-limit login endpoint (Redis)
- [ ] Feature tests: login success/failure, logout, me (authenticated / guest)

---

## C. Permission system (permission-first RBAC)

- [ ] Install `spatie/laravel-permission` (^8)
- [ ] Publish migrations/config; use Redis for permission cache
- [ ] Add `HasRoles` to `User` (permissions via roles **and** direct assignment)
- [ ] Define Phase 1 permission catalog seeder (`users.*`, `roles.manage`, `permissions.view`, `files.*`, `clinics.*`)
- [ ] Seed optional roles `super-admin` and `admin` as **permission groups only**
- [ ] Gate every protected route with `permission:...` middleware (never role-only checks)
- [ ] Bootstrap first super-admin from env (Artisan command or seeder)
- [ ] Admin APIs (permission-gated):
  - [ ] List users
  - [ ] Create/update user
  - [ ] Assign/revoke roles
  - [ ] Assign/revoke direct permissions
  - [ ] List permissions / roles
- [ ] Feature tests: user without permission → 403; with permission → 200

---

## C2. Clinic tenancy skeleton

- [ ] Create `clinics` migration/model (name, contact, settings JSON, `is_active`)
- [ ] Add `users.clinic_id` (nullable only for platform super-admin)
- [ ] Resolve current clinic from authenticated user on API requests
- [ ] Enforce clinic scope helper/trait for upcoming domain models
- [ ] Seed demo clinic + bind clinic admin user
- [ ] Permissions: `clinics.view`, `clinics.manage`
- [ ] Tests: user A cannot resolve clinic B as “current”

---

## D. API hygiene

- [ ] Version routes under `/api/v1`
- [ ] Force JSON responses for API routes
- [ ] Use Form Requests for auth and user-admin endpoints
- [ ] API Resources for user/permission payloads
- [ ] Consistent 401 / 403 / 422 handling

---

## E. Security baselines (minimal for Phase 1)

- [ ] Strong password validation rules
- [ ] Never commit secrets; `.env` gitignored
- [ ] CORS config aligned with known frontend origin(s) once decided
- [ ] HTTPS assumed in non-local environments (document only)

---

## F. Definition of done (Phase 1)

Phase 1 is complete when all of the following are true:

1. `docker compose up` brings API + Postgres 18 + Redis + MinIO to a healthy state.
2. A seeded admin can **login**, call **me**, and **logout** via Sanctum tokens.
3. A second user **without** a given permission receives **403** on that endpoint.
4. Authenticated clinic user resolves a **current clinic**; tenancy helpers are ready for domain models.
5. File put/get works through the S3 disk against MinIO.
6. README documents how to run and how to create the first admin.
7. Automated tests cover auth + at least one permission denial/allowance path + clinic binding.

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
