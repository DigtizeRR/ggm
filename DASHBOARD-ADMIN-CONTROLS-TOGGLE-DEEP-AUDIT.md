# Dashboard Administrator Controls Toggle — Deep Audit

**Status:** Audit only. No dashboard behavior, settings, permissions, assets, or database values were changed.

## Requested behavior

Add a Settings option that controls whether an authenticated WordPress Administrator sees the administration controls on `/dashboard`:

- **On:** the current dashboard administration experience is available.
- **Off:** `/dashboard` renders the same member-facing experience for Administrators as it does for a normal account.

This is a dashboard-specific presentation and endpoint gate. It must not remove an Administrator's WordPress role, `manage_options` capability, or access to the normal WordPress/DZ LMS administration area.

## Current architecture

### Existing administrator check

`modules/dashboard/class-ggm-dashboard-management.php` defines `ggm_dashboard_user_is_administrator()`. It requires all of:

1. A logged-in user.
2. The `manage_options` capability.
3. The literal WordPress `administrator` role.

The same function presently controls both rendering and authorization for dashboard-management AJAX endpoints. This is a sound starting point: it avoids treating a visual CSS/JavaScript hide as security.

### Where controls are rendered

`templates/dashboard/layout.php` obtains the administrator result once and conditionally renders:

- Desktop “Administration” navigation and its Workshops, Courses, Members, Payments, and Diseases items.
- The corresponding administration tab markup, including editor tabs.
- The mobile Manage and Members navigation items.

`templates/dashboard/tab-home.php` conditionally injects the administrator overview into Home. Every individual admin partial adds the same server-side administrator guard.

### Where controls are loaded and authorized

`public/class-ggm-public.php` loads the management, payments, workshop-editor, course-editor, disease-editor, WordPress media, and editor assets only when the existing administrator check passes. `GGM_Dashboard_Management::authorize()` uses that same check with a dedicated nonce for every management AJAX endpoint. No public (`nopriv`) management endpoints are registered.

## Audit finding

The existing design has one authorization predicate shared by dashboard markup, privileged assets, and management AJAX. A toggle must be integrated into that predicate or a new dashboard-controls predicate used consistently by all three paths.

Merely hiding navigation in CSS, or changing only `layout.php`, would be incomplete: administrator overview content, privileged editor assets, and management AJAX endpoints could remain available. Changing `manage_options` or WordPress roles would be over-broad and would unintentionally affect the WordPress admin area.

## Proposed design

### Setting

Add a checkbox under **DZ LMS → Settings → Page Routing**:

- Label: **Show administrator controls on member dashboard**
- Stored key: `ggm_dashboard_admin_controls_enabled`
- Default: enabled (`'1'`) for existing sites, preserving current behavior after the upgrade.
- Help text: controls only the `/dashboard` administrator interface; it does not change WordPress administrator access.

Unchecked form values require explicit handling, because browsers omit unchecked checkboxes. The save handler must always persist either `'1'` or `''` for this key. This avoids a toggle that appears off but silently retains an old enabled setting.

### Single, server-side gate

Retain the existing raw role/capability requirement and add a separate configuration-aware helper, conceptually:

```text
can_use_dashboard_admin_controls =
  setting enabled
  AND logged in
  AND has manage_options
  AND has administrator role
```

Use that helper consistently for:

1. Desktop/mobile administration navigation and every admin tab.
2. The Home administrator overview.
3. Enqueueing management/editor assets and localized management nonce data.
4. All dashboard-management AJAX authorization.

When disabled, `/dashboard` therefore has no administrative navigation, overview, tab markup, editor/media management assets, or usable dashboard-management endpoint—even for Administrators. Direct requests to those dashboard AJAX actions must receive the existing 403-style authorization response. WordPress/DZ LMS back-office pages remain available through their existing capability checks.

### Compatibility and scope

- Existing Administrator behavior remains unchanged by default.
- Normal members remain unable to access dashboard management whether the setting is on or off.
- The main member dashboard, profile, courses, workshops, health, disease, WooCommerce, custom links, login redirects, and non-dashboard WordPress admin actions are out of scope and must remain unchanged.
- The option affects all Administrators globally; it is not a per-user preference. That matches the requested single on/off Settings control.

## Risks and mitigations

| Risk | Mitigation |
| --- | --- |
| UI-only hiding leaves privileged API calls usable | Gate the server-side management authorization with the same setting. |
| Toggle changes WordPress admin privileges | Keep `manage_options` and role checks intact; gate only dashboard controls. |
| Existing sites lose controls after upgrade | Default an absent setting to enabled. |
| Checkbox off is not saved | Explicitly normalize the missing checkbox to off in the settings save handler. |
| Admin assets remain loaded when off | Use the configuration-aware gate in the public asset enqueue path. |
| Cached dashboard HTML shows the wrong view | The dashboard is authenticated and should remain excluded from page caching; verify logged-in Administrator and member responses after the change. |

## Approval-gated implementation plan

1. Add the Page Routing checkbox and explicit server-side saving/normalization.
2. Add a clearly named configuration-aware dashboard-controls authorization helper without weakening existing WordPress permission checks.
3. Apply it to dashboard layout, Home overview, individual admin template guards, privileged assets, and dashboard-management AJAX authorization.
4. Add/update static authorization tests for the enabled and disabled contract.
5. Verify both repository code and the active Local WordPress plugin copy.

## Acceptance tests

| Scenario | Expected result |
| --- | --- |
| Administrator, setting on | Current admin overview, navigation, tabs, assets, and authorized dashboard-management AJAX work. |
| Administrator, setting off | `/dashboard` shows only normal member UI; no administration overview/navigation/tabs/assets; management AJAX is denied. |
| Normal member, either setting | Member UI only; management AJAX remains denied. |
| Administrator, setting off, WordPress `/wp-admin/` | Existing WordPress/DZ LMS administrator access is unchanged. |
| Toggle saved off then page reloads | Off state persists; no stale controls are rendered. |
| Setting absent after update | Controls remain on, preserving existing sites. |

## Conclusion

This can be implemented safely as a global dashboard-only toggle. The critical requirement is to make it a server-side authorization and asset gate, not just a hidden menu. No implementation has been made in this audit phase.
