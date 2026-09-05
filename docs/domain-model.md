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
| `settings` (JSON) | Low-stock defaults, locale, currency, **`branding`** (display name, primary/secondary hex colors, `logo_path` on MinIO) |
| `is_active` | Soft disable tenant |

**Clinic branding (API):** `GET/PUT /api/v1/clinic/branding`, `POST/DELETE /api/v1/clinic/branding/logo` — permission `clinics.branding` (clinic admin) or `clinics.manage` (platform). Logo: jpeg/png/webp, max 2MB, stored under `clinics/{id}/logo.*` on the `s3` disk.

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
│   ├── ClientOrigin         (Instagram, Facebook, Indicação, …)
│   └── Campaign             (belongs to origin; e.g. Reels Setembro)
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
| `client_origin_id` | Acquisition channel (Instagram, Facebook, referral, …) |
| `campaign_id` | Specific campaign under that origin (optional) |
| `initial_consultation_amount` | Amount paid for the first evaluation/consultation (CAC metrics) |
| Extra later | Document (CPF), email, birth date, address — optional |

### ClientOrigin (clinic catalog)

| Field | Notes |
| --- | --- |
| `clinic_id` | Tenant-scoped |
| `name` | Unique per clinic (e.g. Instagram) |
| `is_active` | Soft deactivate; historical client links kept |

### Campaign (clinic catalog)

| Field | Notes |
| --- | --- |
| `clinic_id` | Tenant-scoped |
| `client_origin_id` | Parent origin |
| `name` | Unique per clinic + origin |
| `is_active` | Soft deactivate; historical client links kept |

Attribution fields on the client are optional. When `campaign_id` is set, `client_origin_id` is required and must match the campaign’s origin.

### Permissions

`clients.view`, `clients.create`, `clients.update`, `clients.delete`  
`client_origins.manage`, `campaigns.manage`

---

## 7. Payment methods & card fees (separate catalogs)

Payment setup is **its own module**, not buried inside sales.

### PaymentMethod

Examples: cash (`dinheiro`), PIX, check (`cheque`), credit card (`cartao_credito`), debit card, boleto, other.

| Field | Notes |
| --- | --- |
| `clinic_id` | Tenant-scoped |
| `name`, `code` | Display + stable code (unique per clinic) |
| `kind` | `cash`, `pix`, `check`, `credit_card`, `debit_card`, `boleto`, `other` |
| `requires_card_meta` | True for card kinds (auto-set) |
| `fee_percent`, `fee_fixed` | Optional method-level fees for **non-card** kinds (e.g. boleto generation fee). Null for card kinds — use `CardFeeRule` |
| `is_active` | Soft disable |

### Card operator, brand & rates

When `kind` is `credit_card` or `debit_card`:

| Entity | Purpose |
| --- | --- |
| `CardOperator` | Maquininha / acquirer (Cielo, Stone, Rede…). `auto_anticipate` marks machines that settle/anticipate installments automatically vs D+N |
| `CardBrand` | Visa, Mastercard, Elo, Amex, … (required on fee rules; seeded defaults) |
| `CardFeeRule` | `payment_method` + operator + **required** brand + exact installment count → `fee_percent` and/or `fee_fixed` |

Debit rules use `installments = 1`. Multiple operators per clinic allow different MDR tables (e.g. one anticipating machine vs one without).

Net received for a gross amount: use `PaymentFeeCalculator` / model helpers (`netAmountFor` / `feeAmountFor`). Per-installment manual anticipation UI is deferred (receivables). Monthly machine rent is deferred to fixed costs.

### Permissions

`payment_methods.manage`, `card_operators.manage`, `card_brands.manage`, `card_fees.manage`

---

## 8. Sales

A **sale** is the **commercial** event: what was agreed and charged. It does **not** move stock.

| Field | Purpose |
| --- | --- |
| `clinic_id` | Tenant |
| `client_id` | Buyer / patient |
| `sold_by_user_id` | Authenticated seller |
| `sold_at` | Sale datetime |
| `expected_amount` | Auto-calculated from product lines (`Σ line_total`) |
| `effective_amount` | Actual charged amount (malleable; tracks expected until manually set) |
| `effective_amount_is_manual` | When true, item sync does not overwrite `effective_amount` |
| `status` | `draft`, `confirmed`, `cancelled` |
| `notes` | Free text |

### SaleItem (always product — model B)

Selecting a **protocol** on a sale **explodes** its products into lines (qty editable). Extra standalone products can be added. Protocol suggested/min/special remain **UI references** (`protocol_references` on the resource), not priced sale lines.

| Field | Purpose |
| --- | --- |
| `sale_id` | Parent |
| `product_id` | Product line |
| `source_protocol_id` | Nullable origin when exploded from a protocol |
| `product_name` | Name snapshot at line write (KPI / history) |
| `quantity` | Editable qty |
| `list_unit_price` / `list_line_total` | Catalog/list price snapshot at line write |
| `unit_price` | Offered price snapshot (defaults to product `sale_price`; editable) |
| `unit_cost` | Cost snapshot for later margin |
| `min_unit_price` | Snapshot `coalesce(min_sale_price, cost)` |
| `line_total` | `quantity × unit_price` |

API returns `min_amount`, `is_below_minimum`, and per-line below-min flags. Confirming below `min_amount` requires `confirm_below_minimum: true` (soft gate with explicit confirmation). Catalog changes after the line is written do **not** rewrite these snapshots.

### SalePayment

| Field | Purpose |
| --- | --- |
| `sale_id` | Parent |
| `payment_method_id` | Method used |
| `amount` | Portion paid with this method |
| `card_operator_id` / `card_brand_id` / `installments` | Required when method `requires_card_meta` |
| `paid_at` | Optional |

`Σ payment.amount` **must equal** `effective_amount` on confirm. Card **fees are not** snapshotted here (deferred to receivables / installment anticipation).

No hard delete — cancel only (`draft` or `confirmed` → `cancelled`). Confirmed sales only allow `notes` updates.

### Permissions

`sales.view`, `sales.create`, `sales.update`, `sales.confirm`, `sales.cancel`

---

## 9. Budgets (quotes)

Budgets are **versioned commercial proposals** generated from a **draft sale**. They do **not** move stock.

| Field | Purpose |
| --- | --- |
| `clinic_id` / `sale_id` / `client_id` | Tenant + source sale + patient |
| `created_by_user_id` | Who generated the proposal |
| `version` | Incremental per sale (`v1`, `v2`, …) |
| `status` | `draft`, `sent`, `accepted`, `rejected`, `expired`, `superseded` |
| `expected_amount` / `effective_amount` / `min_amount` | Copied from sale at snapshot time |
| `notes` / `valid_until` | Optional proposal metadata |
| `sent_at` / `accepted_at` / `rejected_at` | Transition timestamps |

### BudgetItem (immutable snapshot)

| Field | Purpose |
| --- | --- |
| `product_id` / `product_name` | Product + name at proposal time |
| `source_protocol_id` | Optional protocol origin |
| `quantity` | Qty at proposal time |
| `list_unit_price` / `list_line_total` | Catalog/list price at proposal time |
| `unit_price` / `line_total` | Offered price from the sale |
| `unit_cost` / `min_unit_price` | Cost and floor snapshots |

**Flow:** edit draft sale → `POST /sales/{sale}/budgets` (supersedes prior `draft`/`sent`) → `send` → `accept` → same sale stays `draft` for payments/confirm. Catalog price changes never rewrite existing budget or confirmed sale lines.

### Permissions

`budgets.view`, `budgets.create`, `budgets.update`, `budgets.convert`

---

## 10. Documents / contracts

Generated from **budget** (Phase 8) or sale (future) + clinic branding + client data. **No stock effect.**

| Field | Purpose |
| --- | --- |
| `clinic_id` | Tenant |
| `client_id` | Patient |
| `sale_id` / `budget_id` | Source (`budget_id` for orçamento PDF; `sale_id` reserved) |
| `type` | `budget_pdf` (Phase 8); later `contract`, `consent`, `receipt`, … |
| `status` | `issued` (Phase 8); later `draft`, … |
| `storage_path` / `filename` / `mime_type` | PDF on MinIO under `documents/{clinic_id}/budgets/...` |
| `payload` (JSON) | Snapshot: branding + client + budget lines with **list vs offered** + discount |
| `generated_by_user_id` | Who generated |

**Phase 8 delivery:** `POST /budgets/{budget}/pdf` renders Blade → Browsershot/Chromium → MinIO → `Document` row. Regenerating creates a **new** document (history). Sale contract/receipt PDFs are out of scope until a later phase.

Typical later order: **confirm sale → issue contract → start treatment**.

### Permissions

`documents.view`, `documents.generate`, `documents.delete` (+ `clinics.branding` for logo/colors)

---

## 11. Treatments + Appointments (clinical application — stock + real cost)

A **treatment** is the clinical case opened from a **confirmed sale** (1:1).  
**Appointments** are calendar sessions under that treatment (application, return with products, evaluation with zero consumption). Stock moves only when an **appointment is completed**.

### Why separate from sale

- At sale time the product has **not** been applied yet.
- A sold package (e.g. 3 applications) is fulfilled across **multiple return visits**.
- The doctor may change quantities and **add complementary products** (courtesy or charged).
- Stock and **clinic cost** follow **real consumption per session**, including complimentary extras.

### Treatment

| Field | Purpose |
| --- | --- |
| `clinic_id` | Tenant |
| `sale_id` | Commercial source (unique) |
| `client_id` | Patient (denormalized) |
| `opened_by_user_id` | Who opened the case |
| `status` | `open`, `completed`, `cancelled` |
| `notes` | Clinical notes |
| `total_cost` | Rollup of completed appointment costs |

### Appointment

| Field | Purpose |
| --- | --- |
| `treatment_id` | Parent clinical case |
| `scheduled_at` | Planned visit (nullable for immediate start) |
| `status` | `scheduled`, `in_progress`, `completed`, `cancelled` |
| `professional_user_id` | Who performed the session |
| `started_at` / `finished_at` / `duration_minutes` | Session window |
| `stock_warning` | JSON warnings captured on start (stock < suggested) |
| `total_cost` / `total_charged_on_appointment` | Session cost + extra charges |

### AppointmentConsumption

| Field | Purpose |
| --- | --- |
| `appointment_id` | Parent session |
| `product_id` | Product used |
| `quantity` | Actual quantity used |
| `source` | `suggested` (from sale) \| `extra` |
| `sale_item_id` | Optional link to sale line |
| `is_complimentary` | Courtesy extra (no patient charge) |
| `charged_amount` | Amount charged for paid extras (0 otherwise) |
| `sale_payment_id` | New `SalePayment` when extra is charged |
| `unit_cost` / `line_cost` | Cost snapshot at appointment complete |

### Session flow

1. Open treatment from confirmed sale.
2. Create one or more appointments (schedule returns).
3. Start appointment → suggested remaining qty + optional stock warnings (non-blocking).
4. Sync consumptions (suggested / complimentary extra / charged extra + payment).
5. Complete appointment → snapshot costs → stock out (`allowNegative`) → reference movement on appointment.
6. Fulfillment report: sold vs consumed vs remaining + current stock.

### Cost accounting note

- **Revenue** primarily lives on the **sale** (`effective_amount` + payments, including extra session payments).
- **Real product cost** lives on appointments rolled into **treatment.total_cost**, including complimentary extras.
- Margin analysis later: sale revenue − treatment real cost (− card fees, etc.).

### Permissions

`treatments.view`, `treatments.manage`, `treatments.start`, `treatments.complete`, `treatments.cancel`

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
| Clinics | `clinics.view`, `clinics.manage` (platform), `clinics.branding` (clinic logo/colors) |
| Catalogs | `product_types.manage`, `brands.manage`, `units.manage` |
| Products | `products.view`, `products.create`, `products.update`, `products.delete`, `products.adjust_stock` |
| Protocols | `protocols.view`, `protocols.create`, `protocols.update`, `protocols.delete` |
| Clients | `clients.view`, `clients.create`, `clients.update`, `clients.delete`, `client_origins.manage`, `campaigns.manage` |
| Payments | `payment_methods.manage`, `card_operators.manage`, `card_brands.manage`, `card_fees.manage` |
| Sales | `sales.view`, `sales.create`, `sales.update`, `sales.confirm`, `sales.cancel` |
| Metrics | `metrics.view` (`GET /metrics/commercial` — revenue by `sold_at`, payment mix, budget funnel, series) |
| Budgets | `budgets.view`, `budgets.create`, `budgets.update`, `budgets.convert` |
| Documents | `documents.view`, `documents.generate`, `documents.delete` |
| Treatments | `treatments.view`, `treatments.manage`, `treatments.start`, `treatments.complete`, `treatments.cancel` |

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
