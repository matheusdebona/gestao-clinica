# Visão da plataforma — Gestão Clínica

Documento em português para você validar se o entendimento da plataforma está alinhado.

Os detalhes técnicos (Laravel, Docker, permissões em inglês de código) continuam em:

- [`stack-definition.md`](./stack-definition.md)
- [`domain-model.md`](./domain-model.md)
- [`domain-roadmap.md`](./domain-roadmap.md)
- [`phase-1-todo.md`](./phase-1-todo.md)

> **Comece por este arquivo** se quiser validar o negócio em português. Os outros são a especificação técnica.

---

## 1. O que é a plataforma

A **gestão-clinica** é um sistema para clínicas controlarem:

- o **estoque de produtos** (botox, preenchimento, toxina, ácidos, etc.);
- os **protocolos** (procedimentos montados com combinação de produtos);
- os **clientes** (pacientes);
- as **formas de pagamento** (incluindo taxas de cartão);
- as **vendas**, os **contratos** e o **tratamento** (aplicação real no paciente);
- tudo isso separado por **clínica** (multi-tenant): cada clínica só vê e gerencia os próprios dados.

**Uso no celular é prioridade:** a interface será **mobile-first**, pensada para o dia a dia do **médico**, da **secretária** e dos demais papéis da clínica (não um sistema “só de desktop” adaptado depois).

A ideia central do fluxo comercial + clínico é:

```text
Cadastrar produtos e estoque
        ↓
Montar protocolos (agrupando produtos)
        ↓
Cadastrar clientes e formas de pagamento
        ↓
Fazer a venda (protocolo e/ou produtos sugeridos)  ← NÃO baixa estoque
        ↓
Gerar o contrato
        ↓
Iniciar o tratamento do paciente
        ↓
Ao final do tratamento: informar o que foi REALMENTE usado
  (sugestão inicial + complementos do médico, mesmo sem cobrança)
        ↓
Aí sim: baixar estoque e contabilizar o custo real
```

---

## 2. Clínica (multi-tenant)

A **clínica** é a “dona” de quase tudo no sistema.

### O que isso significa na prática

- Produtos, protocolos, clientes, vendas, pagamentos, orçamentos e documentos **pertencem a uma clínica**.
- Usuários (recepção, vendedor, estoquista, admin da clínica) **pertencem a uma clínica**.
- Um usuário de Clínica A **não enxerga** dados da Clínica B.
- Pode existir um **super-admin da plataforma** só para criar/gerenciar clínicas — sem misturar estoque/vendas entre elas.

### Dados básicos da clínica

- Nome
- Documento (ex.: CNPJ) — opcional no início
- Telefone, e-mail, endereço
- Configurações (ex.: estoque mínimo padrão, idioma, moeda)
- Ativa / inativa

> **Ponto em aberto:** por enquanto assumimos **1 usuário → 1 clínica**. Se você precisar que a mesma pessoa atenda várias clínicas, ajustamos isso.

---

## 3. Usuários, login e permissões

### Login

- API em **Laravel 13** + **PHP 8.5**
- Autenticação com **Laravel Sanctum** (token Bearer)
- Fluxo básico: **entrar**, **sair**, **me** (dados do usuário logado + permissões)

### Permissões (não só “cargo”)

Você pediu controle por **permissão de acesso a pontos específicos**, não apenas por cargo genérico. É assim que vamos fazer:

- Cada ação importante tem uma permissão, por exemplo:
  - `products.view` — ver produtos
  - `products.create` — cadastrar produto
  - `sales.confirm` — confirmar venda (comercial; **não** baixa estoque)
  - `treatments.complete` — finalizar tratamento e baixar estoque
  - `clients.update` — editar cliente
- O sistema **sempre verifica a permissão**, nunca só “é recepcionista?”.
- **Cargos (roles)** existem só como atalho: um pacote de permissões (ex.: “Admin da clínica”, “Estoque”, “Vendas”).
- Também dá para dar permissão **direta** a um usuário, sem depender do cargo.

Isso permite cenários do tipo: dois usuários com o mesmo “rótulo”, mas um pode cancelar venda e o outro não.

---

## 3.1. Mobile-first (uso no celular)

A plataforma **nasce mobile-first**. O uso diário na clínica acontece no bolso / no balcão com telefone ou tablet — não só no computador.

### Para quem

| Perfil | Exemplos de uso no celular |
| --- | --- |
| Secretária | Buscar cliente, fechar venda, gerar contrato, ver agenda de tratamentos |
| Médico / profissional | Abrir tratamento do paciente, marcar produtos usados, incluir complemento, finalizar e baixar estoque |
| Estoque / admin | Ver alerta de falta, ajustar cadastros rápidos |

### Princípios de UX

1. **Desenhar primeiro para tela pequena** (depois expandir para tablet/desktop).
2. Fluxos críticos com **poucos toques**: venda, tratamento (consumo real), busca de cliente, alerta de estoque.
3. Botões e listas **fáceis de tocar**; evitar tabelas densas como tela principal no mobile.
4. Formulários curtos, com campos essenciais em destaque.
5. Funcionar bem com **conexão variável** da clínica (API enxuta; depois cache/offline se precisar).
6. Desktop continua suportado, mas **não é o desenho-mestre**.

### O que isso implica na construção

- A **API** (Laravel) já serve bem a PWA — Sanctum com token Bearer combina com mobile.
- O **frontend inicial será uma PWA mobile-first** (instalável / “Adicionar à tela inicial”), para médico e secretária usarem no celular no dia a dia.
- Desktop continua suportado na mesma PWA, como tela maior — não como desenho principal.
- App nativo (iOS/Android) fica como evolução **só se** a PWA não atender; não bloqueia as fases da API.

---

## 4. Produtos

Cadastro do que a clínica compra, estoca e vende (sozinho ou dentro de protocolo).

### Informações do produto

| Informação | Exemplo / finalidade |
| --- | --- |
| Nome | Nome comercial do item |
| Tipo de produto | Botox, preenchimento, toxina botulínica, ácido, etc. (cadastro, não lista engessada) |
| Marca | Marca do produto |
| Para que serve | Descrição / finalidade |
| Unidade de medida | Quilos, miligramas, mililitros, unidades, etc. |
| Custo | Quanto custa para a clínica |
| Valor de venda | Preço padrão de venda |
| Estoque atual | Quantidade disponível |
| Estoque mínimo | Abaixo disso = alerta de falta |
| Ativo / inativo | Desliga sem apagar histórico |

### Tipos, marcas e unidades

São **cadastros separados** (por clínica), para você ir incluindo o que usar no dia a dia, sem depender de lista fixa no código.

### Estoque

- Você **abastece** o estoque (entrada / ajuste).
- A **venda** e o **contrato** **não** baixam estoque — o produto ainda não foi aplicado.
- A baixa acontece só ao **finalizar o tratamento**, com base no que foi **realmente usado** (incluindo complementos sem cobrança).
- Se o estoque atual ≤ estoque mínimo → o produto entra na lista de **alerta de falta**.
- Orçamento também **não** baixa estoque.

---

## 5. Protocolos

Protocolo = **agrupamento de produtos** que forma um procedimento.

Exemplo mental:

> Protocolo “Preenchimento labial”  
> - Produto A: 1 unidade  
> - Produto B: 0,5 ml  
> - Valor de venda do protocolo: R$ X  
> - Valor mínimo: R$ Y  
> - Custo total: soma (custo de cada produto × quantidade)

### O que o protocolo guarda

| Informação | Finalidade |
| --- | --- |
| Nome e descrição | Identificar o procedimento |
| Lista de produtos + quantidades | O que será consumido |
| Valor de venda | Preço padrão do protocolo |
| Valor mínimo | Piso comercial (bloquear ou só avisar — a decidir) |
| Custo total | Calculado a partir dos custos dos produtos |
| Ativo / inativo | Desliga sem apagar |

### Por que isso importa

Na venda, em vez de montar produto por produto toda vez, você escolhe o **protocolo**. O sistema já sabe composição, custo e preço esperado. Ainda assim, na mesma venda você pode **somar produtos avulsos**.

---

## 6. Clientes (pacientes)

Cadastro de quem compra / é atendido.

### Dados previstos

| Campo | Uso |
| --- | --- |
| Nome | Identificação |
| WhatsApp | Contato principal |
| Observações | Anotações livres |
| Principais dores | Queixas / dores do paciente |
| Tempo de atendimento | Duração típica ou do atendimento |
| Clínica | Sempre vinculado à clínica |

Depois dá para evoluir com CPF, e-mail, data de nascimento, endereço, etc.

O cliente entra na **venda**, no **orçamento** e nos **documentos/contratos**.

---

## 7. Formas de pagamento (módulo separado)

Cadastro próprio, porque cartão envolve operador, bandeira e taxa.

### Métodos

Exemplos:

- Dinheiro
- PIX
- Cheque
- Cartão de crédito
- Cartão de débito

### Quando for cartão

Cadastros à parte:

- **Operadoras** (Cielo, Stone, Rede, etc.)
- **Bandeiras** (Visa, Mastercard, Elo… — opcional)
- **Taxas / regras** (operadora + bandeira + número de parcelas → percentual ou taxa fixa)

Assim, na venda você registra **como** pagou e, no futuro, consegue calcular o valor líquido após taxas.

---

## 8. Vendas

A venda é o momento **comercial**: o que foi combinado/cobrado com o cliente.

Ela junta:

- clínica
- cliente
- data da venda
- itens sugeridos (protocolos e/ou produtos)
- valor esperado × valor efetivo cobrado
- forma(s) de pagamento

**Importante:** confirmar a venda **não baixa estoque**. O produto só sai do estoque quando o tratamento é finalizado e o consumo real é informado.

### Itens da venda

Você pode:

1. Escolher um ou mais **protocolos**
2. Adicionar **produtos individuais**
3. Misturar os dois na mesma venda

Esses itens viram a **sugestão inicial** do que deve ser usado no tratamento (checklist de partida).

### Valores

| Conceito | Significado |
| --- | --- |
| Valor esperado | Calculado automaticamente com base nos preços já cadastrados (protocolos + produtos) |
| Valor efetivo | Quanto realmente foi cobrado na venda |

### Pagamento na venda

- Uma ou mais formas de pagamento
- Se cartão: operadora, parcelas, etc.
- Data da venda
- Quem vendeu (usuário logado)

### Status da venda (ideia)

- **Rascunho** — ainda editando
- **Confirmada** — venda fechada comercialmente (estoque **intacto**)
- **Cancelada** — venda anulada (sem efeito de estoque, pois nunca baixou)

Os preços do momento da venda ficam **registrados na venda** (snapshot), para o histórico comercial não mudar se o cadastro mudar depois.

---

## 9. Orçamentos

Orçamento é quase uma venda, mas ainda em fase de proposta:

- **não** baixa estoque;
- tem status (rascunho, enviado, aceito, recusado, expirado, convertido);
- pode **virar venda** quando o cliente aceitar, reaproveitando itens, cliente e valores.

---

## 10. Documentação / contratos

Depois da venda (ou a partir do orçamento convertido), gera-se o **contrato** reaproveitando:

- dados da **clínica**
- dados do **cliente**
- **protocolos / produtos** e valores da venda

O arquivo (PDF, etc.) fica no **MinIO** (estilo S3), pronto para migrar depois para S3 na nuvem.

Fluxo esperado:

```text
Venda confirmada → Gerar contrato → (depois) iniciar tratamento
```

Assinatura eletrônica e templates avançados vêm depois; no início: gerar e armazenar.

---

## 11. Tratamento (aplicação no paciente) — onde o estoque desce

O **tratamento** é o passo clínico: ir até o paciente, aplicar o procedimento e, no fim, registrar o consumo real.

### Por que existe separado da venda

Na venda o produto ainda **não foi aplicado**. O médico pode:

- usar exatamente o que foi sugerido na venda/protocolo;
- usar **menos** ou **mais** do que o previsto;
- **adicionar produtos complementares sem cobrar** o paciente;
- em alguns casos, cobrar um valor extra por algo acrescentado (regra a detalhar).

O estoque e o **custo real** precisam refletir o que **saiu do frasco**, não só o que foi vendido no papel.

### Fluxo do tratamento

```text
1. Abrir tratamento a partir da venda (+ contrato já gerado)
2. Iniciar atendimento do paciente
3. Sistema sugere os produtos previstos na venda
   (protocolos “explodidos” em produtos + produtos avulsos)
4. No fim do tratamento, informar o que foi usado de fato:
   - marcar / ajustar quantidades dos itens sugeridos
   - incluir produtos extras (complemento)
   - dizer se o extra foi cobrado ou cortesia (sem cobrança)
   - se cobrado: qual valor
5. Finalizar tratamento
   → baixa o estoque pelo consumo real
   → registra o custo total do que foi usado (mesmo o que foi cortesia)
```

### Dados do tratamento

| Informação | Finalidade |
| --- | --- |
| Clínica, cliente, venda | Vínculo |
| Profissional responsável | Quem aplicou |
| Início / fim | Horários do atendimento |
| Tempo de atendimento | Duração real |
| Status | `agendado`, `em_andamento`, `finalizado`, `cancelado` |
| Observações clínicas | Anotações do atendimento |

### Itens de consumo (o que realmente saiu)

Cada linha de consumo no tratamento:

| Campo | Significado |
| --- | --- |
| Produto | O que foi usado |
| Quantidade | Quanto foi usado (na unidade do produto) |
| Origem | `sugerido` (veio da venda) ou `complemento` (adicionado no atendimento) |
| Cobrado? | Sim / não (cortesia do médico) |
| Valor cobrado | Se cobrado; senão zero |
| Custo unitário (snapshot) | Custo do produto no momento do uso |
| Custo total da linha | `quantidade × custo` — entra na conta da clínica mesmo se for cortesia |

### Regras de ouro

1. **Venda / contrato = comercial** (o que foi combinado e cobrado na venda).
2. **Tratamento finalizado = operacional + estoque + custo real**.
3. Produto de **cortesia** (sem cobrança ao paciente) **ainda baixa estoque** e **ainda conta como custo** da clínica.
4. Só se finaliza o tratamento (e baixa estoque) uma vez; cancelar tratamento finalizado exigiria estorno de estoque (fase posterior).

### Permissões (exemplos)

`treatments.view`, `treatments.start`, `treatments.update`, `treatments.complete`, `treatments.cancel`

---

## 12. Alertas de estoque

Objetivo: a plataforma também funcionar como **controle de estoque**.

- Cada produto tem **estoque mínimo**
- Lista / filtro de produtos em falta ou perto da falta
- A baixa que alimenta esses alertas vem do **fim do tratamento**, não da venda
- No futuro: aviso por e-mail ou WhatsApp

---

## 13. Como as peças se ligam

```text
                    ┌─────────────┐
                    │   Clínica   │
                    └──────┬──────┘
           ┌───────────────┼───────────────┐
           ▼               ▼               ▼
       Usuários        Produtos        Clientes
                           │
                           ▼
                      Protocolos
                     (produtos+qtd)
                           │
           ┌───────────────┼───────────────┐
           ▼               ▼               ▼
     Formas de         Orçamento         Venda
     pagamento              │          (comercial)
           │                └──────┬──────┘
           └───────────────────────┤
                                   ▼
                           Contrato / docs
                                   │
                                   ▼
                             Tratamento
                    (início → uso real → fim)
                                   │
                                   ▼
                    Baixa de estoque + custo real
                      (+ alerta de falta)
```
---

## 14. Stack técnica (resumo)

| Camada | Escolha |
| --- | --- |
| API | Laravel 13 |
| Linguagem | PHP 8.5 |
| Login | Laravel Sanctum |
| Permissões | Spatie Permission (por permissão) |
| Banco | PostgreSQL 18 |
| Cache / filas | Redis |
| Arquivos | MinIO (compatível com S3) |
| Ambiente local | Docker Compose |
| Frontend | PWA mobile-first |

### Padrão da API (travado)

| Entrada | Saída | Erros |
| --- | --- | --- |
| **Form Request** valida o que chega | **API Resource** formata o JSON | `401` / `403` / `422` (bag `errors`) / `404` |

Assim o frontend (PWA) sempre recebe a mesma estrutura de sucesso e a mesma estrutura de erro de validação — sem model Eloquent “cru” e sem validação solta no controller.

Detalhe técnico: [`stack-definition.md`](./stack-definition.md) §7.

Isso é a base. O “negócio” (produtos, vendas, tratamentos, etc.) sobe em fases em cima dessa base.

---

## 15. Ordem de construção (fases)

| Fase | O que entrega |
| --- | --- |
| **1** | API, Docker, login, permissões, esqueleto da clínica |
| **2** | Tipos/marcas/unidades, produtos, estoque, alerta de falta |
| **3** | Protocolos (pacotes de produtos) |
| **4** | Clientes |
| **5** | Formas de pagamento, operadoras e taxas de cartão |
| **6** | Vendas (comercial; **sem** baixa de estoque) |
| **7** | Orçamentos → converter em venda |
| **8** | Documentos / contratos (MinIO) |
| **9** | Tratamentos: início, consumo real, fim → **baixa de estoque + custo** |
| **10** | Notificações, auditoria, painéis (margem real), S3 na nuvem |
| **11** | Frontend **PWA mobile-first** (médico, secretária; nativo depois só se precisar) |

---

## 16. O que ainda precisa da sua confirmação

Marque mentalmente o que está certo ou diga o que mudar:

1. **Usuário em várias clínicas?** Hoje: 1 usuário = 1 clínica.
2. **Vender abaixo do valor mínimo do protocolo:** bloquear ou só avisar?
3. **Pagamento parcial:** pode ficar saldo em aberto na venda, ou o total pago tem que fechar com o valor efetivo?
4. **Complemento no tratamento com cobrança:** gera uma cobrança/ajuste ligado à venda original, ou só anota o valor no tratamento?
5. **Uma venda → um tratamento**, ou pode haver várias sessões de tratamento para a mesma venda?
6. **Moeda:** só Real (BRL)?
7. **Login:** só e-mail/senha, ou também CPF / código de funcionário?
8. **Idioma das mensagens da API:** português (BR) desde o início?
9. **Stack da PWA:** React, Vue, Next ou outra?
10. **Contrato:** PDF gerado pelo próprio sistema no início está ok?
11. **WhatsApp:** só guardar o número por enquanto?

---

## 17. Resumo em uma frase

> Cada **clínica** cadastra **produtos** e **protocolos**, registra **clientes** e **pagamentos**, faz a **venda** e o **contrato** (sem mexer no estoque), depois **inicia o tratamento** do paciente e, ao **finalizar**, informa o que foi realmente usado — inclusive complementos sem cobrança — para **baixar o estoque** e **contabilizar o custo real**, com usuários limitados por **permissões**, dados isolados por clínica, e interface **PWA mobile-first** para o dia a dia do médico e da secretária.

---

Se algo neste documento **não** bate com o que você imaginou (especialmente a separação venda → contrato → tratamento → estoque), diga o ponto que ajustamos antes de começar a implementar a Fase 1.