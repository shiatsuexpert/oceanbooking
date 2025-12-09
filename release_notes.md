# Release Notes

# Release Notes

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
