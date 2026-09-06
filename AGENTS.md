# Agent notes — gestao-clinica

## Frontend UI (non-negotiable)

The Vue app in `apps/web` has a frozen design system (Apple-minimal Soft Violet).

- **Always** build screens from `@/components/ui` (and `@/components/patterns` later).
- **Never** hand-roll buttons, inputs, dialogs, toasts, or cards in a feature.
- Tokens live in `apps/web/src/design-tokens/tokens.css`. Kitchen sink: `/dev/ui`.
- Full rule: `.cursor/rules/design-system.mdc` (always on) and `.cursor/rules/vue-ui-components.mdc` (Vue files).
- Spec: `docs/frontend-vue-spec.md`.

If a component does not exist, add it to the design system first — do not fork the visual language.
