# StoreEngine UI Rules (Tailwind)

Design system for StoreEngine's React surfaces, following GemCRM's architecture
with StoreEngine's brand. **Read this before building or converting a page.**

Migration status: **Phase 0 done** (foundation + pilot). Admin SPA migration is
in progress (Phase 1). Storefront is Phase 3 (scoped, later).

---

## Ground rules

- **Preflight is OFF and stays off.** A global reset would break wp-admin chrome
  and legacy SCSS pages. Tailwind only emits opt-in utility classes.
- **Utilities are scoped to `.se-admin-tw`** (the `important` selector in
  `tailwind.admin.config.js`). Render page content inside `<Scope>` from the kit,
  or add `className="se-admin-tw"` to the page's outer wrapper — otherwise no
  utility applies.
- **Every migrated file must be in the config `content` globs**, or Tailwind's
  JIT won't emit its classes. Add the page's path when you convert it.
- **Never build class names by concatenation** (`text-${c}`). JIT only sees
  literal strings. Use a literal map + `cx()` to pick between whole classes.
- **No opacity modifiers on `se.*` colors** (`bg-se-warning/10`). They're
  `var()`-based and Tailwind can't split them into channels. Use the explicit
  `-light` tokens instead (`bg-se-warning-light`).
- **No web fonts.** `font-se` = `var(--storeengine-primary-font)` = `inherit`
  (the ambient WP-admin / theme font).

---

## Tokens

Source of truth: `assets/scss/common/_root.scss` (`--storeengine-*` CSS vars).
`tailwind.admin.config.js` maps them to semantic names.

| Tailwind | CSS var | Notes |
|---|---|---|
| `se-primary` / `se-primary-light` | `--storeengine-primary-color` / `-primary-light` | brand blue |
| `se-secondary` | `--storeengine-secondary-color` | soft blue |
| `se-text` | `--storeengine-text-color` | body/ink |
| `se-subtitle` | `--storeengine-subtitle-color` | muted text |
| `se-muted` | `--storeengine-gray-color` | |
| `se-border` | `--storeengine-border-color` | |
| `se-bg` | `--storeengine-background-color` | page bg |
| `se-white` | `--storeengine-white-color` | surface |
| `se-success` / `-light` | `--storeengine-green-color` / `-success-light` | |
| `se-warning` / `-light` | `--storeengine-warning-color` / `-warning-light` | |
| `se-error` / `-light` | `--storeengine-error-color` / `-error-light` | |

Radius: `rounded-se` (4px). **Boxes are border-first (GemCRM look): define cards/
panels with `border border-se-border`, not shadow.** `shadow-se-card` is a no-op
(kept so existing markup stays flat); `shadow-se-pop` remains for popovers/menus.

## Type scale (coupled)

Every size carries its line-height. Use the role, not `text-[14px] leading-[20px]`.

`text-2xs` 10/14 · `text-xs` 12/16 · `text-sm` 14/20 · `text-base` 16/24 ·
`text-lg` 18/28 · `text-xl` 20/28 · `text-2xl` 24/32 · `text-3xl` 30/36
(plus `text-11`, `text-13`, `text-15` for odd sizes).

---

## The `ui/` kit

Import from `@Components/ui`. Thin className wrappers — native props pass through.

- **`Scope`** — the `.se-admin-tw` opt-in wrapper. Wrap every kit page in it.
- **`Card`** — white surface: `p-6 rounded-se border border-se-border` (border, no shadow).
- **`Stack` / `Row`** — flex column / row with `gap`, `align`, `justify` props.
- **`Heading` / `Title` / `SubTitle` / `TitleGroup`** — typography roles.
- **`Button`** — `preset` = `primary | soft | ghost | outline | danger | danger-soft | link`; `size` = `xs | sm | md | lg`; `icon`, `iconRight`, `isLoading`.
- **`Field` / `Input` / `Textarea` / `Select`** — labeled controls (40px height).
- **`Table` / `THead` / `TH` / `TR` / `TD`** — simple static tables. For the big
  sortable/filterable/paginated table keep using the existing `ListTable`.

### Minimal page skeleton

```jsx
import { Scope, Card, Heading, Row, Button, Field, Input } from '@Components/ui';

export default function MyPage() {
  return (
    <Scope>
      <Card>
        <Heading>{ __( 'Page Title', 'storeengine' ) }</Heading>
        <Field label="Name" required>
          <Input value={ v } onChange={ ... } />
        </Field>
        <Row justify="end" className="mt-6">
          <Button preset="primary">{ __( 'Save', 'storeengine' ) }</Button>
        </Row>
      </Card>
    </Scope>
  );
}
```

Reference implementation: `containers/BackendDashboard/Pages/Tools/pages/Seeder.js`.

---

## Converting a legacy SCSS page (Phase 1 checklist)

1. Add the page's glob to `tailwind.admin.config.js` `content`.
2. Rebuild markup with the `ui/` kit + Tailwind utilities inside `<Scope>`.
3. Swap the old `@Components/Button` for the kit `Button`.
4. Delete the page's co-located `.scss` once nothing references its classes.
5. `npm run build`, then visually verify the page.
6. Update this doc if you add a kit component or pattern.
