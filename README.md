# HOF Pay

Plataforma de gestão clínica + comercial, multi-tenant — API-first, **mobile-first**. O repositório GitHub permanece [`gestao-clinica`](https://github.com/matheusdebona/gestao-clinica).

Arquitetura, domínios de produção e plano de deploy: [`docs/hofpay-architecture.md`](./docs/hofpay-architecture.md).

## Current status

**Phase 1 in progress / foundation landed:** Laravel 13 API scaffold with Sanctum auth, permission-first RBAC, clinic tenancy skeleton, and Docker Compose stack.

| Document | Purpose |
| --- | --- |
| [docs/hofpay-architecture.md](./docs/hofpay-architecture.md) | Marca HOF Pay, hosts, monorepo e deploy (VPS) |
| [docs/visao-da-plataforma.md](./docs/visao-da-plataforma.md) | Visão completa em português |
| [docs/produto-financeiro.md](./docs/produto-financeiro.md) | Dados do produto para custo, receita e margem |
| [docs/protocolo.md](./docs/protocolo.md) | Protocolo = serviço completo + preços (custo/sugerido/mínimo/especial) |
| [docs/stack-definition.md](./docs/stack-definition.md) | Stack técnica |
| [docs/phase-1-todo.md](./docs/phase-1-todo.md) | Checklist Fase 1 |
| [docs/domain-model.md](./docs/domain-model.md) | Domínio comercial |
| [docs/domain-roadmap.md](./docs/domain-roadmap.md) | Fases 2–11 |
| [docs/frontend-vue-spec.md](./docs/frontend-vue-spec.md) | Spec do frontend Vue + Soft Violet |

## Stack

- Laravel 13 · PHP 8.5
- Sanctum (Bearer) · Spatie Permission
- PostgreSQL 18 · Redis · MinIO (S3)
- Clinic multi-tenant · Vue SPA em [`apps/web`](./apps/web) (PWA later)
- Marketing em [`apps/site`](./apps/site) (Astro; `./dev site` → `:4321`)

## Quick start

```bash
./dev up
```

API: `http://localhost:8000`  
Frontend: `http://localhost:5173` (ou a próxima porta livre)  
MinIO console: `http://localhost:9001` (minio / minioSecret)

```bash
./dev down          # para API e frontend
./dev fresh         # zera o banco local (só migrations)
./dev fresh -y      # a mesma coisa, sem confirmação
./dev seed          # roda os seeders
```

Demo admin (from seed):

- Email: `admin@clinica-demo.test`
- Password: `ChangeMe!123`

### Useful Make targets

```bash
make up
make test
make artisan CMD="route:list"
```

## Frontend Vue (design system)

Kitchen sink Soft Violet em `/dev/ui` — validar cores, inputs e feedback antes das features.

O `./dev up` já sobe o Vite. Para rodar só o frontend:

```bash
cd apps/web
cp .env.example .env
npm install
npm run dev
```

Detalhes: [`apps/web/README.md`](./apps/web/README.md).

## Auth API (Phase 1)

| Method | Path | Notes |
| --- | --- | --- |
| `POST` | `/api/v1/auth/login` | `{ "email", "password" }` → token |
| `POST` | `/api/v1/auth/register` | Clínica + primeiro admin → token |
| `GET` | `/api/v1/auth/me` | Bearer token |
| `POST` | `/api/v1/auth/logout` | Revoke current token |
| `POST` | `/api/v1/auth/logout-all` | Revoke all tokens |

Permission-gated examples: `/api/v1/users`, `/api/v1/clinics/current`, `/api/v1/permissions`.

## Local tests (without Docker)

```bash
cp .env.example .env
php artisan key:generate
# use sqlite/array drivers for quick tests, or point at local Postgres/Redis
php artisan test
```

## PDF de orçamento (Browsershot)

A API gera PDF com Spatie Browsershot (Chromium + Node + Puppeteer). Testes usam `FakePdfRenderer` (`APP_ENV=testing`). Em produção o renderer real precisa dos binários no host da API.

Diagnóstico:

```bash
php artisan pdf:diagnose
```

### Docker (já no `Dockerfile`)

A imagem instala `chromium`, `nodejs`, `npm`, fontes e `puppeteer` global. O container define `BROWSERSHOT_CHROME_PATH=/usr/bin/chromium`. Rebuild da imagem da API depois do merge se o VPS usa Compose.

### VPS com PHP-FPM (Debian/Ubuntu)

Rodar como root no servidor da API (ajuste o nome do pacote se a distro usar `chromium-browser`):

```bash
sudo apt-get update
sudo apt-get install -y chromium fonts-liberation fonts-dejavu-core nodejs npm
sudo npm install -g puppeteer
```

Se `chromium` não existir no apt:

```bash
sudo apt-get install -y chromium-browser
# ou Google Chrome:
# curl -fsSL https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb -o /tmp/chrome.deb
# sudo apt-get install -y /tmp/chrome.deb
```

No `.env` da API (caminhos reais: `which chromium`, `which node`, `npm root -g`):

```env
BROWSERSHOT_CHROME_PATH=/usr/bin/chromium
BROWSERSHOT_NODE_BINARY=/usr/bin/node
BROWSERSHOT_NPM_BINARY=/usr/bin/npm
BROWSERSHOT_NODE_MODULE_PATH=/usr/lib/node_modules
```

O PHP-FPM **não** herda o PATH do seu shell. No pool (`/etc/php/8.5/fpm/pool.d/www.conf` ou equivalente):

```ini
env[PATH] = /usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/bin
env[BROWSERSHOT_CHROME_PATH] = /usr/bin/chromium
env[PUPPETEER_EXECUTABLE_PATH] = /usr/bin/chromium
```

O usuário do PHP-FPM (`www-data`) precisa executar o Chromium (`--no-sandbox` já está no renderer). Depois:

```bash
sudo systemctl restart php8.5-fpm
php artisan config:clear
php artisan pdf:diagnose
```

Se `POST /api/v1/budgets/{id}/pdf` ainda falhar, a API agora responde **503** com mensagem em português (Chromium/Node ausente ou erro do Puppeteer), em vez de um 500 genérico.

## Build order

Follow `docs/domain-roadmap.md` in sequence. Do not start Phase 2 until Phase 1 DoD in `docs/phase-1-todo.md` is complete.
