# apps/web — HOF Pay (Vue)

SPA Vue 3 + TypeScript + Tailwind 4 no visual **Soft Violet / Apple Liquid Glass (heavy)**, com tema claro e escuro.

## Subir

Na raiz do repo, API + frontend juntos:

```bash
./dev up
```

Ou só o Vite, com a API já no ar (`:8000`):

```bash
cd apps/web
cp .env.example .env
npm install
npm run dev
```

- Login: `http://localhost:5173/login`
- Cadastro: `http://localhost:5173/register`
- App: `http://localhost:5173/`
- Kitchen sink: `http://localhost:5173/dev/ui`

No `.env`, `VITE_API_URL=/api/v1` usa o proxy do Vite para `http://127.0.0.1:8000`. Para chamar a API direto, use `http://localhost:8000/api/v1`.

Token fica em `sessionStorage` (`gc_token`).

## Cadastro

`POST /api/v1/auth/register` cria a clínica e o primeiro usuário `admin`. Senha: mínimo 10 caracteres, com maiúscula, número e símbolo.

Foto do painel de auth: `src/assets/auth-clinic.jpg` (recepção da clínica). Troque o arquivo para usar outra imagem.

## Scripts

| Comando | Uso |
| --- | --- |
| `npm run dev` | Vite em `:5173` |
| `npm run build` | Typecheck + build |
| `npm run preview` | Preview do build |
