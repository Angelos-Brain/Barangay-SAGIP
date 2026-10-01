# Design System Master File

> **LOGIC:** When building a specific page, first check `design-system/pages/[page-name].md`.
> If that file exists, its rules **override** this Master file.
> If not, strictly follow the rules below.

---

**Project:** BarangaySafe
**Generated:** 2026-09-28 00:51:04
**Category:** Government/Public Service

---

## Global Rules

### Color Palette

| Role | Hex | CSS Variable |
|------|-----|--------------|
| Primary | `#0F172A` | `--color-primary` |
| On Primary | `#FFFFFF` | `--color-on-primary` |
| Secondary | `#334155` | `--color-secondary` |
| On Secondary | `#FFFFFF` | `--color-on-secondary` |
| Accent/CTA | `#0369A1` | `--color-accent` |
| On Accent/CTA | `#FFFFFF` | `--color-on-accent` |
| Background | `#F8FAFC` | `--color-background` |
| Foreground | `#020617` | `--color-foreground` |
| Card | `#FFFFFF` | `--color-card` |
| Card Foreground | `#020617` | `--color-card-foreground` |
| Muted | `#E8ECF1` | `--color-muted` |
| Muted Foreground | `#475569` | `--color-muted-foreground` |
| Border | `#E2E8F0` | `--color-border` |
| Destructive | `#DC2626` | `--color-destructive` |
| On Destructive | `#FFFFFF` | `--color-on-destructive` |
| Ring | `#0F172A` | `--color-ring` |

**Color Notes:** High contrast navy + blue

### Typography

- **Heading Font:** Atkinson Hyperlegible
- **Body Font:** Atkinson Hyperlegible
- **Mood:** accessible, readable, inclusive, WCAG, dyslexia-friendly, clear
- **Google Fonts:** [Atkinson Hyperlegible + Atkinson Hyperlegible](https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&display=swap)

**CSS Import:**
```css
@import url('https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&display=swap');
```

### Spacing Variables

| Token | Value | Usage |
|-------|-------|-------|
| `--space-xs` | `4px` / `0.25rem` | Tight gaps |
| `--space-sm` | `8px` / `0.5rem` | Icon gaps, inline spacing |
| `--space-md` | `16px` / `1rem` | Standard padding |
| `--space-lg` | `24px` / `1.5rem` | Section padding |
| `--space-xl` | `32px` / `2rem` | Large gaps |
| `--space-2xl` | `48px` / `3rem` | Section margins |
| `--space-3xl` | `64px` / `4rem` | Hero padding |

### Shadow Depths

| Level | Value | Usage |
|-------|-------|-------|
| `--shadow-sm` | `0 1px 2px rgba(0,0,0,0.05)` | Subtle lift |
| `--shadow-md` | `0 4px 6px rgba(0,0,0,0.1)` | Cards, buttons |
| `--shadow-lg` | `0 10px 15px rgba(0,0,0,0.1)` | Modals, dropdowns |
| `--shadow-xl` | `0 20px 25px rgba(0,0,0,0.15)` | Hero images, featured cards |

---

## Component Specs

### Buttons

```css
/* Primary Button */
.btn-primary {
  background: #0369A1;
  color: white;
  padding: 12px 24px;
  border-radius: 8px;
  font-weight: 600;
  transition: all 200ms ease;
  cursor: pointer;
}

.btn-primary:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

/* Secondary Button */
.btn-secondary {
  background: transparent;
  color: #0F172A;
  border: 2px solid #0F172A;
  padding: 12px 24px;
  border-radius: 8px;
  font-weight: 600;
  transition: all 200ms ease;
  cursor: pointer;
}
```

### Cards

```css
.card {
  background: #F8FAFC;
  border-radius: 12px;
  padding: 24px;
  box-shadow: var(--shadow-md);
  transition: all 200ms ease;
  cursor: pointer;
}

.card:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-2px);
}
```

### Inputs

```css
.input {
  padding: 12px 16px;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  font-size: 16px;
  transition: border-color 200ms ease;
}

.input:focus {
  border-color: #0F172A;
  outline: none;
  box-shadow: 0 0 0 3px #0F172A20;
}
```

### Modals

```css
.modal-overlay {
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
}

.modal {
  background: white;
  border-radius: 16px;
  padding: 32px;
  box-shadow: var(--shadow-xl);
  max-width: 500px;
  width: 90%;
}
```

---

## Style Guidelines

**Style:** Accessible & Ethical

**Keywords:** Accessible, inclusive interface, high contrast, large text (16px+), keyboard navigation, screen reader friendly, accessibility standards aware, focus state, semantic

**Best For:** Government, healthcare, education, inclusive products, large audience, legal compliance, public

**Key Effects:** Clear focus rings (3-4px), ARIA labels, skip links, responsive design, reduced motion, 44x44px touch targets

### Page Pattern

**Pattern Name:** Minimal Single Column

- **Conversion Strategy:** Single CTA focus. Large typography. Lots of whitespace. No nav clutter. Mobile-first.
- **CTA Placement:** Center, large CTA button
- **Section Order:** Hero headline > Short description > Benefit bullets (3 max) > CTA > Footer

---

## Anti-Patterns (Do NOT Use)

- ❌ Ornate design
- ❌ Low contrast
- ❌ Motion effects
- ❌ AI purple/pink gradients

### Additional Forbidden Patterns

- ❌ **Emojis as icons** — Use SVG icons (Heroicons, Lucide, Simple Icons)
- ❌ **Missing cursor:pointer** — All clickable elements must have cursor:pointer
- ❌ **Layout-shifting hovers** — Avoid scale transforms that shift layout
- ❌ **Low contrast text** — Maintain 4.5:1 minimum contrast ratio
- ❌ **Instant state changes** — Always use transitions (150-300ms)
- ❌ **Invisible focus states** — Focus states must be visible for a11y

---

## Pre-Delivery Checklist

Before delivering any UI code, verify:

- [ ] No emojis used as icons (use SVG instead)
- [ ] All icons from consistent icon set (Heroicons/Lucide)
- [ ] `cursor-pointer` on all clickable elements
- [ ] Hover states with smooth transitions (150-300ms)
- [ ] Light mode: text contrast 4.5:1 minimum
- [ ] Focus states visible for keyboard navigation
- [ ] `prefers-reduced-motion` respected
- [ ] Responsive: 375px, 768px, 1024px, 1440px
- [ ] No content hidden behind fixed navbars
- [ ] No horizontal scroll on mobile

---

## Dashboard Adaptation (overrides the generator defaults above)

BarangaySafe is an operations dashboard, not a marketing site. Where this section
conflicts with **Page Pattern**, **Cards** or **Shadow Depths** above, this section wins.
Palette, typography, spacing tokens, accessibility rules and anti-patterns above still apply.

**Implementation:** tokens live in exactly one place —
`barangay-sagip-web/resources/views/components/design-tokens.blade.php` (Tailwind CDN config + CSS
variables). Every layout includes `<x-design-tokens />`; no screen defines its own colors.

### Semantic status colors (added — needed for map pins, meters, badges)

Derived to keep ≥4.5:1 contrast on white. Never convey status by color alone — pair with a label or icon.

| Role | Hex | Tailwind name | Usage |
|------|-----|---------------|-------|
| Danger | `#DC2626` (= Destructive) | `danger` | Critical urgency, SOS, full centers |
| Alert | `#C2410C` | `alert` | High urgency, centers ≥ 80% |
| Warning | `#B45309` | `warning` | Average urgency, needs review |
| Success | `#15803D` | `success` | Low urgency, open centers, available |
| Info | `#0369A1` (= Accent) | `accent` | Personnel, standby, primary actions |
| Neutral | `#64748B` | `slate-500` | Closed, unknown |

### App shell

- **Sidebar:** fixed, 256px (`w-64`), `bg-navy` (#0F172A). Items = 20px Lucide icon + label, 44px min height,
  `rounded-xl`. Idle: `text-slate-300`, hover `bg-white/5 text-white`. **Active: `bg-accent text-white` + `aria-current="page"`.**
  Grouped with small uppercase section labels (`text-[11px] tracking-wider text-slate-500`). Below `lg` it becomes an off-canvas drawer.
- **Top bar:** sticky, white, 64px, bottom border `line`. Left: page title. Right: round 40px icon buttons
  (`rounded-full bg-canvas`, hover `bg-muted`) for notifications (+ unread dot) and a quick action, then the circular avatar with name + role.
- **Canvas:** `bg-canvas` (#F8FAFC), content padding 16px mobile / 32px desktop, 24px gap between cards.

### Cards (replaces the generator's clickable card)

- `bg-white rounded-2xl` (16px), `shadow-card` = `0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.12)`,
  1px `line` border, internal padding 24px (20px on mobile).
- Card header: title `text-base font-bold text-navy`, optional one-line subtitle `text-sm text-muted-fg`, actions right-aligned.
- Static cards do **not** lift on hover and are not `cursor-pointer`; only interactive rows/links get hover states.

### Stat cards (bottom/top metric row)

Icon tile (40px, `rounded-xl`, tinted 10% of its status color) + small label (`text-sm text-muted-fg`) + 1–2 metrics
(`text-2xl font-bold text-navy`, secondary metric `text-xs`). Grid: 2 cols mobile → 3 tablet → 5 desktop.

### Toggle switch

44px-tall hit area row: label (+ optional colored legend dot) on the left, 44×24 switch on the right.
Off: `bg-muted`; on: `bg-accent`; knob white 20px, 150–200ms transition; visible 3px focus ring. Backed by a real
`<input type="checkbox">` so it is keyboard and screen-reader accessible.

### Level / progress meter

Row: label left, live value right (`tabular-nums`, e.g. `42 / 120 · 35%`). Track 8px `bg-muted rounded-full`, fill colored by
threshold (success < 50%, warning ≥ 50%, alert ≥ 80%, danger ≥ 100%). Uses `role="meter"` with `aria-valuenow/min/max`.

### Map card

Card containing: header (title + live-refresh indicator) → filter/legend row of toggle chips → map (≥ 28rem tall, `rounded-xl`) .
Markers are **circular pins**: filled circle, 2px white stroke, soft shadow, color from the status table. Evacuation centers
carry a small house glyph inside the pin; SOS requests get a thicker ring. Beside the map on desktop (stacked on mobile) sits
the **Control panel** card: layer toggles + center-occupancy meters. Below: a row of stat cards computed from the live feed.

### Buttons & inputs

Per spec above, with radius `rounded-xl` (12px) to match the rounded shell. Primary = `bg-accent`, destructive = `bg-danger`,
secondary = white with `line` border. All ≥ 44px tall on touch screens.
