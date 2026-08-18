# Domain model — clinical + commercial core

Business domain aligned for **gestao-clinica**. Complements the technical stack in [`stack-definition.md`](./stack-definition.md).

**Visão em português (para validação):** [`visao-da-plataforma.md`](./visao-da-plataforma.md)

Implementation order lives in [`domain-roadmap.md`](./domain-roadmap.md).

---

## 1. Product vision

The platform is a **multi-tenant clinic system** that:

1. Registers **products** (cost, brand, type, purpose, unit, sale price, stock).
2. Groups products into **protocols** (procedures) with quantities, sale price, minimum price, and total cost.
3. Registers **clients** (patients) with contact and clinical notes.
4. Registers **payment methods** (and card operators / rates when credit card).
5. Creates **sales** and **contracts** (commercial agreement — **does not** decrement stock).
6. Runs a **treatment** for the patient: start → record **actual** product usage (planned + complimentary extras) → complete → **then** decrement stock and account real cost.
7. Warns when stock is low.

All commercial and clinical data belongs to a **clinic** (tenant). Users belong to a clinic and only see that clinic’s data (except platform super-admins).

**Portuguese product vision:** [`visao-da-plataforma.md`](./visao-da-plataforma.md)

---

## 2. Multi-tenancy (clinic)

| Concept | Decision |
| --- | --- |
| Tenant | `Clinic` |
| Isolation | Every domain row carries `clinic_id` (or is reached only via a clinic-owned parent) |
| Users | Belong to **one** clinic by default (`users.clinic_id`) |
| Permissions | Still permission-based; queries always scoped by clinic |
| Super-admin | Platform-level user that can manage clinics (no clinic data leak into normal APIs) |

### Clinic (holder)

| Field (conceptual) | Notes |
| --- | --- |
| `name` | Legal / display name |
| `document` | CNPJ / tax id (optional initially) |
| `phone`, `email`, `address` | Contact |
| `settings` (JSON) | Low-stock defaults, locale, currency |
| `is_active` | Soft disable tenant |

**Rule:** products, protocols, clients, payment catalogs, sales, budgets, and documents are **clinic-scoped**. No cross-clinic reads in normal user flows.

---

## 3. Entity map

```text
Clinic
├── Users
├── Product catalogs
│   ├── ProductType          (botox, filler, toxin, acid, …)
│   ├── Brand
│   ├── UnitOfMeasure        (mg, ml, unit, kg, …)
│   └── Product
│         ├── cost, sale_price
│         ├── stock_quantity, min_stock
│         └── purpose / description
├── Protocol
│   └── ProtocolItem  → Product + quantity_used
├── Client (patient)
├── Payment catalog
│   ├── PaymentMethod
│   ├── CardOperator
│   ├── CardBrand            (optional)
│   └── CardFeeRule
├── Budget (optional pre-sale)
│   └── BudgetItem
├── Sale                     (commercial — NO stock movement)
│   ├── SaleItem             (suggested protocols and/or products)
│   └── SalePayment
├── Document / Contract      (from sale/budget)
└── Treatment                (clinical application — stock moves on complete)
    └── TreatmentConsumption (actual usage: suggested + complimentary extras)
```

---

## 4. Products & stock

Financial field rationale (cost, revenue, margin): [`produto-financeiro.md`](./produto-financeiro.md).

### Product

| Field | Purpose |
| --- | --- |
| `clinic_id` | Tenant |
| `name` | Product name |
| `sku` | Optional internal/supplier code |
| `product_type_id` | Category (botox, filling, toxin, acid, …) |
| `brand_id` | Brand |
| `unit_of_measure_id` | kg, mg, ml, unit, … — qty and cost share this UoM |
| `purpose` | What it is for |
| `cost` | **Weighted average unit cost** (CMV / inventory valuation base) |
| `sale_price` | Default selling price |
| `min_sale_price` | Optional floor for standalone product sales |
| `stock_quantity` | Current stock in product UoM |
| `min_stock` | Low-stock threshold |
| `is_active` | Soft disable |

**Derived (Resource):** `unit_margin`, `unit_margin_percent`, `inventory_value`, `is_low_stock`.

### Stock movement

Every stock change is recorded (Phase 2+):

| Field | Purpose |
| --- | --- |
| `type` | `in`, `out`, `adjustment` |
| `quantity` | Positive amount |
| `unit_cost` | Required on `in` — purchase cost for weighted average |
| `cost_before` / `cost_after` | Average cost trail |
| `stock_before` / `stock_after` | Quantity trail |
| `reason`, `user_id` | Audit |
| `reference_*` | Later: treatment / purchase link |

**Inbound weighted average:**  
`(stock × cost + qty_in × unit_cost_in) / (stock + qty_in)`.

### Stock behavior

| Event | Effect |
| --- | --- |
| Stock `in` (restock) | Increase qty; update weighted average `cost` |
| Manual `out` / adjustment | Change qty; average cost unchanged unless policy says otherwise |
| **Sale** confirm / cancel | **No stock change** |
| Budget / contract | **No stock change** |
| **Treatment complete** | Decrease by actual consumption; CMV snapshots `unit_cost` |
| Treatment cancel after complete | Restore stock (later phase) |

### Low-stock warning

- Product is “low” when `stock_quantity <= min_stock`.
- API: list/filter low-stock products.

### Permissions (examples)

`products.view`, `products.create`, `products.update`, `products.delete`, `products.adjust_stock`, `product_types.manage`, `brands.manage`, `units.manage`

---

## 5. Protocols (procedure = product bundle)

A **protocol** is a reusable **set of products that forms a complete service**. Pricing details: [`protocolo.md`](./protocolo.md).

| Field | Purpose |
| --- | --- |
| `clinic_id` | Tenant |
| `name` | e.g. “Full face filling” |
| `description` | Optional |
| `total_cost` | **Calculated** `Σ (product.cost × qty)` |
| `products_sale_total` | **Calculated** `Σ (product.sale_price × qty)` — reference |
| `suggested_price` | Main suggested sell price (defaults from products’ sale prices; editable) |
| `suggested_price_is_manual` | When true, recalculation does not overwrite `suggested_price` |
| `min_price` | Commercial floor (defaults from products’ min/cost; editable) |
| `min_price_is_manual` | When true, recalculation does not overwrite `min_price` |
| `special_price` | Optional second suggested price (special condition / easier close) |
| `is_active` | Soft disable |

### ProtocolItem

| Field | Purpose |
| --- | --- |
| `protocol_id` | Parent |
| `product_id` | Component product |
| `quantity` | Amount used in the service (product UoM) |

**Derived margins:** suggested/special/min minus `total_cost`.

### Permissions

`protocols.view`, `protocols.create`, `protocols.update`, `protocols.delete`

---

## 6. Clients (patients)

| Field | Purpose |
| --- | --- |
| `clinic_id` | Tenant |
| `name` | Full name |
| `whatsapp` | Primary contact |
| `notes` | Free observations |
| `main_pains` | Main pains / complaints (text or structured later) |
| `service_duration_minutes` | Typical or last service time (also capturable per sale) |
| Extra later | Document (CPF), email, birth date, address — optional |

### Permissions

`clients.view`, `clients.create`, `clients.update`, `clients.delete`

---

## 7. Payment methods & card fees (separate catalogs)

Payment setup is **its own module**, not buried inside sales.

### PaymentMethod

Examples: cash (`dinheiro`), PIX, check (`cheque`), credit card (`cartao_credito`), debit card.

| Field | Notes |
| --- | --- |
| `clinic_id` | Tenant (or global + clinic overrides — default: clinic-scoped) |
| `name`, `code` | Display + stable code |
| `kind` | `cash`, `pix`, `check`, `credit_card`, `debit_card`, `other` |
| `requires_card_meta` | True for card kinds |
| `is_active` | Soft disable |

### Card operator & rates

When `kind = credit_card` (and optionally debit):

| Entity | Purpose |
| --- | --- |
| `CardOperator` | Cielo, Rede, Stone, PagSeguro, … |
| `CardBrand` | Visa, Mastercard, Elo, Amex, … (optional) |
| `CardFeeRule` | Operator (+ brand) + installment count → fee % and/or fixed fee |

Sales that pay by card reference method + operator (+ brand/installments) so net received can be calculated later.

### Permissions

`payment_methods.manage`, `card_operators.manage`, `card_fees.manage`

---

## 8. Sales

A **sale** is the **commercial** event: what was agreed and charged. It does **not** move stock.

| Field | Purpose |
| --- | --- |
| `clinic_id` | Tenant |
| `client_id` | Buyer / patient |
| `sold_by_user_id` | Authenticated seller |
| `sold_at` | Sale datetime |
| `expected_amount` | Auto-calculated from lines (protocols + products at registered prices) |
| `effective_amount` | Actual charged amount |
| `status` | `draft`, `confirmed`, `cancelled` |
| `notes` | Free text |

### SaleItem

Each line is either a **protocol** or a **standalone product**. These lines become the **suggested consumption checklist** when a treatment starts.

| Field | Purpose |
| --- | --- |
| `sale_id` | Parent |
| `line_type` | `protocol` \| `product` |
| `protocol_id` | If protocol line |
| `product_id` | If product line |
| `quantity` | How many of that protocol/product |
| `unit_price` | Snapshot at sale time |
| `line_total` | Snapshot |

Price snapshots stay on the sale for commercial history. Stock is **not** touched here.

### SalePayment

| Field | Purpose |
| --- | --- |
| `sale_id` | Parent |
| `payment_method_id` | Method used |
| `amount` | Portion paid with this method |
| `card_operator_id` / brand / installments | When card |
| `paid_at` | Optional |

`Σ payment.amount` should equal `effective_amount` (configurable: allow partial — open question).

### Permissions

`sales.view`, `sales.create`, `sales.update`, `sales.confirm`, `sales.cancel`

---

## 9. Budgets (quotes)

Same shape as a sale, but:

- Does **not** decrement stock (nothing does until treatment complete).
- Status: `draft`, `sent`, `accepted`, `rejected`, `expired`, `converted`.
- Can **convert to sale** (copy lines + client + expected amounts).

### Permissions

`budgets.view`, `budgets.create`, `budgets.update`, `budgets.convert`

---

## 10. Documents / contracts

Generated from budget or sale + clinic + client data (and protocol/product lines). **No stock effect.**

| Field | Purpose |
| --- | --- |
| `clinic_id` | Tenant |
| `client_id` | Patient |
| `sale_id` / `budget_id` | Source |
| `type` | `contract`, `consent`, `receipt`, … |
| `status` | `draft`, `issued`, … |
| `storage_path` | PDF/file on MinIO (S3) |
| `payload` (JSON) | Snapshot of values used to render the document |

Typical order: **confirm sale → issue contract → start treatment**.

### Permissions

`documents.view`, `documents.generate`, `documents.delete`

---

## 11. Treatments (clinical application — stock + real cost)

A **treatment** is opened for a patient (from a confirmed sale). Suggested products come from exploding sale protocols + product lines. At the end, the professional records **what was actually used**.

### Why separate from sale

- At sale time the product has **not** been applied yet.
- The doctor may use the suggested set, change quantities, and **add complementary products without charging** the patient.
- Stock and **clinic cost** must follow **real consumption**, including complimentary extras.

### Treatment

| Field | Purpose |
| --- | --- |
| `clinic_id` | Tenant |
| `sale_id` | Commercial source |
| `client_id` | Patient (denormalized for queries) |
| `professional_user_id` | Who performed the application |
| `started_at` / `finished_at` | Session window |
| `duration_minutes` | Actual service time |
| `status` | `scheduled`, `in_progress`, `completed`, `cancelled` |
| `notes` | Clinical notes |
| `total_cost` | Σ consumption line costs (includes complimentary) |
| `total_charged_on_treatment` | Σ amounts charged on consumption lines (extras billed at session, if any) |

### TreatmentConsumption

| Field | Purpose |
| --- | --- |
| `treatment_id` | Parent |
| `product_id` | Product used |
| `quantity` | Actual quantity used |
| `source` | `suggested` (from sale) \| `extra` (added during treatment) |
| `sale_item_id` | Optional link back to originating sale line |
| `is_charged` | Whether patient was billed for this line |
| `charged_amount` | Amount charged (0 if complimentary) |
| `unit_cost` | Product cost snapshot at completion |
| `line_cost` | `quantity × unit_cost` — always counted for clinic cost |

### Completion behavior (transactional)

1. Validate treatment is `in_progress` with at least the required consumption lines.
2. Persist consumption snapshots (`unit_cost`, `line_cost`).
3. Decrement `product.stock_quantity` by each consumption `quantity`.
4. Set `total_cost` / status `completed` / `finished_at`.
5. Emit low-stock evaluation for touched products.

### Cost accounting note

- **Revenue** primarily lives on the **sale** (`effective_amount` + payments).
- **Real product cost** lives on the **treatment** (`total_cost`), including complimentary extras.
- Margin analysis later: sale revenue − treatment real cost (− card fees, etc.).

### Permissions

`treatments.view`, `treatments.start`, `treatments.update`, `treatments.complete`, `treatments.cancel`

---

## 12. Domain flow (happy path)

```text
Clinic exists
  → Register catalogs (types, brands, units, payment methods, card fees)
  → Register products (cost, sale price, stock, min_stock)
  → Build protocols (products + qty → total_cost, sale_price, min_price)
  → Register clients
  → (Optional) Create budget → convert
  → Create & confirm sale (protocol and/or products)     ← no stock change
  → Generate contract
  → Start treatment (suggested products from sale)
  → Complete treatment with actual usage
       (+ extras, possibly complimentary / charged)
       → stock down
       → real cost recorded
  → Low-stock list / alerts
```
---

## 13. Permission catalog (domain, additive to Phase 1)

| Area | Permissions |
| --- | --- |
| Clinics | `clinics.view`, `clinics.manage` (platform / admin) |
| Catalogs | `product_types.manage`, `brands.manage`, `units.manage` |
| Products | `products.view`, `products.create`, `products.update`, `products.delete`, `products.adjust_stock` |
| Protocols | `protocols.view`, `protocols.create`, `protocols.update`, `protocols.delete` |
| Clients | `clients.view`, `clients.create`, `clients.update`, `clients.delete` |
| Payments | `payment_methods.manage`, `card_operators.manage`, `card_fees.manage` |
| Budgets | `budgets.view`, `budgets.create`, `budgets.update`, `budgets.convert` |
| Sales | `sales.view`, `sales.create`, `sales.update`, `sales.confirm`, `sales.cancel` |
| Documents | `documents.view`, `documents.generate`, `documents.delete` |
| Treatments | `treatments.view`, `treatments.start`, `treatments.update`, `treatments.complete`, `treatments.cancel` |

All checks remain **permission-first**; roles only group these permissions per clinic job (receptionist, seller, stock manager, professional, clinic admin).

---

## 14. Open questions

1. **User ↔ clinic** — One clinic per user for now, or membership in many clinics?
2. **`min_price` on protocol** — Hard block below minimum, or warning only?
3. **Partial payments** — Must payments sum exactly to `effective_amount`, or allow outstanding balance?
4. **Installments** — Store installment plan on the sale payment, or only fee lookup?
5. **Treatment extras with charge** — Create a payment adjustment on the original sale, or only record `charged_amount` on the treatment line?
6. **Sessions** — One sale → one treatment, or multiple treatment sessions per sale?
7. **Product types / brands / units** — Clinic-scoped catalogs only, or platform defaults cloned into each clinic?
8. **Currency** — BRL only?
9. **Contracts** — HTML/PDF templates first, or third-party doc tool later?
10. **WhatsApp** — Store number only for now?

---

## 15. Decision summary

| Topic | Decision |
| --- | --- |
| Tenant | Clinic; all domain data clinic-scoped |
| Commercial core | Products → Protocols → Sales → Contract → Treatment |
| Stock | Decrements only on **treatment complete** (actual usage); low-stock via `min_stock` |
| Sale | Commercial only; suggested products; **no stock movement** |
| Treatment | Records real usage (suggested + complimentary extras); drives stock + real cost |
| Protocol | Bundle of products with qty, sale_price, min_price, total_cost |
| Sale lines | Protocol and/or individual products |
| Money | Sale: expected + effective + payments; Treatment: real cost (+ optional extra charges) |
| Cards | Separate operators / fee rules module |
| Budgets & docs | Same commercial lineage; docs on MinIO; no stock effect |
| Authz | Permission-first model, clinic-scoped queries |
| UX | Mobile-first **PWA** for doctor, secretary, and clinic staff; native optional later |
