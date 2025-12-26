# Release Notes

## [2.1.8] - 2025-12-26
### Hotfix: Mobile Focus & Auto-Advance
- **Fixed:** **Mobile Focus Jump:** Widget no longer auto-focuses/scrolls to center on initial load (Step 1). Focus now correctly targets the top of the container only on subsequent steps.
- **Fixed:** **Auto-Advance:** Step 1 now reliably advances to Step 2 upon selecting a service (bypassed strict validation check that caused intermittent failures).
- **Fixed:** **Stale Cache:** Version bump ensures all v2.1.7 UI changes (Waitlist Redesign, Header Fixes) are correctly loaded by browsers.

## [2.1.7] - 2025-12-26
### Frontend V3 Parity Patch
- **Fixed:** Step 2 Header "VERFÜGBARE ZEITEN AM [DATE]" was missing; injected via JS with correct styling.
- **Fixed:** Waitlist UI completely redesigned to match prototype (header, friendly text, side-by-side inputs, auto-validation).
- **Fixed:** Mobile Footer buttons now stack correctly (replaced side-by-side layout regressed in v2.1.5).
- **Fixed:** Step 3 Section Icons were missing size constraints; now limited to 18px inline.
- **Fixed:** Step 4 Success/Waitlist Icons were huge (full width) and wrong color; fixed to 64px and correct Green/Orange.
- **Improved:** Widget automatically scrolls to top when transitioning between steps.
- **Improved:** Step 1 Auto-Advance (Refined in v2.1.8).

## [2.1.6] - 2025-12-26
### Bug Fixes
- **Fixed:** Added missing asterisk (*) to required Phone field label.
- **Fixed:** Replaced crude `alert()` validation messages with inline `showError()` notifications.

## [2.1.5] - 2025-12-26
### Frontend Parity V3
- **Added:** Validated "slot taken" error handling with auto-refresh.
- **Added:** 1000ms success overlay (checkmark) on valid slot selection.
- **Added:** AGB notice in footer on Step 3.
- **Added:** HTML support for service descriptions.
- **Changed:** Submission flow transitions directly to Step 4 (Spinner).
- **Fixed:** "Neue Buchung" button now cleanly resets the widget.
