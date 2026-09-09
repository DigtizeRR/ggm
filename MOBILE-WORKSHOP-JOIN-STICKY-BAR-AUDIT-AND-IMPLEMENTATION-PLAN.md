# Mobile Workshop Join Sticky Bar — Audit and Implementation Plan

## Request

On mobile pages containing:

```text
[ggm_workshop_join_form_full button_text="Submit Now"]
```

show a floating bottom purchase bar containing the workshop price and a **Join Now** button. The bar must:

- appear only on phone-sized mobile viewports;
- sit 30px above the bottom of the visible viewport;
- follow the cream, dark-green, and warm brown visual direction in the supplied reference;
- take the visitor to the matching full join form on the same page;
- leave the existing form, login/OTP, payment, enrollment, and dashboard redirect logic unchanged.

This document is an audit and proposed plan only. No sticky-bar implementation is included yet.

## Audit scope

Reviewed:

- shortcode registration and `sc_workshop_join_form_full()` in `public/class-ggm-shortcodes.php`;
- the full form markup, per-instance ID, inline JavaScript, and responsive styles;
- fixed, sale, free, and contribution price resolution in `modules/workshop/class-ggm-workshop.php`;
- selected-currency formatting in `includes/class-ggm-currency.php`;
- existing-user access states and the shortcode's dashboard handoff;
- current smooth-scroll patterns and mobile safe-area handling in the plugin.

## Current behavior and useful foundations

### 1. The shortcode is the correct rendering boundary

`sc_workshop_join_form_full()` first resolves and validates the workshop. It returns no form for an invalid workshop, an unavailable notice when contribution choices are missing, an access panel for a paid/enrolled user, or the complete join form for a visitor who can enroll.

Generating the sticky bar from this method means it will only be emitted when the shortcode is genuinely present and tied to a valid workshop. A generic page-level content scan or hardcoded Elementor selector would be less reliable.

### 2. Every rendered form already has a unique ID

The form receives a `wp_unique_id()` value. That ID can be used as the sticky CTA's exact target, avoiding fragile heading text, section order, or page-builder selectors.

### 3. Price data already has one authoritative source

`GGM_Workshop` provides:

- regular price;
- sale price;
- resolved charge price;
- free status;
- contribution options and the default contribution;
- currency-aware formatted output.

The sticky bar must reuse these methods. It must not read a price from visible page text or duplicate pricing calculations in JavaScript.

### 4. The form has dynamic contribution pricing

Contribution workshops can change their selected option in the form. The sticky price should initially show the configured default and then mirror the selected form option. For a free option it should display **Free** rather than a zero-value currency string.

### 5. Existing access requires a different CTA

If a logged-in user already has paid access, the shortcode currently renders **Go to Dashboard** instead of the enrollment form. Showing **Join Now** in this state would be misleading. The sticky component should therefore use **Go to Dashboard** and the existing resolved dashboard URL, or be omitted. The recommended behavior is to retain the useful bar and change its action to **Go to Dashboard**.

### 6. Current enrollment logic should not be duplicated

The full form already owns validation, existing-account lookup, OTP login, Razorpay payment, free enrollment, contribution selection, and post-payment dashboard redirection. The sticky CTA should only navigate to the form. It must not submit, enroll, or initiate payment itself.

## Proposed behavior

### Visibility

- Render only when the full join-form shortcode resolves a valid workshop.
- Display only at phone-sized viewport widths up to 600px.
- Remain hidden on desktop, laptop, tablet, and all viewports wider than 600px, even when the shortcode is present.
- Do not render an actionable sticky bar for the contribution-unavailable state.
- Hide the bar while printing.
- When the target form is substantially visible, fade/hide the sticky bar to prevent a duplicate CTA covering the form; restore it after the form leaves the viewport.
- When the on-screen keyboard is open and the visitor is entering form details, keep the bar hidden so it does not cover fields.

### CTA action

- Use a real `<button type="button">` for the scroll action.
- On activation, smoothly scroll the matching form into view with a small top offset.
- Respect `prefers-reduced-motion: reduce` by scrolling immediately instead of animating.
- Move programmatic focus to the first incomplete form input after scrolling, without forcing the mobile keyboard open during the scroll.
- If the user already has access, use a normal **Go to Dashboard** link instead.

### Price presentation

Fixed-price workshop:

- show the resolved sale price prominently;
- when a valid lower sale price exists, optionally show the regular price struck through underneath, matching the reference hierarchy;
- show **Free** for a zero-price workshop.

Contribution workshop:

- initially show the configured default contribution;
- update the sticky price when the user selects another contribution option in the form;
- show **Free** for a selected free option;
- never imply that the displayed client-side value is the charge authority—the existing server-side payment validation remains authoritative.

Currency:

- use the same selected currency and formatting utilities as the form;
- allow the existing currency-selection page reload/update behavior to recreate the correctly formatted bar.

### Visual specification

Recommended reference-derived palette:

- bar background: warm cream `#F7F0DF`;
- primary text/price: deep green `#274C3A`;
- CTA background: warm terracotta brown `#BB673B`;
- CTA text: `#FFFFFF`;
- subtle border: `rgba(39, 76, 58, 0.16)`;
- shadow: soft, low-opacity dark green/black to separate it from content.

Layout:

- `position: fixed` with a high but controlled z-index;
- `bottom: calc(30px + env(safe-area-inset-bottom, 0px))`;
- 16px left/right viewport clearance;
- centered maximum width so it does not stretch excessively on tablets;
- price block on the left and rounded **Join Now** button on the right;
- minimum 48px tap height;
- rounded outer container and pill-style CTA inspired by the reference;
- no desktop output visibility.

The 30px requirement remains in addition to the iPhone safe-area inset, so the bar is never pushed into the home indicator.

### Preventing content obstruction

While the bar is active, add calculated bottom breathing room to the page so the final content cannot remain hidden behind it. The spacing should equal:

```text
sticky bar measured height + 30px bottom gap + safe-area inset + small content gap
```

Use a scoped body class/CSS custom property and remove it when the component is hidden or the viewport changes to desktop.

## Proposed implementation

### Phase 1 — Server-rendered companion markup

In `public/class-ggm-shortcodes.php`:

1. Add a small private renderer for the mobile sticky component.
2. Call it only from `sc_workshop_join_form_full()` after workshop validation.
3. Associate it with the exact form/access-panel ID using `aria-controls` and a data target.
4. Build price HTML with `GGM_Workshop` and `GGM_Currency` methods.
5. Emit escaped labels and URLs using WordPress escaping functions.
6. Handle fixed, sale, free, contribution, enrolled, and unavailable states explicitly.

### Phase 2 — Mobile styles

Extend the shortcode's existing one-time style renderer:

1. Keep the component hidden by default.
2. Enable it only inside `@media (max-width: 600px)`; its base/default style remains `display: none` so desktop and tablet layouts cannot expose it accidentally.
3. Apply the approved reference colors and 30px plus safe-area bottom offset.
4. Add focus-visible styling, adequate contrast, 48px minimum tap targets, and reduced-motion rules.
5. Add transition classes for visible, target-visible, and keyboard/input-active states.
6. Ensure Elementor/theme button rules cannot override the component by using fully scoped selectors.

### Phase 3 — Scoped interaction logic

Extend the shortcode instance script:

1. Bind the sticky CTA only to its matching form instance.
2. Implement safe smooth scrolling with a reduced-motion fallback.
3. Use `IntersectionObserver` to hide the sticky component when its target form is visible; provide a scroll/resize fallback for older browsers.
4. Listen for contribution radio changes and update the displayed price from the server-rendered `data-amount` value.
5. Use `visualViewport` and form focus state to suppress the bar while the mobile keyboard is occupying the viewport.
6. Measure the actual bar height and expose it through a CSS custom property for bottom content spacing.
7. Recalculate on orientation change and viewport resize.

### Phase 4 — Multiple-shortcode protection

Although the expected page has one form, implementation should remain deterministic if a page contains multiple instances:

- each bar targets its own unique form ID;
- only one sticky bar may be visible at a time;
- prefer the first eligible shortcode in document order unless the visitor is currently nearest another form;
- do not create duplicate global event handlers.

## Files expected to change

- `public/class-ggm-shortcodes.php`
  - sticky markup;
  - authoritative price state;
  - mobile styling;
  - scoped scroll/visibility/update behavior.

No database migration, AJAX endpoint, payment handler, enrollment handler, or dashboard change should be required.

## Acceptance criteria

1. A page without `[ggm_workshop_join_form_full]` never shows the sticky bar.
2. A valid shortcode page shows it only at 600px and below. It remains absent at 601px and above, including tablets, laptops, and desktops.
3. The bar stays fixed 30px above the usable bottom edge, plus any device safe area.
4. The palette visually follows the supplied cream/green/terracotta reference.
5. **Join Now** scrolls to the exact matching full form on the same page.
6. The bar hides while the target form is visible and returns when appropriate.
7. Fixed, sale, free, and contribution prices display correctly in the selected currency.
8. Changing a contribution option updates the sticky price.
9. An already-enrolled user receives **Go to Dashboard**, not a misleading enrollment action.
10. The bar does not cover the page's final content or focused form inputs.
11. iPhone safe areas, Android Chrome, Safari, keyboard opening, rotation, and reduced-motion settings behave correctly.
12. Existing form validation, OTP login, payment, enrollment, and redirect flows remain unchanged.

## Verification plan

### Functional matrix

- guest + fixed paid workshop;
- guest + fixed free workshop;
- guest + regular/sale price workshop;
- guest + contribution workshop, paid default;
- guest + contribution workshop, free default;
- logged-in user not enrolled;
- existing account requiring OTP;
- logged-in user already enrolled;
- invalid workshop and contribution-with-no-options states;
- page containing no shortcode;
- page containing two shortcode instances.

### Device/layout checks

- 320px, 360px, 390px, 430px, and 600px mobile widths;
- 601px boundary plus common 768px, 1024px, laptop, and desktop widths, where the bar must remain hidden;
- iOS Safari safe area and keyboard;
- Android Chrome keyboard;
- portrait/landscape rotation;
- WordPress admin bar present and absent;
- Elementor/theme containers and existing floating widgets.

### Regression checks

- PHP lint;
- JavaScript syntax validation;
- shortcode output escaping review;
- payment amount remains server-validated;
- OTP and post-login payment-resume behavior;
- successful enrollment/dashboard redirect;
- no new page-level horizontal scroll or layout shift.

## Approval checkpoint

Implementation should begin only after approval of this plan. The default recommendation is:

- phone-only mobile breakpoint: 600px (hidden at 601px and wider);
- bottom position: 30px plus safe-area inset;
- CTA label: **Join Now** for eligible users;
- enrolled-user CTA: **Go to Dashboard**;
- hide the sticky bar when the matching form is visible or a form field/keyboard is active;
- use the reference-derived cream `#F7F0DF`, deep green `#274C3A`, and terracotta `#BB673B` palette.
