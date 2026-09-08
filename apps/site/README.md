# apps/site — HOF Pay (marketing)

Site estático de vendas em **Astro (SSG)** + ilhas **Vue 3** + **Tailwind 4** + **GSAP / ScrollTrigger**. Visual **Soft Violet / Liquid Glass**, alinhado a [`apps/web`](../web).

Produção (depois, fora deste scaffold): `https://hofpay.com.br`. Sem login, sem Sanctum, sem chamada à API.

## Subir

Na raiz do repo:

```bash
./dev site
```

Ou só este pacote:

```bash
cd apps/site
npm install
npm run dev
```

Dev server: `http://localhost:4321`

CTA do site aponta para o app de produção (`https://app.hofpay.com.br/register` e `/login`), não para o Vite local.

## Scripts

| Comando | Uso |
| --- | --- |
| `npm run dev` | Astro em `:4321` |
| `npm run build` | SSG → `dist/` |
| `npm run preview` | Preview do `dist/` |

## Tokens

`src/design-tokens/tokens.css` é uma cópia de `apps/web/src/design-tokens/tokens.css` (cores, radius, glass, Inter). Não inventar uma segunda paleta; se o app mudar tokens, sincronizar este arquivo.

## Motion

GSAP registra `ScrollTrigger` uma vez (`src/lib/gsap.ts`). Ilhas Vue usam `gsap.matchMedia` com `prefers-reduced-motion` e fazem `revert()` no unmount.
