# apps/web — Gestão Clínica (Vue)

SPA Vue 3 + TypeScript + Tailwind 4 no visual **Modern Soft Violet**.

Neste PR: scaffold + kitchen sink `/dev/ui` para validar o design system. Sem login/API ainda.

## Subir

```bash
cd apps/web
cp .env.example .env
npm install
npm run dev
```

Abre `http://localhost:5173` (redireciona para `/dev/ui`).

## Scripts

| Comando | Uso |
| --- | --- |
| `npm run dev` | Vite em `:5173` |
| `npm run build` | Typecheck + build de produção |
| `npm run preview` | Preview do build |

`VITE_API_URL` fica preparado para a API Laravel (`http://localhost:8000/api/v1`) e ainda não é usado.

## Pasta

Ver [`docs/frontend-vue-spec.md`](../../docs/frontend-vue-spec.md) §4 e §5.
