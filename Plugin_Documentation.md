# Ocean Shiatsu Booking - Plugin Documentation
**Version:** 1.3.16
**Author:** Ocean Shiatsu
**Date:** 2025-12-08

## 1. Introduction

Ocean Shiatsu Booking is a premium WordPress appointment booking plugin designed specifically for Shiatsu practitioners and similar wellness professionals. It provides a seamless frontend booking experience for clients, a robust backend management interface, and two-way synchronization with Google Calendar.

### Key Features Summary
- **Frontend Booking Wizard**: Multi-step, AJAX-driven booking form.
- **Smart Availability Clustering**: Specific algorithm to manage slot presentation and "scarcity" illusion.
- **Google Calendar Sync**: Two-way sync (GCal -> WP and WP -> GCal) handling busy slots and event creation.
- **Email Workflow**: Automated notifications for admins and clients (Request, Confirmation, Cancellation, Rescheduling).
- **Admin Management**: Dashboard for appointments, services, settings, and logs.

---

## 2. Installation & Setup

1.  **Plugin Activation**:
    - Upload the plugin folder to `wp-content/plugins/`.
    - Activate via the WordPress Plugins menu.
    - Upon activation, the plugin creates necessary database tables:
        - `wp_osb_appointments`: Stores booking data.
        - `wp_osb_services`: Stores service definitions.
        - `wp_osb_settings`: Key-value store for plugin configuration.
        - `wp_osb_logs`: System logs for debugging and auditing.

2.  **Shortcode Placement**:
    - Add `[ocean_shiatsu_booking]` to any WordPress page to display the booking wizard.
    - Configure this page in **Settings > Booking Page** to ensure email links work correctly.

3.  **Google Calendar API Setup**:
    - Go to **Booking > Settings**.
    - Enter `Client ID` and `Client Secret` from Google Cloud Console.
    - Click **Save Settings**.
    - Click **Connect Google Calendar** to authorize the application via OAuth 2.0.
    - Select calendars to read availability from and one calendar to write new bookings to.

---

## 3. Frontend Booking Wizard

 The frontend application (`assets/js/booking-app.js`) is a single-page application (SPA) embedded via shortcode.

### Step 1: Service Selection
- Lists all services configured in the backend.
- Displays Name, Price, and Duration.
- User selects a service to proceed.

### Step 2: Date & Time Selection
- **Calendar UI**: Custom JavaScript-rendered calendar.
    - **Blocked Days**: Holidays and weekends are visually disabled.
    - **Partially Booked**: Days with strict availability limitations are marked.
    - **Past Days**: Disabled.
- **Time Slots**:
    - On date click, available slots are fetched via AJAX (`POST wp-json/osb/v1/availability`).
    - **Clustering Algorithm**: The system does NOT show all technically available slots. It uses a "Freewindow + Sequential Fill" algorithm:
        1.  Identifies "Free Windows" between existing busy slots (taking preparation time into account).
        2.  Clusters new slots *adjacent* to existing bookings ("Filling from the middle out") to minimize gaps.
        3.  **Presentation Logic**:
            - **Max/Min Limits**: Shows a configurable minimum (e.g., 3) and maximum (e.g., 8) slots.
            - **Variety Sampling**: On completely empty days, it randomly samples slots (weighted towards edges) to avoid showing a "completely open" schedule, creating a sense of demand.

### Step 3: Client Details
- Collects: Salutation, First Name, Last Name, Email, Phone, and Notes.
- Validates required fields.

### Step 4: Summary & Confirmation
- Shows selected Service, Date, Time, and Price.
- On Confirm, sends `POST wp-json/osb/v1/book`.
- Displays success message.

---

## 4. Admin Management Interface

Located in the WordPress Admin Dashboard under "Booking".

### Dashboard (Appointments)
- **List View**: Shows next 50 upcoming appointments.
- **Status Indicators**:
    - `pending`: Awaiting admin approval (if enabled, though currently most flows auto-confirm or use "Request" logic).
    - `confirmed`: Standard active booking.
    - `cancelled`: Cancelled by user or admin.
    - `reschedule_requested`: User requested a new time.
    - `admin_proposal`: Admin proposed a new time (waiting for user).
- **Actions**:
    - **Accept/Reject**: For pending requests.
    - **Propose New Time**: Opens a form to select a new date/time for an existing booking.
    - **Revoke Proposal**: Revert a proposal to its previous state.
    - **Sync from Google**: Manual trigger to pull events from GCal immediately.

### Services
- CRUD interface for Services.
- Fields: Name, Duration (minutes), Preparation Time (buffer minutes), Price, Description, Image URL.

### Settings
- **General**:
    - **Booking Page**: Select the WP page containing the shortcode.
    - **Working Days/Hours**: Define global availability (Monday-Sunday, Start/End time).
    - **Timezone**: Select the operational timezone (defaults to Europe/Berlin).
- **Availability & Limits**:
    - **Max Bookings Per Day**: Cap total daily appointments.
    - **All-Day Events**: Option to treat GCal all-day events as "Holidays" (blocking the full day).
    - **Holiday Keywords**: Comma-separated list (e.g., "Holiday, Closed") that triggers a full-day block if found in a GCal event title.
- **Slot Presentation (Algorithm Tuning)**:
    - `Slot Min Show`: Minimum slots to display.
    - `Slot Max Show`: Cap on displayed slots.
    - `Empty Day Variety %`: How many slots to show on an empty day.
    - `Edge Probability`: Chance of showing the very first/last slots of the day.

### Logs
- View system logs (INFO, DEBUG, ERROR, WARNING).
- Useful for debugging Sync issues or Email failures.
- Option to clear logs.

---

## 5. Google Calendar Synchronization

Managed by `Ocean_Shiatsu_Booking_Google_Calendar` and `class-sync.php`.

### OAuth Flow
- Uses standard Google OAuth 2.0 Web Server flow.
- Tokens (Access & Refresh) are stored in `wp_osb_settings`.
- Auto-refreshes expired access tokens.

### Sync Logic (Bidirectional)
1.  **Read (GCal to WP)**:
    - Fetches events from *selected* calendars.
    - events are stored in `wp_osb_appointments` with `service_id=0` (External).
    - **Clustering**: External events are treated as "Busy Blocks" by the availability algorithm.
    - **Sync Cron**: Runs hourly (`osb_cron_sync_events`) to fetch updates.
    - **Notifications**: If a GCal event corresponding to a WP Booking is moved or cancelled in GCal, the system detects this change and notifies the user via email.

2.  **Write (WP to GCal)**:
    - When a booking is Created/Rescheduled in WP, it is pushed to the *write* calendar in GCal.
    - Stores the `gcal_event_id` in the local DB to track future updates.

---

## 6. Email Notifications

Managed by `Ocean_Shiatsu_Booking_Emails`.

| Trigger | Recipient | Subject | Context |
| :--- | :--- | :--- | :--- |
| **New Booking Request** | Admin | New Appointment Request | Client details, actions (Accept/Reject/Propose). |
| **Confirmation** | Client | Terminbestätigung | Date/Time, links to Reschedule/Cancel. |
| **Cancellation (Client)** | Admin | Booking Cancelled | Notification of user action. |
| **Reschedule Request** | Admin | Reschedule Request | New time proposed by client. |
| **Admin Proposal** | Client | Neue Terminzeit vorgeschlagen | "Original time didn't work, how about X?", Accept/Decline links. |
| **Proposal Accepted** | Admin | Proposal Accepted | Client accepted the new time. |
| **Proposal Declined** | Admin | Proposal Declined | Client refused the new time. |
| **Sync Cancellation** | Client | Termin abgesagt | Triggered when GCal event is deleted. |
| **Sync Time Change** | Client | Terminänderung | Triggered when GCal event is moved. |

---

## 7. Technical Details

- **Database**: Custom tables (`wp_osb_*`).
- **Timezone Handling**: All DB timestamps are stored in local time `Y-m-d H:i:s`. GCal events are converted to/from the configured Timezone.
- **Security**:
    - **Nonces**: Used on all admin actions and public REST API endpoints (`X-WP-Nonce`).
    - **Prepared Statements**: All SQL queries use `$wpdb->prepare()`.
    - **Escaping**: All outputs are escaped (`esc_html`, `esc_attr`).
- **Dependencies**:
    - `google/apiclient`: For Google API interaction (via Composer).

---
