# Dados do produto para custo, receita e margem

Documento de avaliação: o que o **produto** precisa guardar para a plataforma medir custos, recebimentos e lucros de forma consistente depois (protocolos, vendas, tratamentos, taxas de cartão).

Complementa [`domain-model.md`](./domain-model.md) e a visão em [`visao-da-plataforma.md`](./visao-da-plataforma.md).

---

## 1. O que a plataforma precisa calcular (meta)

| Indicador | Ideia | De onde vem |
| --- | --- | --- |
| **Receita bruta** | Quanto entrou das vendas | `Sale.effective_amount` (+ extras cobrados no tratamento, se houver) |
| **Receita líquida** | Depois de taxas de cartão/operadora | Receita − taxas (`SalePayment` + regras de cartão) |
| **Custo do produto usado (CMV)** | Quanto custou o que foi aplicado | `TreatmentConsumption` (`quantity × unit_cost` snapshot), inclusive cortesia |
| **Margem bruta** | Lucro antes de taxas/operação | Receita bruta − CMV |
| **Margem líquida** | Depois das taxas de pagamento | Receita líquida − CMV |
| **Valor em estoque** | Capital parado em produto | `Σ (stock_quantity × cost)` por produto/clínica |
| **Margem unitária do cadastro** | Saúde do preço vs custo atual | `sale_price − cost` (e %) |
| **Margem do protocolo** | Procedimento precificado | `protocol.sale_price − protocol.total_cost` |

Regra já travada: **estoque e CMV real só fecham no fim do tratamento**, não na venda.

---

## 2. Campos obrigatórios no produto (Fase 2)

Esses campos são o **mínimo consistente** para alimentar os cálculos acima.

| Campo | Tipo | Para que serve nos cálculos |
| --- | --- | --- |
| `clinic_id` | FK | Isola margem/estoque por clínica |
| `name` | string | Identificação |
| `product_type_id` | FK | Agrupar margem por tipo (botox, ácido, …) |
| `brand_id` | FK | Agrupar margem por marca |
| `unit_of_measure_id` | FK | Garante que qty × custo usa a **mesma unidade** (ml, mg, un) |
| `purpose` | text nullable | Contexto clínico (não entra em fórmula) |
| `cost` | decimal(15,4) | **Custo médio unitário atual** (base do CMV e do valor em estoque) |
| `sale_price` | decimal(15,2) | Preço padrão de venda (receita esperada / linha de venda) |
| `min_sale_price` | decimal(15,2) nullable | Piso comercial do item avulso (análogo ao `min_price` do protocolo) |
| `stock_quantity` | decimal(15,4) | Quantidade atual na UoM do produto |
| `min_stock` | decimal(15,4) | Alerta operacional (não é financeiro, mas protege o CMV futuro) |
| `sku` | string nullable | Código interno / fornecedor (rastreio) |
| `is_active` | bool | Tira do catálogo sem apagar histórico |

### Por que `cost` com mais casas decimais

Toxina/ácido em mg/ml gera custos unitários pequenos. `decimal(15,4)` no custo e no estoque evita arredondar errado o CMV.

### Por que `min_sale_price`

Sem isso, só o protocolo tem piso. Produto vendido avulso também precisa de referência de margem mínima.

---

## 3. O que NÃO fica só no cadastro do produto

Para as contas fecharem depois, o produto é a **fonte atual**; os eventos **congelam** valores:

| Momento | O que grava | Por quê |
| --- | --- | --- |
| **Venda** | Snapshot de `unit_price` / totais | Receita histórica não muda se o preço de catálogo mudar |
| **Entrada de estoque** | `unit_cost` daquela entrada + recalcula `product.cost` médio | Compras em preços diferentes não destroem o CMV |
| **Fim do tratamento** | Snapshot `unit_cost` + `line_cost` do que foi usado | CMV real (inclui cortesia) |
| **Pagamento cartão** | Taxa da operadora naquela venda | Receita líquida |

Sem esses snapshots, alterar o custo do produto hoje reescreveria o lucro de ontem.

---

## 4. Método de custo (decisão Fase 2)

**Custo médio ponderado** no cadastro do produto.

Na **entrada** de estoque com custo informado:

```text
novo_custo = (estoque_atual × custo_atual + qty_entrada × custo_entrada)
             / (estoque_atual + qty_entrada)
```

- Se estoque atual era 0 → `cost = custo_entrada`.
- Saídas (tratamento / ajuste negativo) **não** recalculam a média; usam o `cost` vigente no snapshot.
- Ajuste de inventário sem compra (quebra/perda) baixa quantidade **sem** mudar o custo médio (ou com regra explícita no movimento).

**Fora do escopo agora (evolução):** lotes/FIFO/validade por frasco. Dá para evoluir depois sem mudar a ideia de snapshot no tratamento.

---

## 5. Movimento de estoque (necessário já na Fase 2)

Cada alteração de estoque gera um registro (auditoria + base financeira):

| Campo | Uso |
| --- | --- |
| `product_id`, `clinic_id` | Escopo |
| `type` | `in` (compra/entrada), `out` (saída manual), `adjustment` |
| `quantity` | Sempre positiva; o sinal vem do `type` |
| `unit_cost` | Obrigatório em `in` (compra); opcional/derivado nos outros |
| `cost_before` / `cost_after` | Rastreio do custo médio |
| `stock_before` / `stock_after` | Rastreio de quantidade |
| `reason` | compra, quebra, inventário, … |
| `user_id` | Quem fez |
| `reference_type` / `reference_id` | Depois: tratamento, compra, etc. (nullable na Fase 2) |

Na Fase 9, a baixa do tratamento cria movimentos `out` (ou tipo `treatment`) com o mesmo padrão.

---

## 6. Indicadores derivados no produto (API pode expor)

Não precisam ser colunas persistidas (podem ser calculados no Resource):

| Indicador | Fórmula |
| --- | --- |
| `unit_margin` | `sale_price - cost` |
| `unit_margin_percent` | `(sale_price - cost) / sale_price` (null se preço 0) |
| `inventory_value` | `stock_quantity * cost` |
| `is_low_stock` | `stock_quantity <= min_stock` |

Isso já permite, no cadastro, ver se o preço cobre o custo **antes** de montar protocolo/venda.

---

## 7. Cadeia completa (como o produto alimenta o resto)

```text
Produto.cost / sale_price
        │
        ├─► Protocolo.total_cost = Σ (cost × qty)
        │   Protocolo.sale_price / min_price
        │
        ├─► Venda: receita (effective_amount) + snapshots de preço
        │
        ├─► Tratamento: CMV = Σ (qty_usada × unit_cost_snapshot)
        │               (cortesia entra no CMV, não na receita)
        │
        └─► Pagamentos: taxas → receita líquida

Margem bruta   ≈ receita bruta − CMV tratamento
Margem líquida ≈ receita líquida − CMV tratamento
```

---

## 8. O que fica de fora do produto (de propósito)

| Tema | Onde vive |
| --- | --- |
| Impostos fiscais complexos (NF-e, CFOP) | Fase futura / integração |
| Comissão de profissional | Venda/tratamento (depois) |
| Taxa de cartão | Módulo de pagamentos |
| Preço negociado da venda | `Sale` / `SaleItem` |
| Custo real aplicado | `TreatmentConsumption` |

---

## 9. Decisão travada para implementação (Fase 2)

1. Produto guarda **custo médio unitário** + **preço de venda** + **estoque** + **UoM** + tipo/marca.
2. Entrada de estoque **informa custo** e recalcula a média.
3. Resource do produto expõe margem unitária e valor em estoque.
4. Histórico financeiro fino vem depois via snapshots em venda/tratamento — o cadastro já nasce pronto para isso.

---

## 10. Checklist de implementação Fase 2

- [ ] Catálogos: tipo, marca, unidade
- [ ] CRUD produto com campos financeiros acima
- [ ] Ajuste/entrada de estoque com recálculo de custo médio
- [ ] Listagem de estoque baixo
- [ ] Permissões + Form Request + Resource
- [ ] Testes (margem, média ponderada, isolamento entre clínicas)
