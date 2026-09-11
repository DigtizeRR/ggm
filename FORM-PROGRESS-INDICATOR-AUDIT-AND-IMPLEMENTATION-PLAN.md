# Form Progress Indicator Audit and Implementation Plan

## Requested behavior

Show a progress indicator only when a public form has two or more visible sections/pages. Single-page forms must not render a page number or a 100% progress bar.

## Audit findings

The public renderer always outputs `.ggm-form-progress`, regardless of the number of pages. Its JavaScript initializes the indicator immediately; when there is one page, the bar calculation is `(1 / 1) * 100`, which produces the visible 100% bar in the supplied HTML.

The page count must be determined after the renderer has split post-submit fields and applied the payment-first screen. Those steps can change the number of sections that the visitor sees.

## Implementation plan

1. Calculate whether the final visible section list contains more than one page.
2. Render the progress container only in that multi-page case.
3. Make the progress runtime safe when no container exists.
4. Preserve the configured `none`, `pages`, and `bar` modes for multi-page forms.
5. Verify standard forms, payment-first forms before/after payment, and mobile rendering.

## Implementation status

Implemented with the requested scope.
