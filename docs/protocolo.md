# Protocolo — serviço completo a partir de produtos

O **protocolo** é um **conjunto de produtos** que forma um **serviço completo** (procedimento). Não baixa estoque sozinho: na venda ele sugere o pacote; no tratamento o consumo real é informado.

---

## 1. Ideia

```text
Produtos (custo + preço de venda + piso)
        │
        ▼
Protocolo = serviço completo
        │
        ├─ custo do serviço     ← soma dos custos dos produtos
        ├─ valor sugerido       ← baseado nos preços de venda dos produtos
        ├─ valor mínimo         ← piso comercial do pacote
        └─ valor condição especial ← outro sugerido para facilitar a venda do médico
```

---

## 2. Preços do protocolo (travado)

| Campo | Origem | Uso |
| --- | --- | --- |
| `total_cost` | **Calculado** `Σ (product.cost × qty)` | Custo do serviço; base de margem |
| `products_sale_total` | **Calculado** `Σ (product.sale_price × qty)` | Referência: “se vendesse os itens a preço de tabela” |
| `suggested_price` | Inicialmente = `products_sale_total`; **editável** | Valor sugerido principal na venda |
| `min_price` | Inicialmente = `Σ (coalesce(product.min_sale_price, product.cost) × qty)`; **editável** | Piso: não vender abaixo (aviso/bloqueio depois) |
| `special_price` | **Manual**, opcional | Segundo sugerido (promoção / condição especial / facilitar fechamento) |

### Margens derivadas (Resource)

| Indicador | Fórmula |
| --- | --- |
| `margin_at_suggested` | `suggested_price − total_cost` |
| `margin_at_special` | `special_price − total_cost` (se `special_price` preenchido) |
| `margin_at_min` | `min_price − total_cost` |

---

## 3. Itens do protocolo

| Campo | Uso |
| --- | --- |
| `product_id` | Produto componente |
| `quantity` | Quanto do produto entra no serviço (na UoM do produto) |

Ao alterar itens (ou custos/preços dos produtos), o sistema **recalcula**:

1. `total_cost`
2. `products_sale_total`

e, se o protocolo ainda estiver em modo automático para aquele campo, atualiza `suggested_price` / `min_price`.  
Flags:

- `suggested_price_is_manual` — se `true`, não sobrescreve o sugerido no recálculo
- `min_price_is_manual` — idem para o mínimo

`special_price` nunca é recalculado automaticamente.

---

## 4. Na venda (depois)

O médico/secretária escolhe o protocolo e pode partir de:

1. **Valor sugerido** (`suggested_price`)
2. **Condição especial** (`special_price`), se existir
3. Outro valor efetivo, respeitando o **mínimo** (`min_price`)

O estoque só desce no **fim do tratamento**, com o que foi realmente usado.

---

## 5. Resumo em uma frase

> Protocolo = pacote de produtos que monta um serviço, com **custo calculado**, **sugerido** (pelos preços de venda), **mínimo**, e opcionalmente um **valor de condição especial** para facilitar a venda.
