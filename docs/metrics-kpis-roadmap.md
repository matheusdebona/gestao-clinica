# Métricas & KPIs — guia de implementação

Documento de referência para construir o dashboard e os indicadores da clínica a partir dos dados **já existentes** (e poucos campos extras quando necessários).

**Resposta direta:** não implemente tudo de uma vez. Entregue em **4 ondas** (abaixo). Cada onda fecha endpoints + testes + um bloco de UI. Implementar tudo junto atrasa o feedback e mistura métricas frágeis (ex.: ROI de mídia sem gasto de campanha) com métricas sólidas (receita, margem real).

Complementa: [`domain-model.md`](./domain-model.md), [`domain-roadmap.md`](./domain-roadmap.md), [`produto-financeiro.md`](./produto-financeiro.md).

---

## 1. Fontes de dados (o que já existe)

| Domínio | Dados úteis para KPI |
| --- | --- |
| **Client** | `client_origin_id`, `campaign_id`, `initial_consultation_amount`, `created_at`, `is_active` |
| **ClientOrigin / Campaign** | catálogos de canal e campanha |
| **Sale** | `status` (confirmed), `expected_amount`, `effective_amount`, `sold_at` / `confirmed_at` |
| **SaleItem** | `list_*` vs preço ofertado, `product_id`, `source_protocol_id`, quantidades |
| **SalePayment** | método, valor, taxas de cartão (via catálogo) |
| **Budget** | status (`sent` / `accepted` / `rejected` / …), versões |
| **Treatment** | 1:1 com sale, `total_cost` |
| **Appointment** | status, `scheduled_at`, `professional_user_id`, `total_cost`, charged extras |
| **AppointmentConsumption** | qty real, `unit_cost` / `line_cost`, complimentary vs charged |
| **Product / StockMovement** | `stock_quantity`, `min_stock`, `cost`, movimentos com referência a appointment |

Regra de ouro: **toda métrica é clinic-scoped** e aceita filtro de período (`from` / `to`).

---

## 2. Onda A — Dashboard comercial (base sólida)

**Objetivo:** dono da clínica vê “quanto entrou” sem ambiguidade.

### KPIs

| KPI | Fórmula / definição | Fonte |
| --- | --- | --- |
| Receita do período | Σ `sale.effective_amount` onde `status = confirmed` e data no range | Sale |
| Nº de vendas | count das mesmas sales | Sale |
| Ticket médio | receita ÷ nº vendas | Sale |
| Desconto médio % | 1 − (Σ offered / Σ list) nos itens das sales confirmadas | SaleItem |
| Mix de pagamento | Σ payment.amount agrupado por `payment_method` (e kind) | SalePayment |
| Funil de orçamento | count por status; taxa aceite = accepted ÷ sent (no período) | Budget |

### Passo a passo técnico

1. **Contrato de API**
   - `GET /api/v1/metrics/commercial?from=&to=`
   - Response: totais + séries diárias/semanais opcionais (`series[]`).
2. **Service** `CommercialMetricsService`
   - Queries agregadas (não carregar todas as sales na memória).
   - Datas: **`sold_at`** (não existe `confirmed_at`; na confirmação o sistema só garante `sold_at`).
   - Período: `from` + `to` obrigatórios (`Y-m-d`, inclusivos), máximo ~5 anos (1825 dias).
   - Série: `granularity=day|week|month` (opcional). Default automático: ≤62d → day; ≤366d → week; senão → month. Buckets vazios preenchidos com 0.
3. **Permission** `metrics.view` (admin + papéis gerenciais).
4. **Testes**
   - 2 clínicas isoladas.
   - Sale draft **não** entra na receita.
   - Período exclui vendas fora do range.
5. **UI (PWA depois)**
   - Cards: Receita | Ticket | Nº vendas | Taxa aceite orçamento.
   - Gráfico simples de receita no tempo.
   - Tabela mix de pagamento.

**Status:** implementado na API (`GET /api/v1/metrics/commercial`).

**DoD onda A:** endpoint + testes verdes; números batem com soma manual de 2–3 sales de fixture.

---

## 3. Onda B — Aquisição (origem / campanha / consulta)

**Objetivo:** saber de onde vem o paciente e se a avaliação vira venda.

### KPIs

| KPI | Fórmula / definição | Fonte |
| --- | --- | --- |
| Novos clientes por origem | count clients com `created_at` no período, group by origin | Client + ClientOrigin |
| Novos clientes por campanha | idem group by campaign | Client + Campaign |
| Receita da consulta inicial | Σ `initial_consultation_amount` dos clients criados no período | Client |
| Conversão consulta → venda | clients com amount/consulta (ou todos novos) que têm ≥1 Sale confirmed no período (ou lifetime) ÷ total | Client ↔ Sale |
| Receita de vendas por origem | Σ effective_amount de sales confirmed cujo `client.origin` = X | Sale ⨝ Client |
| CAC aproximado por origem | receita consultas ÷ nº clientes novos **ou** (se houver gasto de mídia) gasto ÷ clientes | Client (+ futuro Campaign spend) |
| ROI aproximado | receita vendas do canal ÷ receita consultas do canal | Client + Sale |

### Passo a passo técnico

1. Garantir índices: `(clinic_id, created_at)`, `(clinic_id, client_origin_id)`, `(clinic_id, campaign_id)` em `clients`.
2. `GET /api/v1/metrics/acquisition?from=&to=&group_by=origin|campaign`
3. Conversão **lifetime** (decidido): cliente criado no período com ≥1 sale `confirmed` onde `sold_at` ≥ `client.created_at`. A venda pode cair **depois** do período (orçamento no mês N, pagamento no N+1).
4. Service `AcquisitionMetricsService` — cohort de clients + subquery de sales pós-cadastro.
5. Testes: Instagram com sale no mês seguinte; Facebook sem sale; draft não converte; isolamento; `group_by=campaign`.
6. UI (depois): ranking de canais (clientes | receita consulta | receita vendas | conversão %).

**Sem `campaigns.spend_amount` nesta onda** — CAC/ROI de ads fica para B+ quando houver gasto de mídia. Enquanto isso: `avg_consultation_amount` e `sales_to_consultation_ratio` (receita vendas ÷ receita consultas).

**Status:** implementado (`GET /api/v1/metrics/acquisition?from=&to=&group_by=origin|campaign`).

**DoD onda B:** ranking por origem/campanha confiável; conversão lifetime testada.

---

## 4. Onda C — Margem real (Sale vs Treatment/Appointment)

**Objetivo:** “vendi R$ X, mas o custo real do que foi aplicado foi R$ Y”.

### KPIs

| KPI | Fórmula / definição | Fonte |
| --- | --- | --- |
| Custo clínico do período | Σ `appointment.total_cost` (completed) **ou** `treatment.total_cost` | Appointment / Treatment |
| Margem bruta clínica | receita sales confirmed − custo appointments completed (mesmo período — ver nota) | Sale + Appointment |
| % margem | margem ÷ receita | — |
| Custo de cortesia | Σ line_cost onde consumption `is_complimentary = true` | AppointmentConsumption |
| Receita extra na sessão | Σ charged em consumptions / payments de extra | Appointment + SalePayment |
| Margem por protocolo/produto | receita alocada vs custo consumido | SaleItem + Consumption |

**Nota de matching temporal:**  
Receita de setembro vs custo de sessões de setembro **não** é a mesma coisa que margem da venda X (paciente pode aplicar em outubro). Ofereça **dois modos**:

- `mode=period` — receita no período vs custos de sessões no período (visão caixa/operação).
- `mode=cohort_sale` — para sales confirmadas no período, soma custos de appointments já completed ligados ao treatment daquela sale (visão rentabilidade da venda; pode subestimar se ainda há saldo a aplicar).

### Passo a passo técnico

1. Documentar os dois modos (este arquivo).
2. `GET /api/v1/metrics/margin?from=&to=&mode=period|cohort_sale` (default `period`)
3. `MarginMetricsService` com queries separadas por modo.
4. Receita = `sale.effective_amount` + extras cobrados (`appointment.total_charged_on_appointment`); custo = `appointment.total_cost` (completed); cortesia = Σ `line_cost` onde `is_complimentary`.
5. Data do custo em `period`: `finished_at`. Data da receita: `sold_at`.
6. Fora de escopo: impostos, taxas de cartão, custos fixos.
7. Testes: period vs cohort com datas cruzadas; cortesia/extras; isolamento; draft/não-completed fora.
8. UI (depois): card Margem | % | Custo cortesia; aviso saldo a aplicar (`pending_fulfillment_count` em cohort).

**Status:** implementado (`GET /api/v1/metrics/margin`).

**DoD onda C (API):** margem period e cohort testadas; `pending_fulfillment_count` em cohort. UI pendente.

---

## 5. Onda D — Estoque + operação clínica

**Objetivo:** evitar ruptura e enxergar agenda/produção.

### KPIs estoque

| KPI | Definição | Fonte |
| --- | --- | --- |
| SKUs abaixo do mínimo | count `stock_quantity <= min_stock` | Product |
| Valor em estoque | Σ `stock_quantity × cost` | Product |
| Consumo no período | Σ qty out em StockMovement (ref appointment) | StockMovement |
| Eventos de estoque negativo | count completes que deixaram stock &lt; 0 (ou stock atual &lt; 0) | Product / logs |
| Dias de cobertura | stock atual ÷ (consumo médio diário 30d) | Product + movements |

### KPIs operação

| KPI | Definição | Fonte |
| --- | --- | --- |
| Sessões por status | scheduled / completed / cancelled no período | Appointment |
| Taxa de cancelamento | cancelled ÷ (scheduled+completed+cancelled) | Appointment |
| Saldo a aplicar (unidades) | sold − consumed por sale/treatment (fulfillment) | TreatmentService.fulfillment agregado |
| Sessões por profissional | count + custo + (se possível) receita associada | Appointment.professional_user_id |

### Passo a passo técnico

1. `GET /api/v1/metrics/inventory`
2. `GET /api/v1/metrics/operations?from=&to=`
3. Reusar lógica de fulfillment em agregado (`FulfillmentMetricsService`).
4. Alertas (Phase 10): job diário low-stock (atual + projeção do dia) → **in-app + push stub** já entregue; email/WhatsApp/FCM depois dos endpoints de métricas onda D.
5. Testes de isolamento + stock math.
6. UI: lista low-stock; cards de sessões; tabela “pacotes com saldo pendente”.

**DoD onda D:** low-stock e saldo pendente acionáveis no dia a dia. **API entregue** (`/metrics/inventory`, `/metrics/operations`); UI na PWA.

---

## 6. Ordem recomendada e o que NÃO fazer de uma vez

```text
Onda A  Comercial          → 1–2 PRs
Onda B  Aquisição          → 1 PR (+ opcional spend em campaign)
Onda C  Margem real        → 1–2 PRs (period depois cohort)
Onda D  Estoque/operação   → 1 PR
Depois  Alertas + PWA charts
```

| Evitar | Por quê |
| --- | --- |
| Um único PR “todas as métricas” | Review impossível; bugs escondem KPI errado |
| Misturar ROI de Ads sem `campaign.spend` | Número mentiroso |
| Margem só “por período” sem explicar | Dono acha que venda de ontem já está rentável |
| Recalcular tudo em PHP loop | Timeout; use SQL aggregates |
| Dashboard sem `metrics.view` | Vazamento entre papéis |

---

## 7. Contrato API sugerido (resumo)

```
GET /metrics/commercial?from&to
GET /metrics/acquisition?from&to&group_by=origin|campaign
GET /metrics/margin?from&to&mode=period|cohort_sale
GET /metrics/inventory
GET /metrics/operations?from&to
```

Permission: `metrics.view`  
Todos clinic-scoped via `CurrentClinic`.

Response shape sugerida:

```json
{
  "data": {
    "from": "2026-09-01",
    "to": "2026-09-30",
    "kpis": { "revenue": "10000.00", "ticket_avg": "500.00" },
    "breakdown": [ { "key": "instagram", "label": "Instagram", "value": "..." } ]
  }
}
```

---

## 8. Checklist por onda (copiar para issues/PRs)

### A — Comercial
- [x] Permission `metrics.view`
- [x] `CommercialMetricsService` + endpoint
- [x] Feature tests (isolation, draft excluded, granularity)
- [x] Docs: campo de data da receita = `sold_at`; períodos via `from`/`to` + granularidade
- [ ] (UI) 4 cards + mix pagamento

### B — Aquisição
- [x] `AcquisitionMetricsService` + endpoint
- [x] Definição de conversão documentada (**lifetime**)
- [x] Tests por origin/campaign
- [ ] (Opcional / B+) `campaigns.spend_amount`
- [ ] (UI) ranking canais

### C — Margem
- [x] modes `period` e `cohort_sale`
- [x] Cortesia e extras charged
- [x] Tests period vs cohort / isolamento
- [ ] (UI) margem + aviso saldo pendente
- [ ] (C+) breakdown por produto/protocolo
- [ ] (Futuro) impostos, taxas, custos fixos

### D — Estoque / operação
- [x] inventory + operations endpoints
- [x] Agregação fulfillment
- [x] Tests stock / cancel rate
- [ ] (UI) low-stock + saldo a aplicar
- [x] Job de alerta low-stock (já entregue na Phase 10; lead-time-aware reorder depois)

---

## 9. Primeiro dashboard (MVP visual)

Só depois da **Onda A + B** (mínimo) ou A+B+C (ideal):

1. Receita do mês  
2. Margem real (se C pronta; senão ocultar)  
3. Ticket médio  
4. Top 5 origens (clientes + receita)  
5. Conversão consulta → venda  
6. Alertas: estoque baixo + pacotes com saldo  

---

## 10. Decisões de produto

1. Data canônica da sale para métricas: **`sold_at`** (Onda A).  
2. Períodos longos: `from`/`to` + agregação `day|week|month` (auto ou explícita) — evita séries diárias de 365+ pontos sem necessidade.  
3. Conversão (Onda B): **lifetime** — sale `confirmed` com `sold_at` ≥ `client.created_at` (pode ser após o período de cadastro).  
4. Incluir `initial_consultation_amount` na “receita total” do dashboard ou card separado (recomendação: **card separado**, não somar com Sale).  
5. Canal de alerta low-stock: email vs WhatsApp — pendente.  
6. Gasto de campanha (`spend_amount`) adiado para B+.  
7. Margem (Onda C): receita = `effective_amount` + extras cobrados; custo = `appointment.total_cost` completed; `period` usa `finished_at` para custo; impostos/taxas/fixos fora do escopo.

**Status Onda D:** implementado — `GET /api/v1/metrics/inventory` (snapshot + `from`/`to` opcionais, default 30d) e `GET /api/v1/metrics/operations` (período obrigatório, saldo a aplicar + lista).

Próximo: PWA / charts consumindo A–D; alerta de reposição por `lead_time_days`.
