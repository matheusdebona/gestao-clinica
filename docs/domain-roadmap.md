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

- [ ] CRUD `ProductType` (clinic-scoped)
- [ ] CRUD `Brand`
- [ ] CRUD `UnitOfMeasure`
- [ ] CRUD `Product` (cost, sale_price, stock_quantity, min_stock, purpose, FKs)
- [ ] Stock adjustment endpoint (permission `products.adjust_stock`)
- [ ] Low-stock listing (`stock_quantity <= min_stock`)
- [ ] Permissions + feature tests (including cross-clinic isolation)
- [ ] Seed sample types (botox, filling, toxin, acid, …) for demo clinic

**DoD:** register products with cost/sale/stock; list low-stock; another clinic cannot see these rows.

---

## Phase 3 — Protocols

- [ ] CRUD `Protocol` (sale_price, min_price, total_cost)
- [ ] Manage `ProtocolItem` (product + quantity)
- [ ] Recalculate `total_cost` when items or product costs change
- [ ] Optional validation helper: proposed price vs `min_price`
- [ ] Permissions + tests

**DoD:** define a procedure as a product bundle with cost and prices.

---

## Phase 4 — Clients

- [ ] CRUD `Client` (name, whatsapp, notes, main_pains, service_duration_minutes)
- [ ] Search by name / WhatsApp within clinic
- [ ] Permissions + tests

**DoD:** clients usable as sale/budget counterparts.

---

## Phase 5 — Payment methods & card fees

- [ ] CRUD `PaymentMethod` (cash, PIX, check, credit_card, …)
- [ ] CRUD `CardOperator`
- [ ] Optional CRUD `CardBrand`
- [ ] CRUD `CardFeeRule` (operator, brand?, installments → fee)
- [ ] Permissions + tests

**DoD:** sales can reference methods; card sales can attach operator/fee metadata.

---

## Phase 6 — Sales (commercial only)

- [ ] Create/update sale draft (client, sold_at, notes)
- [ ] Add lines: protocol and/or product with qty; snapshot prices
- [ ] Compute `expected_amount` automatically
- [ ] Set `effective_amount` (+ min_price policy)
- [ ] Attach one or more `SalePayment`s
- [ ] Confirm sale **without** stock movement
- [ ] Cancel sale (still no stock side effects)
- [ ] Permissions + tests (isolation, 403s; assert stock unchanged on confirm)

**DoD:** sale + payments persisted; confirming a sale does **not** change product stock.

---

## Phase 7 — Budgets

- [ ] Budget CRUD mirroring sale lines (no stock decrement)
- [ ] Status workflow (`draft` → `sent` → `accepted` / `rejected` / `expired`)
- [ ] Convert accepted budget → sale draft
- [ ] Permissions + tests

**DoD:** quote → convert → confirm sale path works (still no stock change).

---

## Phase 8 — Documents / contracts

- [ ] Document model linked to sale/budget + client + clinic
- [ ] Generate PDF (or HTML→PDF) from snapshot payload
- [ ] Store file on MinIO (`documents` prefix)
- [ ] List/download by permission
- [ ] Permissions + tests

**DoD:** contract/receipt file produced from existing sale data; stock unchanged.

---

## Phase 9 — Treatments + stock movement + real cost

- [ ] Create/start treatment from confirmed sale
- [ ] Prefill suggested consumption by exploding sale protocols + product lines
- [ ] Allow quantity adjustments on suggested lines
- [ ] Allow **extra** products (complimentary or charged)
- [ ] Complete treatment transactionally:
  - [ ] Snapshot unit costs / line costs
  - [ ] Decrement stock by actual quantities
  - [ ] Persist `total_cost` (includes complimentary)
  - [ ] Persist any `charged_amount` on extras
- [ ] Cancel in-progress treatment (no stock change)
- [ ] Permissions + tests (stock math, complimentary cost counted, isolation)

**DoD:** finishing a treatment lowers stock by what was really used; complimentary extras still cost the clinic and hit stock.

---

## Phase 10 — Alerts & hardening (later)

- [ ] Low-stock notification channel (email / WhatsApp — TBD)
- [ ] Audit log for stock and treatment overrides
- [ ] Dashboard: revenue (sale) vs real cost (treatment) / margin
- [ ] Cloud S3 cutover for documents and uploads

---

## Phase 11 — Frontend PWA (mobile-first)

- [ ] Choose PWA stack (e.g. React/Vue/Next) with **mobile-first** layouts
- [ ] Ship as installable **PWA** (manifest + service worker; “Add to Home Screen”)
- [ ] Prioritize phone flows: login, client search, sale, contract, treatment complete, low-stock
- [ ] Touch-friendly forms and lists; avoid desktop-only dense tables as primary UI
- [ ] Desktop layouts as progressive enhancement of the mobile design
- [ ] Native iOS/Android only if PWA proves insufficient later

**DoD:** doctor and secretary can install the PWA and complete core daily tasks comfortably on a phone-sized viewport.

---

## Suggested build order (why)

```text
Clinic/tenant → Products/stock master → Protocols → Clients → Payments
    → Sales (no stock out) → Budgets → Documents/contracts
    → Treatments (actual usage → stock out + real cost)
    → Mobile-first PWA UI
```

Sales and contracts are commercial. Treatments are clinical consumption. Stock only moves when treatment is completed. First UI is an installable **PWA**.
