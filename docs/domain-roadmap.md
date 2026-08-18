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

## Phase 6 — Sales + stock movement

- [ ] Create/update sale draft (client, date/service_at, notes)
- [ ] Add lines: protocol and/or product with qty; snapshot prices
- [ ] Compute `expected_amount` automatically
- [ ] Set `effective_amount` (+ min_price policy)
- [ ] Attach one or more `SalePayment`s
- [ ] Confirm sale → explode protocols → decrement stock (transactional)
- [ ] Cancel sale → restore stock (if enabled)
- [ ] Permissions + tests (stock math, isolation, 403s)

**DoD:** selling a protocol reduces component product stock; payments and amounts persisted.

---

## Phase 7 — Budgets

- [ ] Budget CRUD mirroring sale lines (no stock decrement)
- [ ] Status workflow (`draft` → `sent` → `accepted` / `rejected` / `expired`)
- [ ] Convert accepted budget → sale draft
- [ ] Permissions + tests

**DoD:** quote → convert → confirm sale path works.

---

## Phase 8 — Documents / contracts

- [ ] Document model linked to sale/budget + client + clinic
- [ ] Generate PDF (or HTML→PDF) from snapshot payload
- [ ] Store file on MinIO (`documents` prefix)
- [ ] List/download by permission
- [ ] Permissions + tests

**DoD:** contract/receipt file produced from existing sale data.

---

## Phase 9 — Alerts & hardening (later)

- [ ] Low-stock notification channel (email / WhatsApp — TBD)
- [ ] Audit log for stock and price overrides
- [ ] Dashboard aggregates (sales, margin, stock risk)
- [ ] Cloud S3 cutover for documents and uploads

---

## Suggested build order (why)

```text
Clinic/tenant → Products/stock → Protocols → Clients → Payments
    → Sales (stock out) → Budgets → Documents
```

Sales need products, protocols, clients, and payment methods. Budgets reuse the same line model. Documents consume confirmed commercial data and MinIO.
