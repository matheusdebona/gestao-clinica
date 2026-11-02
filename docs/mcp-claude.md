# Conector do Claude (MCP) — só leitura

O HOF Pay tem um servidor MCP em `https://api.hofpay.com.br/mcp` para o administrador da clínica
fazer perguntas ao Claude sobre os dados da clínica (“quanto faturamos este mês?”, “quais orçamentos
estão sem resposta?”). **Não existe nenhuma ferramenta de escrita**: o Claude não cadastra, altera
nem apaga nada.

## Como adicionar no Claude (passo a passo)

> Funciona no claude.ai, no app do computador e no celular (o conector fica na conta do Claude).

1. No HOF Pay, abra **Gestão › Integrações** e clique em **Copiar** no card do Claude
   (endereço `https://api.hofpay.com.br/mcp`).
2. No Claude, abra **Personalizar › Conectores**, clique em **+ Adicionar** e em
   **Adicionar conector personalizado**.
3. Em **Nome**, escreva `HOF Pay`; em **URL**, cole o endereço. Clique em **Continuar**.
4. Em **Autenticação**, deixe o login ligado. Na identificação OAuth, escolha
   **Registrar automaticamente** (*Register automatically*). Clique em **Adicionar**.
5. Clique em **Conectar**. Abre a tela do HOF Pay: entre com **o seu login do HOF Pay**, confira
   a clínica e clique em **Permitir acesso**.
6. Numa conversa, toque em **+ › Conectores**, ligue o **HOF Pay** e pergunte.
7. Plano Team/Enterprise do Claude: quem é Owner adiciona o conector em
   *Configurações da organização › Conectores*; depois cada pessoa clica em **Conectar**.

Para desconectar: **Gestão › Integrações › Revogar** (o Claude perde o acesso na hora) ou remova o
conector no Claude.

## Ferramentas

| Ferramenta | O que responde | Permissão no app |
| --- | --- | --- |
| `resumo_faturamento` | faturamento (vendas confirmadas), ticket, formas de pagamento, funil de orçamentos, evolução | `metrics.view` **e** `finance.view` |
| `vendas_por_produto` | ranking de produtos/tipos no período (mesma tela Métricas › Vendas por produto) | `metrics.view` e `finance.view` |
| `dre_do_mes` | DRE do mês x mês anterior, margens, ponto de equilíbrio | `finance.view` |
| `agenda` | consultas e sessões de um dia/período (até 31 dias) | `appointments.view` |
| `leads` | lista e resumo do funil (status, origem, conversão, parados) | `leads.view` (+ `metrics.view` p/ resumo) |
| `buscar_pacientes` | pacientes por nome/faixa, com total comprado em 12 meses | `clients.view` |
| `paciente_detalhe` | ficha do paciente: faixa, compras, orçamentos, consultas | `clients.view` (+ `sales.view`, `budgets.view`, `appointments.view`) |
| `metas_do_mes` | metas por colaborador e remuneração variável | `users.view`/`metrics.view` (+ `finance.view` p/ remuneração) |
| `estoque_baixo` | produtos no mínimo ou abaixo, cobertura | `metrics.view` |
| `retornos_pendentes` | retornos a chamar / atrasados | `clients.view` e `sales.view` |
| `orcamentos` | orçamentos por situação e os “esquecidos” | `budgets.view` |

Todas: `readOnlyHint=true`, `destructiveHint=false`, respostas em português, R$, datas de Brasília,
paginação/limites.

## Como funciona

* **Transporte**: `laravel/mcp` (streamable HTTP) em `POST /mcp`.
* **Auth**: OAuth 2.1 com Passport. Descoberta (`/.well-known/oauth-protected-resource`,
  `/.well-known/oauth-authorization-server`), registro dinâmico (`POST /oauth/register`, só cliente
  público e só redirects do Claude), PKCE **S256 obrigatório**, escopo único `mcp:read`,
  access token de 1 h, refresh de 30 dias. Login com o usuário do HOF Pay em `/login` e tela de
  consentimento mostrando a clínica e o que será lido.
* **Mesmos números do app**: cada ferramenta chama, por dentro, os mesmos endpoints `GET /api/v1/...`
  do app (sub-requisição com o usuário conectado), então valores, filtros de mês (Brasília) e
  permissões são idênticos. Só caminhos de uma lista fixa (`ReadOnlyApi::ALLOWED`) e só `GET`.
* **Só leitura de verdade**: cada sub-requisição roda numa transação `READ ONLY` do Postgres e
  termina com `ROLLBACK`; se algum GET tentasse gravar, o banco recusa.

## Segurança

* **Dados pessoais**: nenhuma ferramenta devolve telefone, e-mail, CPF, endereço, data de nascimento
  ou anotações clínicas. Toda resposta passa por `App\Mcp\Support\Pii::scrub` (última barreira, que
  mascara qualquer coisa com cara de telefone/e-mail/CPF). Buscas só por nome: números, `@` e
  telefones são recusados (evita descobrir quem é dono de um telefone).
* **Quem conecta**: só quem tem `integrations.manage` (papel admin). A cada chamada o servidor
  confere de novo: conta ativa, clínica ativa, sem senha temporária e permissão. Se algo falhar,
  responde 401 e revoga os tokens dessa conta.
* **Clínica**: tudo preso à clínica da conta (escopo global + endpoints do app). Testes provam que a
  clínica B não vê nada da A.
* **Tokens**: JWT assinado (o banco guarda só o id, nunca o token); clientes públicos sem segredo;
  códigos de uso único; revogação pela tela Integrações.
* **Limites e auditoria**: 60 chamadas/min por token e 120/min por usuário; registro, token e login
  com limite por IP; tabela `mcp_audit_logs` (usuário, ferramenta, parâmetros sem dados pessoais,
  status, duração, IP). Erros nunca mostram stack trace.
* **Navegador**: `/mcp` e `/oauth/*` não liberam CORS (só o servidor do Claude chama), respostas
  OAuth com `Cache-Control: no-store`.

## Deploy

1. `composer install --no-dev` (novos pacotes `laravel/mcp`, `laravel/passport`).
2. `php artisan migrate --force` (tabelas `oauth_*`, `mcp_audit_logs` e permissão
   `integrations.manage` no papel admin).
3. `sudo -u hofpay php artisan passport:keys` e `chmod 600 storage/oauth-*.key`
   (ou `PASSPORT_PRIVATE_KEY`/`PASSPORT_PUBLIC_KEY` no `.env`).
4. `.env`: `MCP_SERVER_URL=https://api.hofpay.com.br/mcp`, `MCP_ALLOW_LOOPBACK_REDIRECTS=false`,
   `SESSION_SECURE_COOKIE=true` (recomendado).
5. Caddy: `api.hofpay.com.br` já manda tudo para o PHP (`php_fastcgi`), então `/mcp`,
   `/.well-known/*`, `/oauth/*` e `/login` funcionam sem mudança.
6. Caches (`config:cache`, `route:cache`), reiniciar fila e PHP-FPM.
