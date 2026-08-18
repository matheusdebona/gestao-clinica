# Visão da plataforma — Gestão Clínica

Documento em português para você validar se o entendimento da plataforma está alinhado.

Os detalhes técnicos (Laravel, Docker, permissões em inglês de código) continuam em:

- [`stack-definition.md`](./stack-definition.md)
- [`domain-model.md`](./domain-model.md)
- [`domain-roadmap.md`](./domain-roadmap.md)
- [`phase-1-todo.md`](./phase-1-todo.md)

---

## 1. O que é a plataforma

A **gestão-clinica** é um sistema para clínicas controlarem:

- o **estoque de produtos** (botox, preenchimento, toxina, ácidos, etc.);
- os **protocolos** (procedimentos montados com combinação de produtos);
- os **clientes** (pacientes);
- as **formas de pagamento** (incluindo taxas de cartão);
- as **vendas** (e depois orçamentos e contratos);
- tudo isso separado por **clínica** (multi-tenant): cada clínica só vê e gerencia os próprios dados.

A ideia central do fluxo comercial é:

```text
Cadastrar produtos e estoque
        ↓
Montar protocolos (agrupando produtos)
        ↓
Cadastrar clientes e formas de pagamento
        ↓
Fazer venda (protocolo e/ou produtos avulsos)
        ↓
Baixar estoque automaticamente
        ↓
(depois) Orçamento → Contrato / documentação
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
  - `sales.confirm` — confirmar venda (e baixar estoque)
  - `clients.update` — editar cliente
- O sistema **sempre verifica a permissão**, nunca só “é recepcionista?”.
- **Cargos (roles)** existem só como atalho: um pacote de permissões (ex.: “Admin da clínica”, “Estoque”, “Vendas”).
- Também dá para dar permissão **direta** a um usuário, sem depender do cargo.

Isso permite cenários do tipo: dois usuários com o mesmo “rótulo”, mas um pode cancelar venda e o outro não.

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
- Quando uma **venda é confirmada**, o estoque **desce** automaticamente.
- Se o estoque atual ≤ estoque mínimo → o produto entra na lista de **alerta de falta**.
- Orçamento **não** baixa estoque (a não ser que depois você peça reserva — ver perguntas no final).

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

A venda é o momento em que junta:

- clínica
- cliente
- data / horário do atendimento
- tempo de atendimento
- itens (protocolos e/ou produtos)
- valor esperado × valor efetivo
- forma(s) de pagamento
- e, ao confirmar, **baixa de estoque**

### Itens da venda

Você pode:

1. Escolher um ou mais **protocolos**
2. Adicionar **produtos individuais**
3. Misturar os dois na mesma venda

### Valores

| Conceito | Significado |
| --- | --- |
| Valor esperado | Calculado automaticamente com base nos preços já cadastrados (protocolos + produtos) |
| Valor efetivo | Quanto realmente foi cobrado (pode diferir do esperado, respeitando ou não o valor mínimo do protocolo) |

### Pagamento na venda

- Uma ou mais formas de pagamento
- Se cartão: operadora, parcelas, etc.
- Data da venda / do atendimento
- Quem vendeu (usuário logado)

### Status (ideia)

- **Rascunho** — ainda editando, estoque intacto
- **Confirmada** — fecha a venda e **diminui estoque**
- **Cancelada** — (depois) devolve estoque

### Baixa de estoque (regra)

Ao confirmar:

- Para linha de **produto**: desce a quantidade vendida
- Para linha de **protocolo**: “explode” os produtos do protocolo e desce cada um × quantidade do protocolo

Os preços/custos do momento da venda ficam **registrados na venda** (snapshot), para o histórico não mudar se você alterar o cadastro depois.

---

## 9. Orçamentos

Orçamento é quase uma venda, mas:

- **não baixa estoque** ao salvar;
- tem status (rascunho, enviado, aceito, recusado, expirado, convertido);
- pode **virar venda** quando o cliente aceitar, reaproveitando itens, cliente e valores.

Serve para negociar antes de consumir produto.

---

## 10. Documentação / contratos

Na hora de gerar contrato (ou termo / recibo), o sistema reaproveita o que já existe:

- dados da **clínica**
- dados do **cliente**
- **protocolos / produtos** e valores da venda ou do orçamento

O arquivo (PDF, etc.) fica guardado no **MinIO** (armazenamento estilo S3), preparado para depois migrar para um S3 na nuvem (AWS, R2, etc.) sem mudar a lógica do sistema.

No início: gerar e armazenar o documento. Assinatura eletrônica e templates avançados vêm depois.

---

## 11. Alertas de estoque

Objetivo: a plataforma também funcionar como **controle de estoque**.

- Cada produto tem **estoque mínimo**
- Lista / filtro de produtos em falta ou perto da falta
- No futuro: aviso por e-mail ou WhatsApp (por enquanto, foco em ter a informação no sistema)

---

## 12. Como as peças se ligam

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
     pagamento              │               │
           │                └───────┬───────┘
           └────────────────────────┤
                                    ▼
                            Documentos /
                             contratos
                                    │
                                    ▼
                          Estoque atualizado
                         (+ alerta de falta)
```

---

## 13. Stack técnica (resumo)

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

Isso é a base. O “negócio” (produtos, vendas, etc.) sobe em fases em cima dessa base.

---

## 14. Ordem de construção (fases)

| Fase | O que entrega |
| --- | --- |
| **1** | API, Docker, login, permissões, esqueleto da clínica |
| **2** | Tipos/marcas/unidades, produtos, estoque, alerta de falta |
| **3** | Protocolos (pacotes de produtos) |
| **4** | Clientes |
| **5** | Formas de pagamento, operadoras e taxas de cartão |
| **6** | Vendas + baixa de estoque |
| **7** | Orçamentos → converter em venda |
| **8** | Documentos / contratos (MinIO) |
| **9** | Notificações, auditoria, painéis, S3 na nuvem |

---

## 15. O que ainda precisa da sua confirmação

Marque mentalmente o que está certo ou diga o que mudar:

1. **Usuário em várias clínicas?** Hoje: 1 usuário = 1 clínica.
2. **Vender abaixo do valor mínimo do protocolo:** bloquear ou só avisar?
3. **Pagamento parcial:** pode ficar saldo em aberto na venda, ou o total pago tem que fechar com o valor efetivo?
4. **Orçamento aceito:** reserva estoque, ou só baixa na venda confirmada?
5. **Moeda:** só Real (BRL)?
6. **Login:** só e-mail/senha, ou também CPF / código de funcionário?
7. **Idioma das mensagens da API:** português (BR) desde o início?
8. **App:** só web no começo, ou também mobile?
9. **Contrato:** PDF gerado pelo próprio sistema no início está ok?
10. **WhatsApp:** só guardar o número por enquanto, sem integração com API da Meta ainda?

---

## 16. Resumo em uma frase

> Cada **clínica** cadastra **produtos** (com custo, preço e estoque), monta **protocolos**, registra **clientes** e **pagamentos**, faz **vendas** (protocolo e/ou avulso) com valor esperado e efetivo, **baixa estoque**, recebe **alerta de falta**, e depois usa os mesmos dados para **orçamento** e **contrato** — com usuários limitados por **permissões** e sem ver dados de outra clínica.

---

Se algo neste documento **não** bate com o que você imaginou (nome de campo, regra de estoque, fluxo de venda, multi-clínica, etc.), diga o ponto que ajustamos antes de começar a implementar a Fase 1.
