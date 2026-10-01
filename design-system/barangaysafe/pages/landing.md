# Landing Page Overrides

> Overrides `../MASTER.md` for the public landing page (`resources/views/landing.blade.php`) only.
> Everything not listed here follows MASTER.md (palette, type, spacing, cards, buttons, a11y).

## Motion (replaces MASTER "❌ Motion effects" for this page)

The landing page is the first impression, so purposeful, calm motion is allowed here — never in the app shell.

| Pattern | Spec |
|---------|------|
| Hero entrance | Badge → headline → text → CTAs → hotline line, fade + 16px rise, 600ms `cubic-bezier(.2,.7,.2,1)`, 120ms stagger. Starts when the splash begins to leave. |
| Hero background | Radar pulse: 3 accent rings expanding from one point, 4.8s loop, ≤ 30% opacity, plus one slow drifting accent glow (20s). Decorative, `aria-hidden`. |
| Scroll reveal | `.reveal` — fade + 16px rise, 600ms, stagger `--i × 80ms`, once per element (IntersectionObserver, no library). |
| Register steps | Connector line draws left→right (700ms); step numbers switch from muted to accent in sequence as the list enters view. |
| Card hover | Step and developer cards (and feature cards) lift 4px with a stronger shadow, 200ms. Exception to MASTER "static cards don't lift" — landing only. |
| Primary CTA | Hover: lift 2px + accent-tinted shadow; press: settles back and scales to 0.98. 200ms. |
| Scroll cue | Chevron under the hero, 6px drift, 1.6s loop. |
| Header | Gains `shadow-card` + border once the hero is scrolled past. |

**Never:** bounce/elastic easing, confetti, parallax on text, motion that delays reading content.

## Reduced motion

`prefers-reduced-motion: reduce` must show every element in its final state immediately:
no entrance, no reveal offsets, no looping radar/glow/chevron, no hover lift. The page's JS also
marks every `.reveal` visible up front. Content must be fully readable without JavaScript (reveal
hiding only applies when `html.js` is set).
