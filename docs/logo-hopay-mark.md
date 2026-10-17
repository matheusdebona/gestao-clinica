# HOF Pay mark (SVG)

Source raster: [`logo-hopay-clean.png`](./logo-hopay-clean.png).

| Asset | Use |
| --- | --- |
| [`logo-hopay-mark.svg`](./logo-hopay-mark.svg) | Full landscape mark (`currentColor` + gold `#BB9562`) |
| `apps/web/src/assets/brand/hofpay-mark.svg` | Same mark for the Vue app |
| `apps/web/src/assets/brand/hofpay-mark-icon.svg` | Square crop (icon / PWA) |
| `apps/*/public/favicon.svg` | App icon tile (Soft Violet + white mark) |
| `BrandMark` (`apps/web` UI + `apps/site` Astro) | Inline logo; `Spinner` / button loading spin this mark |

Prefer the Vue/Astro `BrandMark` when the mark must inherit text color. Use the static SVG/`favicon` when you need a file URL (meta, OG, `<link rel="icon">`).
