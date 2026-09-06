# Domain roadmap — TODOs by phase

Builds on [`stack-definition.md`](./stack-definition.md), [`phase-1-todo.md`](./phase-1-todo.md), and [`domain-model.md`](./domain-model.md).

Status legend: `[ ]` todo · `[~]` in progress · `[x]` done · `[-]` deferred

---

## Phase 1 — Platform foundation (existing)

See [`phase-1-todo.md`](./phase-1-todo.md).

Add when implementing tenancy scaffolding:

- [ ] `clinics` table + model
- [ ] `users.clinic_id` (+ nullable only for platform super-admin)
- [ ] Global tenant scope / middleware: resolve clinic from authenticated user
- [ ] Permission: `clinics.view`, `clinics.manage`
- [ ] Seeder: one demo clinic + admin user bound to it

---

## Phase 2 — Catalogs & products + stock

See financial field evaluation: [`produto-financeiro.md`](./produto-financeiro.md).

- [x] CRUD `ProductType` (clinic-scoped)
- [x] CRUD `Brand`
- [x] CRUD `UnitOfMeasure`
- [x] CRUD `Product` (cost médio, sale_price, min_sale_price, stock, purpose, FKs + margem no Resource)
- [x] Stock movement endpoint (`in`/`out`, weighted average on inbound)
- [x] Low-stock listing (`?low_stock=1`)
- [x] Permissions + feature tests (including cross-clinic isolation)
- [x] Seed sample types (botox, filling, toxin, acid, …) for demo clinic

**DoD:** register products with cost/sale/stock; list low-stock; another clinic cannot see these rows; inbound recalculates average cost.

---

## Phase 3 — Protocols

See pricing model: [`protocolo.md`](./protocolo.md).

- [x] CRUD `Protocol` (total_cost, products_sale_total, suggested_price, min_price, special_price)
- [x] Manage `ProtocolItem` (product + quantity) via sync endpoint
- [x] Recalculate costs/suggested/min from products (respect manual flags)
- [x] `special_price` for special-condition suggested sell price
- [x] Permissions + tests (margins, manual preserve, clinic isolation)

**DoD:** define a complete service as a product bundle with cost, suggested, minimum, and optional special price.

---

## Phase 4 — Clients

- [x] CRUD `Client` (name, whatsapp, notes, main_pains, service_duration_minutes)
- [x] Search by name / WhatsApp within clinic (`?q=`)
- [x] Soft deactivate via DELETE (`is_active=false`)
- [x] Permissions + tests (isolation, 403, validation)

**DoD:** clients usable as sale/budget counterparts.

---

## Phase 5 — Payment methods & card fees

- [x] CRUD `PaymentMethod` (cash, PIX, check, credit_card, debit_card, boleto, other) + optional method fees
- [x] CRUD `CardOperator` (multi-machine; `auto_anticipate`)
- [x] CRUD `CardBrand` + seed principais bandeiras
- [x] CRUD `CardFeeRule` (method + operator + brand + installments → fee % and/or fixed)
- [x] Permissions `*.manage` + feature tests + default catalog seed

**DoD:** sales can reference methods; card sales can attach operator/fee metadata and compute net received.

---

## Phase 6 — Sales (commercial only)

- [x] Create/update sale draft (client, sold_at, notes)
- [x] Add lines via protocol explode and/or product sync; snapshot prices/costs/mins
- [x] Compute `expected_amount` automatically; malleable `effective_amount`
- [x] Soft min-price warnings + `confirm_below_minimum` on confirm
- [x] Attach one or more `SalePayment`s (sum must equal effective; card meta, no fee snapshot)
- [x] Confirm sale **without** stock movement
- [x] Cancel sale (history preserved; no delete)
- [x] Permissions + tests (isolation, 403s; assert stock unchanged on confirm)

**DoD:** sale + payments persisted; confirming a sale does **not** change product stock.

---

## Phase 7 — Budgets

- [x] Versioned budgets created from draft sales (immutable item snapshots)
- [x] Sale item KPI snapshots: `product_name`, `list_unit_price`, `list_line_total`
- [x] Status workflow (`draft` → `sent` → `accepted` / `rejected` / `expired`; prior drafts/sent → `superseded`)
- [x] Accept on same sale (no new sale); then payments/confirm as today
- [x] Permissions + tests (isolation, versioning, stock unchanged)

**DoD:** quote versions preserved; accept → confirm sale path works (still no stock change).

---

## Phase 8 — Documents / contracts

- [x] Clinic branding in `settings.branding` + logo on MinIO
- [x] Document model linked to budget (+ nullable sale) + client + clinic
- [x] Generate **budget PDF** via Blade + Browsershot/Chromium from snapshot payload (list vs offered + discount)
- [x] Store file on MinIO (`documents/` prefix)
- [x] List / show / download / delete by permission
- [x] Permissions + tests (PDF renderer mocked in CI)
- [ ] Sale contract / receipt PDF (deferred)

**DoD (partial):** budget orçamento PDF produced with clinic branding; stock unchanged. Contract/receipt from confirmed sale remains future work.

---

## Phase 9 — Treatments + appointments + real stock

- [x] Treatment model opened from confirmed sale (1:1)
- [x] Appointment entity (N per treatment) for scheduling / sessions / calendar later
- [x] Prefill suggested consumption from remaining sale balance
- [x] Allow quantity adjustments + extra products (complimentary or charged)
- [x] Charged extras append a new `SalePayment` on the sale
- [x] Complete appointment transactionally:
  - [x] Snapshot unit costs / line costs
  - [x] Decrement stock by actual quantities (negative stock allowed)
  - [x] Persist `total_cost` (includes complimentary)
  - [x] Persist charged amounts on extras
- [x] Warn on start when stock < suggested remaining
- [x] Fulfillment endpoint (sold / consumed / remaining + current stock)
- [x] Cancel scheduled/in-progress appointment (no stock change)
- [x] Permissions + tests

**DoD:** each completed appointment session lowers stock by what was really used; complimentary extras still cost the clinic and hit stock; multiple sessions per sale supported.

---

## Phase 10 — Alerts & hardening (later)

See detailed KPI build guide: [`metrics-kpis-roadmap.md`](./metrics-kpis-roadmap.md) (waves A–D).

- [x] Metrics API wave A — commercial KPIs (`GET /metrics/commercial`, `metrics.view`)
- [x] Metrics API wave B — acquisition (`GET /metrics/acquisition`, lifetime conversion)
- [x] Metrics API wave C — real margin (`GET /metrics/margin`, period + cohort_sale)
- [x] Metrics API wave D — inventory + clinical operations (`GET /metrics/inventory`, `GET /metrics/operations`)
- [x] Low-stock alerts (in-app inbox + PushChannel stub): daily job (atual + projeção pelos agendamentos do dia); warnings não-bloqueantes no `schedule`; destinatários com `products.view`
- [x] **UI inbox** (Fase 4.9 Vue) — list/read/read-all + badge; ver [`frontend-vue-spec.md`](./frontend-vue-spec.md) §4.9
- [ ] Real push / email / WhatsApp channels (FCM/Web Push later)
- [ ] Audit log for stock and treatment overrides
- [x] Dashboard UI (Vue web / later PWA) consuming metrics endpoints
- [ ] Cloud S3 cutover for documents and uploads

**Ops:** keep `php artisan schedule:run` (cron) and `php artisan queue:work` running so `CheckLowStockProductsJob` (01:00) and queued notifications are delivered.

---

## Phase 11 — Frontend (mobile-first web → PWA)

See [`frontend-vue-spec.md`](./frontend-vue-spec.md) for stack, folder layout, design-system order, and implementation phases.

- [x] Spec MD — Vue 3 + Vite + TS; Tailwind + Reka UI; web responsiva primeiro; componentes antes das features
- [x] Scaffold `apps/web` (Fase 1 do spec)
- [x] Design system **Modern Soft Violet Liquid Glass (heavy)** + `/dev/ui` kitchen sink (Fase 3)
- [x] Auth + `ClinicShell` (Fase 4.1)
- [x] Clientes: lista, busca `?q=`, CRUD (Fase 4.2)
- [x] Equipe: RBAC + CRUD usuários (Fase 4.2b)
- [x] **Produtos** (+ marcas/tipos/unidades, cascata marca→tipo, `?q=`, ajuste estoque) — Fase 4.3; ver detalhe em [`frontend-vue-spec.md`](./frontend-vue-spec.md) §4.3
- [x] **Protocolos** (pacote de produtos, itens no form, preços editáveis, `?q=`) — Fase 4.4; ver [`frontend-vue-spec.md`](./frontend-vue-spec.md) §4.4
- [x] **Vendas / orçamentos** (wizard, protocolo+itens, soft min, pagamentos fechados, orçamento+PDF, nav inbox) — Fase 4.5; ver [`frontend-vue-spec.md`](./frontend-vue-spec.md) §4.5
- [x] **Agendamentos** (agenda dia/semana, `appointments.*`, overlap por profissional, start sem estoque) — Fase 4.6; ver [`frontend-vue-spec.md`](./frontend-vue-spec.md) §4.6
- [x] **Tratamento — consumo clínico** (`treatments.consume`, checklist sessão, extras, stock no complete) — Fase 4.7; ver [`frontend-vue-spec.md`](./frontend-vue-spec.md) §4.7
- [x] **Métricas** (página única A–D, hero faturamento/ticket/conversão/margem, charts) — Fase 4.8; ver [`frontend-vue-spec.md`](./frontend-vue-spec.md) §4.8
- [x] **Notificações** (inbox Alertas, badge unread, chips, deep links; só `products.view`) — Fase 4.9; ver [`frontend-vue-spec.md`](./frontend-vue-spec.md) §4.9
- [ ] Ship as installable **PWA** after web is stable (Fase 5 — manifest + service worker)
- [ ] Touch-friendly forms and lists; avoid desktop-only dense tables as primary UI
- [ ] Desktop layouts as progressive enhancement of the mobile design
- [ ] Native iOS/Android only if PWA proves insufficient later

**DoD (web):** doctor and secretary complete core daily tasks comfortably on a phone-sized viewport.  
**DoD (PWA later):** installable “Add to Home Screen” on top of the same app.

---

## Suggested build order (why)

```text
Clinic/tenant → Products/stock master → Protocols → Clients → Payments
    → Sales (no stock out) → Budgets → Documents/contracts
    → Treatments (actual usage → stock out + real cost)
    → Mobile-first Vue web UI (PWA after)
```

Sales and contracts are commercial. Treatments are clinical consumption. Stock only moves when treatment is completed. First UI is a **mobile-first Vue web app**; PWA comes after the web flows are stable.
