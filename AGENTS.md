# Agent notes — gestao-clinica

## Frontend UI (non-negotiable)

The Vue app in `apps/web` has a frozen design system (Apple Liquid Glass, Soft Violet, **heavy**).

- **Always** build screens from `@/components/ui` (and `@/components/patterns` later).
- **Never** hand-roll buttons, inputs, dialogs, toasts, or cards in a feature.
- Tokens live in `apps/web/src/design-tokens/tokens.css`. Kitchen sink: `/dev/ui`.
- Materials: `glass-regular` / `glass-clear` / `glass-dark` / `glass-field` (or `GlassSurface`). Do not invent `backdrop-filter` in a feature.
- Full rule: `.cursor/rules/design-system.mdc` (always on) and `.cursor/rules/vue-ui-components.mdc` (Vue files).
- Spec: `docs/frontend-vue-spec.md` §5.

If a component does not exist, add it to the design system first — do not fork the visual language.
