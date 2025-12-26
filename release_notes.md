# Release Notes

## [2.2.3] - 2025-12-26
### Fixed
- **Mobile:** Increased footer stacking breakpoint to 992px (covering tablets/MD devices).

## [2.2.2] - 2025-12-26
### Fixed
- **Visual:** Fixed massive size of Info icon in Summary card (Step 3).

## [2.2.1] - 2025-12-26
### Hotfix
- **Version Bump:** Forced version update to trigger plugin update mechanism.

## [2.2.0] - 2025-12-26
### Fixed
- **Visual Polish:** Fixed issue where progress line was visible through future step indicators (removed opacity).
- **Date Display:** Fixed styling issue where date header appeared with a grey box due to theme interference (isolated in span).
- **UX:** Added auto-scroll to error messages to ensure visibility.
- **Mobile:** Fixed footer buttons not stacking correctly on mobile (<768px).
- **Logic:** Fixed bug where loading spinner persisted indefinitely when a slot conflict (409) occurred.

## [2.1.9] - 2025-12-26
### Audit Remediation (Fail-Safe Styling)
- **Fixed:** Step 2 Header now uses correct Cormorant font and Teal color via inline styles.
- **Fixed:** Step 3 Section Icons are now 18px (inline sized to bypass CSS cache).
- **Fixed:** Step 4 Success/Waitlist Icon is 64px with correct Green/Orange color.
- **Fixed:** Loading Spinner now scrolls into view for visibility.
- **Fixed:** Step Indicator forward clicks are blocked (only back/current allowed).
- **Fixed:** Reminder dropdown text updated to "48h vorher per Email" for consistency.
- **Fixed:** Calendar month change now clears previous selection and auto-selects within current month only.
- **Added:** CSS fail-safe rules for step indicator visibility.

## [2.1.8] - 2025-12-26
### Hotfix: Mobile Focus & Auto-Advance
- **Fixed:** **Mobile Focus Jump:** Widget no longer auto-focuses/scrolls to center on initial load (Step 1). Focus now correctly targets the top of the container only on subsequent steps.
- **Fixed:** **Auto-Advance:** Step 1 now reliably advances to Step 2 upon selecting a service (bypassed strict validation check that caused intermittent failures).
- **Fixed:** **Stale Cache:** Version bump ensures all v2.1.7 UI changes (Waitlist Redesign) are correctly loaded by browsers.

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
