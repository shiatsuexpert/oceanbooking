# Ocean Shiatsu Booking - Plugin Documentation
**Version:** 1.3.18
**Author:** Ocean Shiatsu
**Date:** 2025-12-08

## 1. Introduction

Ocean Shiatsu Booking is a premium WordPress appointment booking plugin designed specifically for Shiatsu practitioners and similar wellness professionals. It provides a seamless frontend booking experience for clients, a robust backend management interface, and two-way synchronization with Google Calendar.

### Key Features Summary
- **Zero Latency Frontend**: Instant Step 2 load times using pre-warmed server-side caching (v1.3.18).
- **Validation on Next**: Strictly prevents double-bookings via live Google Calendar checks (`POST /validate-slot`).
- **Destructive Sync**: Improved background sync process that maintains a "Hot" and "Self-Healing" cache.
- **Smart Availability Clustering**: Algorithm to manage slot presentation and "scarcity" illusion.
- **Debug Mode (v1.4.0):**
    *   **Traceability:** View exact "Decision Logic" (why a slot was rejected/accepted) in the Browser Console.
    *   **Cache Inspector:** View active cache keys and their remaining TTL in WP Admin.
    *   **Metrics:** See exactly how long Google API vs Database vs Logic took via `Server-Timing` headers.
- **Google Calendar Sync**: Two-way sync handling busy slots, event creation, and updates.
- **Email Workflow**: Automated notifications for admins and clients (Request, Confirmation, Cancellation, Rescheduling, Rejection).

---

## 2. Installation & Setup

1.  **Plugin Activation**:
    - Upload the plugin folder to `wp-content/plugins/`.
    - Activate via the WordPress Plugins menu.
    - Upon activation, the plugin creates necessary database tables (`wp_osb_appointments`, `wp_osb_services`, `wp_osb_settings`, `wp_osb_logs`).

2.  **Shortcode Placement**:
    - Add `[ocean_shiatsu_booking]` to any WordPress page to display the booking wizard.
    - Configure this page in **Settings > Booking Page**.

3.  **Google Calendar API Setup**:
    - Go to **Booking > Settings**.
    - Enter `Client ID` and `Client Secret`.
    - Click **Connect Google Calendar** and authorize via OAuth 2.0.
    - Select calendars to read availability from and one to write new bookings to.

---

## 3. Frontend Booking Wizard (v1.3.18+)

The frontend application (`assets/js/booking-app.js`) is an AJAX-driven SPA.

### Step 1: Service Selection
- Lists configured services (Name, Price, Duration).

### Step 2: Date & Time Selection (Zero Latency)
- **Instant Load**: Availability is read from a pre-warmed cache (`osb_gcal_YYYY-MM-DD`, 1h TTL).
- **Month Miss Fallback**: If a month isn't cached, the system performs a single bulk fetch to warm the cache instantly.
- **Live Validation**: When a user clicks "Next", the system triggers `POST /validate-slot` to perform a real-time check against Google Calendar, bypassing all caches.

### Step 3: Client Details
- Collects personal details (Name, Email, Phone, Notes).

### Step 4: Summary & Confirmation
- Final review and booking submission (`POST /book`).

---

## 4. Admin Management Interface

Located in the WordPress Admin Dashboard under "Booking".

### Dashboard (Appointments)
- **Status Indicators**: `pending`, `confirmed`, `cancelled`, `reschedule_requested`, `admin_proposal`.
- **Actions**: Accept, Reject (with email), Propose New Time, Sync from Google.

### Settings
- **General**: Working Days/Hours, Timezone, Max Bookings Per Day.
- **Google Calendar**: OAuth connection status, Calendar selection.
- **Slot Presentation**: Tuning for the clustering algorithm (Min/Max slots, Edge probability).

### Logs
- System logs for debugging Sync, API, and Email issues.

---

## 5. Google Calendar Synchronization

Managed by `Ocean_Shiatsu_Booking_Google_Calendar` and `class-sync.php`.

### Sync Architecture (v1.3.18)
- **Destructive Write**: The 15-minute sync job (`osb_cron_sync_events`) actively *overwrites* the daily availability cache with fresh data from Google.
- **Pre-Warming**: Ensures that when a user visits the site, the cache is already hot.
- **Normalization**: All event data is normalized to Plain Arrays to ensure stability.
- **Webhooks**: Real-time updates from Google trigger an immediate cache refresh.

---

## 6. Email Notifications

| Trigger | Recipient | Subject | Context |
| :--- | :--- | :--- | :--- |
| **New Booking Request** | Admin | New Appointment Request | Client details, actions. |
| **Confirmation** | Client | Terminbestätigung | Date/Time, Cancel/Reschedule links. |
| **Rejection** | Client | Terminanfrage abgelehnt | (New in v1.3.16) Polite rejection notice. |
| **Cancellation (Client)** | Admin | Booking Cancelled | User action notification. |
| **Reschedule Request** | Admin | Reschedule Request | New time proposed. |
| **Admin Proposal** | Client | Neue Terminzeit vorgeschlagen | "How about time X?", Accept/Decline links. |
| **Sync Cancellation** | Client | Termin abgesagt | Triggered by GCal event deletion. |
| **Sync Time Change** | Client | Terminänderung | Triggered by GCal event move. |

---

## 7. Technical Details

- **Database**: Custom tables (`wp_osb_*`).
- **Timezone**: UTC-based logic for GCal, Local time for DB (`Y-m-d H:i:s`).
- **Security**: Nonces (`X-WP-Nonce`), Prepared Statements (`$wpdb->prepare`), Rate Limiting (IP-based).
- **API**: REST API under `wp-json/osb/v1/`.

---
---

## 8. Debug & Troubleshooting (v1.4.0)

### Enable Debug Mode
1. Go to **Booking > Settings > General**.
2. Check **Enable Debug Mode**.
3. Save Settings.

### Frontend Trace (Browser Console)
As a logged-in Administrator, open your browser's Developer Tools (F12) > Console.
- **`🔍 Availability Trace` Group**: Explains why slots were shown or hidden.
- **Tables**:
    - **Blockers**: Busy slots from Google Calendar or Local DB.
    - **Windows**: Calculated free time ranges.
    - **Candidates**: All potential slots before filtering.
    - **Selected**: Final slots shown to the user.
- **Performance**: 'Server-Timing' headers show `db`, `gcal`, and `logic` execution time in the Network tab.

### Cache Inspector
Go to **Booking > Cache Inspector**.
- View all active availability caches (`osb_gcal_YYYY-MM-DD`).
- Check **Expires In** to see when the cache will auto-refresh.
- Click **Clear** to force a fresh fetch for a specific day.

### Backend Logs
Go to **Booking > Logs**.
- **Webhook Logs**: Verify if Google is sending notifications ("Webhook Received").
- **Sync Logs**: Check "Two-Way Sync Started" and "Cache Updated" entries.
- **API Logs**: Request payloads (PII masked) and rate limit warnings.

---
