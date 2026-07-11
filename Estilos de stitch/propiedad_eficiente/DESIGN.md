---
name: Propiedad Eficiente
colors:
  surface: '#f8f9ff'
  surface-dim: '#d0dbed'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e6eeff'
  surface-container-high: '#dee9fc'
  surface-container-highest: '#d9e3f6'
  on-surface: '#121c2a'
  on-surface-variant: '#4d4632'
  inverse-surface: '#27313f'
  inverse-on-surface: '#eaf1ff'
  outline: '#7f7660'
  outline-variant: '#d1c6ab'
  surface-tint: '#735c00'
  primary: '#735c00'
  on-primary: '#ffffff'
  primary-container: '#facc15'
  on-primary-container: '#6c5700'
  inverse-primary: '#eec200'
  secondary: '#006e2d'
  on-secondary: '#ffffff'
  secondary-container: '#7cf994'
  on-secondary-container: '#007230'
  tertiary: '#0053db'
  on-tertiary: '#ffffff'
  tertiary-container: '#c2cfff'
  on-tertiary-container: '#004ecf'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#ffe083'
  primary-fixed-dim: '#eec200'
  on-primary-fixed: '#231b00'
  on-primary-fixed-variant: '#574500'
  secondary-fixed: '#7ffc97'
  secondary-fixed-dim: '#62df7d'
  on-secondary-fixed: '#002109'
  on-secondary-fixed-variant: '#005320'
  tertiary-fixed: '#dbe1ff'
  tertiary-fixed-dim: '#b4c5ff'
  on-tertiary-fixed: '#00174b'
  on-tertiary-fixed-variant: '#003ea8'
  background: '#f8f9ff'
  on-background: '#121c2a'
  surface-variant: '#d9e3f6'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
    letterSpacing: 0.05em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  gutter: 20px
  margin-mobile: 16px
  margin-desktop: 40px
---

## Brand & Style

The design system is engineered for a professional property management environment where clarity, speed of information retrieval, and trust are paramount. The target audience includes property owners and real estate managers who require a high-utility interface to manage complex financial data and tenant relationships.

The style is **Corporate / Modern**, characterized by a systematic approach to density and information hierarchy. It leverages a clean, high-utility aesthetic that balances functional efficiency with a welcoming professional tone. The UI should evoke a sense of reliability and financial transparency, ensuring that users feel in control of their assets at all times.

## Colors

This design system utilizes a high-contrast palette to distinguish between brand identity and functional status.

- **Primary (Yellow):** Used sparingly for high-level brand moments, primary actions, and attention-grabbing highlights. It provides a modern, energetic edge to the professional foundation.
- **Secondary (Green):** Representing financial health and "Paid" statuses. It is the core color for success states and growth-related metrics.
- **Tertiary (Blue):** Employed for informational elements, links, and neutral action states to prevent "signal fatigue" from the primary yellow.
- **Neutral:** A sophisticated range of cool grays. Use `#1F2937` for primary text to maintain high legibility and a grounded feel.
- **Functional Semantics:**
    - **Success:** `#16A34A` (Secondary Green)
    - **Warning/Pending:** `#F59E0B`
    - **Error/Overdue:** `#DC2626`

## Typography

The design system relies exclusively on **Inter** to ensure maximum legibility across data-heavy tables and financial reports. 

- **Hierarchy:** Use `display-lg` for dashboard summaries (e.g., total monthly revenue). `headline-md` is the standard for card titles and section headers.
- **Data Tables:** Use `body-sm` for tabular data to maximize information density without sacrificing readability.
- **Labels:** `label-sm` should be used for table headers and status badge text, utilizing the uppercase style to create clear visual distinction from the data itself.

## Layout & Spacing

This design system uses a **Fluid Grid** model with a base-4 spacing scale.

- **Grid:** A 12-column grid for desktop with 20px gutters. On mobile, transition to a single-column layout with 16px side margins.
- **Information Density:** Use `md` (16px) padding for standard cards and containers. For data tables, use a "compact" vertical rhythm of `sm` (8px) for rows to allow more data to be visible above the fold.
- **Safe Areas:** Maintain a `margin-desktop` of 40px for primary page wrappers to give the professional content room to breathe.

## Elevation & Depth

Visual hierarchy is established through **Tonal Layers** and **Low-Contrast Outlines**. 

- **Surface Levels:** The background uses a slight off-white (`#F9FAFB`). Primary content containers (cards, table wrappers) are pure white (`#FFFFFF`).
- **Outlines:** Use 1px borders in `#E5E7EB` instead of heavy shadows to define boundaries. This creates a "flat-plus" look that feels modern and precise.
- **Elevation:** Reserve soft ambient shadows for interactive elements that float, such as dropdown menus, modals, or active tooltips. These shadows should have a 12% opacity and a 16px blur with no offset.

## Shapes

The design system adopts a **Soft** shape language to maintain a professional yet approachable demeanor.

- **Base Radius:** 4px (`rounded`) for input fields, checkboxes, and small buttons.
- **Container Radius:** 8px (`rounded-lg`) for cards, data table wrappers, and modals.
- **Interaction:** Focus states should use a 2px offset ring in the Primary Yellow to ensure accessibility without distorting the shape of the component.

## Components

### Buttons
- **Primary:** Background `#FACC15`, text `#1F2937` (Bold). High visibility for "Registrar Pago" or "Nuevo Contrato."
- **Secondary:** Outlined `#E5E7EB` with text `#1F2937`. For less critical actions.
- **Success:** Background `#16A34A`, text `#FFFFFF`. Used specifically for "Aprobar" or "Finalizar."

### Status Badges (Pills)
Badges use a "soft-tint" approach (light background with dark text of the same hue):
- **Pagado (Paid):** Green-100 background, Green-800 text.
- **Pendiente (Pending):** Yellow-100 background, Yellow-800 text.
- **Atrasado (Overdue):** Red-100 background, Red-800 text.

### Data Tables
- **Headers:** `label-sm` typography, light gray background (`#F3F4F6`), 1px bottom border.
- **Rows:** Alternating "Zebra" striping is not required; use 1px dividers.
- **Alignment:** Numbers (rent amounts, dates) must be right-aligned or tabular-spaced to allow for easy comparison.

### Financial Charts
- Use **Secondary Green** for income/growth.
- Use **Neutral Gray** or **Tertiary Blue** for historical comparisons.
- Avoid the Primary Yellow in charts unless it represents a "Warning" threshold.

### Input Fields
- Standard 1px border. On focus, the border changes to `#1F2937` with a subtle Primary Yellow glow.
- Labels always sit above the field in `label-md`.