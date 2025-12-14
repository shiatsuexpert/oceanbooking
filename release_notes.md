# Release Notes

### Added
- **Frontend Redesign (V2 Beta):** Brand-aligned booking wizard matching `oceanshiatsu.at`.
    - **Dual Version System:** Toggle between "Classic (V1)" and "Modern Redesign (V2)" in Admin Settings.
    - **Mobile-First Process:** Vertical stacked card layout for better mobile UX.
    - **Split View Desktop:** Modern 2-column layout for desktop.
    - **Provider Identity:** Display of Provider Name & Image (configured in Settings) during the booking flow.
- **Provider Settings:** Added fields to Calendar Picker to associate specific Google Calendars with a provider persona.
- **API:** New `/config` endpoint to serve frontend configuration settings dynamically.

## [1.7.2] - 2025-12-14
### Fixed
- **Desktop API Error (v2):** Added proper `res.ok` check in `fetchTimeSlots` to handle non-2xx responses correctly. Error messages are now truncated to prevent UI breakage from HTML error pages.

## [1.7.1] - 2025-12-13
### Fixed
- **Mobile Accordion Visibility:** Fixed CSS issue where service cards were hidden inside the mobile accordion wrapper.
- **Desktop API Error:** Fixed "Fehler beim Laden" in Step 3 by adding Nonce verification to `fetchTimeSlots`.
- **Performance:** Relocated Google Fonts enqueue to `enqueue_styles` for better rendering performance.

## [1.7.0] - 2025-12-12
### Added
- **Step 4 Confirmation UI:** New success screen with context-aware messages (Booking Confirmed vs Request Received).
- **Auto-Confirmation Logic:** Admin setting to auto-confirm bookings if GCal slot is free.
- **Reschedule Flow:** "Propose new time" functionality via email link.
- **Design Tokens:** Implemented centralized CSS variables for Teal/Sand brand colors.

## [1.4.6] - 2025-12-10
### Fixed
- **Critical Date Bug:** Fixed month calculation that would skip February when running on Jan 31 (4 locations).
- **Cron Pre-Warming:** 15-min sync now properly pre-warms cache for next 2 months.
- **Last Sync Timezone:** Display now uses WordPress local timezone instead of UTC.

### Added
- **Cache Inspector Day Status:** Shows availability status (Available/Booked/Closed/Holiday) with color coding.
- **Cache Inspector Label:** Corrected to "Raw Events per Day" for clarity.

## [1.4.5] - 2025-12-10
### Fixed
- **Timezone Display:** System Status now shows times in WordPress timezone (was showing UTC).
- **Cache Inspector Label:** Corrected "Time Slots" → "Raw Events per Day" to avoid confusion.

### Added
- **Cache Inspector Day Status:** New column showing each day's availability status (Available/Booked/Closed/Holiday) with color coding.

## [1.4.4] - 2025-12-09
### Fixed
- **Sync Date Corruption:** Fixed `1970-01-01` dates when syncing from Google (array vs string parsing).
- **Cron Self-Healing:** 15-min sync job now auto-registers if missing after plugin updates.

### Added
- **System Status Dashboard:** Settings page now shows cron health, next sync time, last sync timestamp.
- **Settings-Triggered Sync:** Saving settings or calendars immediately triggers availability recalculation.
- **Enhanced Webhook Logging:** Webhooks now log full data (resource_uri, message_number, timestamp).
- **Delayed Loading Indicator:** Spinner only shows if month fetch takes >300ms.

### Documentation
- Complete rewrite of `timeslots calculation and sync.md` with accurate cache-first architecture.

## [1.4.3] - 2025-12-09
### Fixed
- **Critical Fix:** Resolved a PHP Fatal Error when saving calendar settings (`undefined variable $gcal`).

## [1.4.2] - 2025-12-09
### Changed
- **Max Bookings Logic:** The "Max Bookings Per Day" limit now STRICTLY counts events that are either:
    1. Local WordPress Appointments.
    2. Google Calendar events in the configured **Write Calendar** (Working Calendar).
    *Events in other read-only calendars (like Personal or Holidays) still block time slots but DO NOT increment the booking count.*

## [1.4.1] - 2025-12-09
### Added
- **Max Bookings Refinement:** The "Max Bookings Per Day" setting now strictly counts ALL busy events (Google Calendar + Local) towards the daily limit.
- **Documentation:** Updated `timeslots calculation and sync.md` with new enforcement rules.

### Fixed
- Fixed bug where 'Enable Debug Mode' checkbox was not saving in Admin Settings.

## [1.4.0] - 2025-12-08
### Added
- **Debug Mode:** Detailed visibility into availability logic, enabled via Settings.
- **Cache Inspector:** Admin tool to view and clear active `osb_gcal_*` cache keys and check TTL.
- **Frontend Trace:** Developer console output (`console.table`) proving "Zero Latency" decision logic (Blockers, Free Windows).
- **Performance Metrics:** API responses now include `Server-Timing` headers for `db`, `gcal`, and `logic` duration.
- **Webhook Logging:** Detailed verification logs for Google Calendar webhooks.

### Improved
- **Security:** Added PII masking (email/phone/names) to system logs.
- **Sync Performance:** Reduced log noise for routine sync jobs.
- **API Robustness:** Generalized "Month Miss" logic for robust fallback.

## [1.3.18] - 2025-12-08
### Fixed
- Fixed critical TTL inconsistency (60s -> 3600s) in `class-google-calendar.php` which caused excessive cache misses.

### Changed
- "Month Miss" fallback logic now pre-warms the cache safely.
- API Response time improved via optimized Sync.

## [1.3.17] - 2025-12-08 (Skipped)
- Version bump to resolve tag conflict.

## [1.3.16] - 2025-12-08
- "Zero Latency" Architecture Release.
