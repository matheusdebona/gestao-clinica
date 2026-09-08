# HOF Pay — arquitetura e plano de deploy

Documento **travado** de marca, superfícies, monorepo e publicação no VPS. Complementa a stack da API ([`stack-definition.md`](./stack-definition.md)) e a visão de negócio ([`visao-da-plataforma.md`](./visao-da-plataforma.md)).

Não cria código do site de marketing neste momento: `apps/site` ainda **não** está scaffoldado.

---

## 1. Marca vs nome do repositório

| O quê | Valor | Pode mudar? |
| --- | --- | --- |
| **Marca do produto** (UI, e-mails, `APP_NAME`, título do HTML) | **HOF Pay** | Esta é a marca. |
| Repositório GitHub | `gestao-clinica` | **Não** (por enquanto). |
| Pacote Composer | `gestao-clinica/api` | **Não**. |
| Usuário / banco Postgres | `gestao` / `gestao_clinica` | **Não**. |
| Bucket MinIO | `gestao-clinica` | **Não** (id técnico). |
| Clínica demo / e-mails seed | `Clínica Demo`, `@clinica-demo.test` | **Não** — são dados do tenant demo, não a marca. |

**HOF Pay** é a plataforma de gestão clínica (estoque, protocolos, clientes, vendas, contratos, tratamentos). “Clínica” no domínio continua sendo o **tenant**, não o nome do produto.

---

## 2. Superfícies, domínios e o que cada um publica

Produção: **um VPS**, três hosts, TLS em todos.

| Host | Superfície | Código | O que o nginx entrega |
| --- | --- | --- | --- |
| `hofpay.com.br` | Marketing / vendas | futuro `apps/site` | Site **estático** (HTML/CSS/JS) |
| `app.hofpay.com.br` | App da clínica | `apps/web` | SPA Vue (arquivos estáticos + `try_files` → `index.html`) |
| `api.hofpay.com.br` | API | raiz Laravel | PHP / container Docker (JSON `/api/v1`) |

| Superfície | Autenticada? | Fala com a API? |
| --- | --- | --- |
| Marketing | Não | **Não**. CTA → `https://app.hofpay.com.br/register` |
| App (`apps/web`) | Sim (Sanctum Bearer) | Sim, `https://api.hofpay.com.br/api/v1` |
| API | Emite tokens | — |

O site de marketing **não** chama endpoints autenticados e **não** entra em `CORS_ALLOWED_ORIGINS`.

---

## 3. Layout do monorepo (alvo)

Um repositório GitHub. **Hosts e deploys separados**, não repositórios separados.

```text
gestao-clinica/                 # repo GitHub (nome técnico)
  app/, config/, routes/, …     # Laravel API  →  api.hofpay.com.br
  apps/
    web/                        # Vue SPA      →  app.hofpay.com.br   (já existe)
    site/                       # marketing    →  hofpay.com.br       (ainda NÃO existe)
  docs/
  docker-compose.yml
  ./dev                         # API + web hoje; no futuro também o site
```

### Site de marketing (`apps/site`) — stack recomendada

Quando for scaffoldar (PR seguinte, não este):

| Camada | Escolha |
| --- | --- |
| Framework | **Astro (SSG)** |
| Interatividade | **Ilhas Vue 3** (só onde precisar de JS) |
| CSS | **Tailwind 4**, alinhado ao visual **Soft Violet / Liquid Glass** de `apps/web` |
| Motion | **GSAP** + **ScrollTrigger** |
| Build | Arquivos estáticos (`dist/`) atrás do nginx |

Por quê Astro: HTML estático rápido para vendas/SEO; Vue só nas ilhas (hero, FAQ, CTA); o mesmo mental model Vue do app operacional, sem SSR autenticado.

**Alternativa aceitável:** **Nuxt (SSG)** + GSAP + ScrollTrigger, se no momento do scaffold preferirem um único mental model Vue/Nuxt em vez de Astro. Continua deployável como estático no VPS.

Regras do site:

- Linguagem visual: Soft Violet / Liquid Glass (heavy), claro — sem segundo design system.
- GSAP: registrar `ScrollTrigger` uma vez; nas ilhas Vue, criar tweens em `onMounted` com `gsap.context` no root da ilha e `ctx.revert()` no unmount; respeitar `prefers-reduced-motion` (`gsap.matchMedia`).
- Sem login, sem Bearer, sem chamada à API da clínica.
- CTA principal: `https://app.hofpay.com.br/register`.

---

## 4. Auth e CORS

Modelo atual da API (não muda neste PR): Sanctum **personal access token** (`Authorization: Bearer …`). Cookie SPA stateful **não** é o caminho do app.

```text
app.hofpay.com.br  -- Bearer token -->  api.hofpay.com.br
hofpay.com.br      -- (nada)       -->  (não fala com a API)
```

| Config | Local | Produção (VPS) |
| --- | --- | --- |
| `APP_URL` | `http://localhost:8000` | `https://api.hofpay.com.br` |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:5173`, `http://127.0.0.1:5173` | `https://app.hofpay.com.br` |
| `VITE_API_URL` (SPA) | `/api/v1` (proxy Vite) ou `http://localhost:8000/api/v1` | `https://api.hofpay.com.br/api/v1` |

`SANCTUM_STATEFUL_DOMAINS` só importa se no futuro houver cookie same-site. Com Bearer, o CORS da origem do SPA é o que libera o browser.

Não incluir `https://hofpay.com.br` no CORS: o marketing não consome a API.

---

## 5. Esboço de deploy no VPS

Três `server` blocks nginx, um certificado (ou três) via Let’s Encrypt (`certbot`).

### `hofpay.com.br` — estático (futuro `apps/site/dist`)

```nginx
server {
    listen 443 ssl http2;
    server_name hofpay.com.br www.hofpay.com.br;
    root /var/www/hofpay-site;   # rsync/copy do dist do Astro
    index index.html;

    location / {
        try_files $uri $uri/ =404;
    }
}
```

Redirect `www` → apex (ou o contrário — escolher um canônico).

### `app.hofpay.com.br` — SPA (`apps/web/dist`)

```nginx
server {
    listen 443 ssl http2;
    server_name app.hofpay.com.br;
    root /var/www/hofpay-app;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

O SPA **não** faz proxy `/api` no nginx de produção: o browser chama `api.hofpay.com.br` direto (CORS).

### `api.hofpay.com.br` — Laravel (Docker ou PHP-FPM)

Dois caminhos válidos no mesmo VPS:

1. **Docker** (já existe `Dockerfile` + Compose): nginx faz `proxy_pass` para `127.0.0.1:8000` (ou a porta publicada do container).
2. **PHP-FPM** clássico: `root` em `public/`, `fastcgi_pass` para o socket PHP 8.5.

Além do HTTP: PostgreSQL, Redis, MinIO (ou S3 na nuvem depois). Filas: `queue:work` + `schedule:run`.

TLS: `certbot --nginx` nos três `server_name`. HTTP 80 só para redirect → HTTPS e challenge ACME.

DNS (quando for a fase de DNS):

| Tipo | Nome | Alvo |
| --- | --- | --- |
| A | `hofpay.com.br` | IP do VPS |
| A | `www` | IP do VPS (ou CNAME para apex) |
| A | `app` | IP do VPS |
| A | `api` | IP do VPS |

---

## 6. Portas locais

| Serviço | Porta | Como sobe hoje |
| --- | --- | --- |
| API Laravel | **8000** | `./dev up` (Docker Compose) |
| App Vue | **5173** | `./dev up` (Vite) |
| Site Astro | **4321** | **Ainda não.** Intenção: estender `./dev` quando `apps/site` existir |

`./dev` hoje sobe API + `apps/web`. **Não** incluir o site neste PR. Quando o scaffold existir: `apps/site` no default Astro (`astro dev` → `:4321`) e um `start_site` análogo ao `start_web` no `./dev`.

---

## 7. Rollout em fases

| Fase | O quê | Este PR? |
| --- | --- | --- |
| **1** | Documento de arquitetura + rebrand de nome (**HOF Pay**) na UI/docs/`APP_NAME` | **Sim** |
| **2** | Scaffold `apps/site` (Astro + Vue 3 + Tailwind 4 + GSAP) | Não |
| **3** | DNS + nginx + TLS nos três hosts; `CORS_ALLOWED_ORIGINS` / `APP_URL` / `VITE_API_URL` de produção | Não |
| **4** | Polir animações do marketing (ScrollTrigger, reduced motion, performance) | Não |

Ordem: docs e marca primeiro (para o app já nascer com o nome certo) → site no monorepo → expor hosts no VPS → motion fino no site. Não inverter 2 e 3 se o `dist` do site ainda não existir.

---

## 8. Fora de escopo agora

- Processador de pagamentos (gateway, split, conciliação). Formas de pagamento da clínica já existem no domínio; isso **não** é “HOF Pay” como adquirente.
- Renomear o repositório GitHub `gestao-clinica`.
- Migrations ou rename de banco/usuário/bucket por causa da marca.
- Scaffold de `apps/site`.
- Mudança de rotas ou regras de negócio da API.
- Segundo design system no site (Soft Violet / Liquid Glass permanece).
