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
5. Creates **sales** (and later **budgets** / **contracts**) that consume protocols and/or individual products, link to a client and clinic, and **decrement stock**.
6. Warns when stock is low.

All commercial and clinical data belongs to a **clinic** (tenant). Users belong to a clinic and only see that clinic’s data (except platform super-admins).

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
│   ├── Brand                (optional entity; not free-text only)
│   ├── UnitOfMeasure        (mg, ml, unit, kg, …)
│   └── Product
│         ├── cost, sale_price
│         ├── stock_quantity, min_stock
│         └── purpose / description
├── Protocol
│   └── ProtocolItem  → Product + quantity_used
├── Client (patient)
├── Payment catalog
│   ├── PaymentMethod        (cash, PIX, check, credit_card, …)
│   ├── CardOperator         (Cielo, Stone, …)
│   ├── CardBrand            (Visa, Mastercard, …)  [optional]
│   └── CardFee / rate rules (operator + brand + installments → %)
├── Budget (optional pre-sale)
│   └── BudgetItem
├── Sale
│   ├── SaleItem             (protocol and/or product lines)
│   └── SalePayment
└── Document / Contract      (generated from sale/budget + clinic + client)
```

---

## 4. Products & stock

### Product

| Field | Purpose |
| --- | --- |
| `clinic_id` | Tenant |
| `name` | Product name |
| `product_type_id` | Category (botox, filling, botulinum toxin, acid, …) — **registered catalog**, not hard-coded enum forever |
| `brand_id` | Brand |
| `unit_of_measure_id` | kg, mg, ml, unit, … |
| `purpose` / `description` | What it is for |
| `cost` | Acquisition / unit cost |
| `sale_price` | Default selling price |
| `stock_quantity` | Current stock (decimal-friendly for mg/ml) |
| `min_stock` | Threshold for low-stock warning |
| `is_active` | Soft disable |

### Stock behavior

| Event | Effect |
| --- | --- |
| Manual adjustment / restock | Increase or set stock (audited later) |
| Confirmed **sale** | Decrease by quantities on sale lines (product lines + protocol exploded products) |
| Budget | **Does not** decrease stock (unless later we add reservation — open question) |
| Sale cancel / void | Restore stock (when cancellation is implemented) |

### Low-stock warning

- Product is “low” when `stock_quantity <= min_stock`.
- API: list/filter low-stock products; optional notification channel later (WhatsApp/email — out of Phase 1).

### Permissions (examples)

`products.view`, `products.create`, `products.update`, `products.delete`, `products.adjust_stock`, `product_types.manage`, `brands.manage`, `units.manage`

---

## 5. Protocols (procedure = product bundle)

A **protocol** is a reusable grouping of products for a procedure.

| Field | Purpose |
| --- | --- |
| `clinic_id` | Tenant |
| `name` | e.g. “Full face filling” |
| `description` | Optional |
| `sale_price` | Default protocol price |
| `min_price` | Floor the seller should not go below (warning or hard block — open question) |
| `total_cost` | Sum of (product.cost × quantity) — **stored and/or recalculated** when items change |
| `is_active` | Soft disable |

### ProtocolItem

| Field | Purpose |
| --- | --- |
| `protocol_id` | Parent |
| `product_id` | Component product |
| `quantity` | Amount of that product consumed by the protocol (in product’s UoM) |

**Cost formula:** `total_cost = Σ (product.cost × protocol_item.quantity)`  
**Margin (derived):** `sale_price - total_cost` (not necessarily stored).

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

A **sale** is the commercial event that ties clinic + client + lines + payments + date, and **moves stock**.

| Field | Purpose |
| --- | --- |
| `clinic_id` | Tenant |
| `client_id` | Buyer / patient |
| `sold_by_user_id` | Authenticated seller |
| `sold_at` / `service_at` | Sale date and service datetime |
| `service_duration_minutes` | Time of this attendance |
| `expected_amount` | Auto-calculated from lines (protocols + products at registered prices) |
| `effective_amount` | Actual charged amount (may differ; respect protocol `min_price` policy) |
| `status` | `draft`, `confirmed`, `cancelled` |
| `notes` | Free text |

### SaleItem

Each line is either a **protocol** or a **standalone product** (or both kinds on the same sale).

| Field | Purpose |
| --- | --- |
| `sale_id` | Parent |
| `line_type` | `protocol` \| `product` |
| `protocol_id` | If protocol line |
| `product_id` | If product line (or exploded component for stock — see below) |
| `quantity` | How many of that protocol/product |
| `unit_price` | Snapshot at sale time |
| `line_total` | Snapshot |

**Stock on confirm:** for each protocol line, explode `ProtocolItem`s and decrement product stock by `protocol_qty × item.quantity`; for product lines, decrement by line quantity. Snapshots of prices/costs stored on the sale so catalog changes do not rewrite history.

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

- Does **not** decrement stock on save.
- Status: `draft`, `sent`, `accepted`, `rejected`, `expired`, `converted`.
- Can **convert to sale** (copy lines + client + expected amounts).

Feeds later **contracts/documents** with the same client/protocol/product data.

### Permissions

`budgets.view`, `budgets.create`, `budgets.update`, `budgets.convert`

---

## 10. Documents / contracts

Generated from budget or sale + clinic + client data (and protocol/product lines).

| Field | Purpose |
| --- | --- |
| `clinic_id` | Tenant |
| `client_id` | Patient |
| `sale_id` / `budget_id` | Source |
| `type` | `contract`, `consent`, `receipt`, … |
| `status` | `draft`, `issued`, … |
| `storage_path` | PDF/file on MinIO (S3) |
| `payload` (JSON) | Snapshot of values used to render the document |

Templates and e-sign are later; Phase definition only reserves the model and MinIO storage.

### Permissions

`documents.view`, `documents.generate`, `documents.delete`

---

## 11. Domain flow (happy path)

```text
Clinic exists
  → Register catalogs (types, brands, units, payment methods, card fees)
  → Register products (cost, sale price, stock, min_stock)
  → Build protocols (products + qty → total_cost, sale_price, min_price)
  → Register clients
  → (Optional) Create budget → convert
  → Create sale (protocol and/or products)
       → expected_amount calculated
       → effective_amount + payment(s)
       → confirm → stock down
  → Low-stock list / alerts
  → Generate contract/doc from sale data
```

---

## 12. Permission catalog (domain, additive to Phase 1)

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

All checks remain **permission-first**; roles only group these permissions per clinic job (receptionist, seller, stock manager, clinic admin).

---

## 13. Open questions

1. **User ↔ clinic** — Confirm: one clinic per user for now, or membership in many clinics?
2. **`min_price` on protocol** — Hard block below minimum, or warning only?
3. **Partial payments** — Must payments sum exactly to `effective_amount`, or allow outstanding balance?
4. **Installments** — Store installment plan on the sale payment, or only fee lookup?
5. **Stock reservation** — Reserve stock when budget is accepted, or only on sale confirm?
6. **Product types / brands / units** — Clinic-scoped catalogs only, or platform defaults cloned into each clinic?
7. **Currency** — BRL only?
8. **Sale performer** — Only the logged-in user, or selectable professional?
9. **Contracts** — HTML/PDF templates first, or integrate a third-party doc tool later?
10. **WhatsApp** — Store number only for now, or plan Meta API integration soon?

---

## 14. Decision summary

| Topic | Decision |
| --- | --- |
| Tenant | Clinic; all domain data clinic-scoped |
| Commercial core | Products → Protocols → Sales (+ Clients + Payments) |
| Stock | Decrements on confirmed sale; low-stock via `min_stock` |
| Protocol | Bundle of products with qty, sale_price, min_price, total_cost |
| Sale lines | Protocol and/or individual products |
| Money | expected_amount (auto) + effective_amount (actual) + payment method(s) |
| Cards | Separate operators / fee rules module |
| Budgets & docs | Same data lineage; docs stored on MinIO |
| Authz | Same permission-first model, clinic-scoped queries |
