const osbApp = {
    state: {
        step: 1,
        serviceId: null,
        serviceDuration: null,
        serviceName: null,
        date: null,
        time: null,
        availabilityCache: {} // Cache for pre-fetched slots
    },

    init: function () {
        // Set min date to today
        const today = new Date();
        const datePicker = document.getElementById('osb-date-picker');
        datePicker.setAttribute('min', this.formatDateLocal(today));

        // ... (rest of init)

        this.renderCalendar(new Date());

        // Check URL Params
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get('action');
        const token = urlParams.get('token');

        if (action && token) {
            this.handleExternalAction(action, token);
        }
    },

    // Helper to fix Timezone Off-By-One Bug
    formatDateLocal: function (date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    },

    traceDebug: function (debug) {
        if (!debug) return;

        const label = `🔍 Availability Trace`;
        console.groupCollapsed(label, debug.timestamp);

        console.log(`%c Source: ${debug.source}`, 'font-weight: bold; color: #0073aa;');

        if (debug.metrics) {
            console.log('⏱️ Performance Metrics:', debug.metrics);
        }

        if (debug.logs) {
            console.group('🛠️ Decision Components');
            if (debug.logs.blockers) {
                console.log('Blockers (Busy Slots):');
                console.table(debug.logs.blockers);
            }
            if (debug.logs.windows) {
                console.log('Free Windows:');
                console.table(debug.logs.windows);
            }
            if (debug.logs.candidates) {
                console.log('Slot Candidates (Pre-Filter):', debug.logs.candidates);
            }
            if (debug.logs.selected) {
                console.log('Final Selected Slots:', debug.logs.selected);
            }
            if (debug.logs.steps) {
                console.log('Log Steps:');
                console.table(debug.logs.steps);
            }
            console.groupEnd();
        }

        if (debug.calculation_log) {
            // Handle single day structure if different (I used 'logs' in catch-all range and 'calculation_log' in single day - unifying to 'logs' is better but I used 'calculation_log' in one place in PHP. Let's handle both or fix PHP.
            // PHP: 'logs' => $clustering->get_last_debug_log() (Range)
            // PHP: 'calculation_log' => ... (Single)
            // Let's support both here :)
            const logs = debug.calculation_log;
            console.group('🛠️ Decision Components (Single Day)');
            if (logs.blockers) console.table(logs.blockers);
            if (logs.windows) console.table(logs.windows);
            if (logs.steps) console.table(logs.steps);
            console.groupEnd();
        }

        console.groupEnd();
    },

    renderCalendar: function (date) {
        const container = document.getElementById('osb-calendar-container');
        if (!container) return;

        const year = date.getFullYear();
        const month = date.getMonth();
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        this.state.currentYear = year;
        this.state.currentMonth = month;

        // Fetch availability if not cached for this month
        // We do this async, but render calendar immediately
        this.fetchMonthlyAvailability(year, month);

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDay = (firstDay.getDay() + 6) % 7; // Adjust for Monday start (0=Mon, 6=Sun)

        const monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

        let html = `
            <div class="osb-calendar-header">
                <button class="btn btn-sm btn-outline-secondary" onclick="osbApp.prevMonth()">&lt;</button>
                <span class="fw-bold">${monthNames[month]} ${year}</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="osbApp.nextMonth()">&gt;</button>
            </div>
            <div class="osb-calendar-grid">
                <div class="osb-calendar-day-header">Mo</div>
                <div class="osb-calendar-day-header">Di</div>
                <div class="osb-calendar-day-header">Mi</div>
                <div class="osb-calendar-day-header">Do</div>
                <div class="osb-calendar-day-header">Fr</div>
                <div class="osb-calendar-day-header">Sa</div>
                <div class="osb-calendar-day-header">So</div>
        `;

        // Empty cells
        console.log(`Calendar: firstDay=${firstDay}, getDay()=${firstDay.getDay()}, startingDay=${startingDay}`);
        for (let i = 0; i < startingDay; i++) {
            html += `<div class="osb-calendar-day osb-day-empty"></div>`;
        }

        // Days
        for (let day = 1; day <= daysInMonth; day++) {
            const currentDayDate = new Date(year, month, day);
            const dateStr = this.formatDateLocal(currentDayDate);

            // DEBUG: Log days 14-18 to trace the shift
            if (day >= 14 && day <= 18) {
                console.log(`Rendering day ${day}: currentDayDate=${currentDayDate}, dateStr=${dateStr}`);
            }

            let classes = 'osb-calendar-day';
            let onclick = `onclick="osbApp.selectDate('${dateStr}')"`;

            // Check if past
            if (currentDayDate < today) {
                classes += ' osb-day-disabled';
                onclick = '';
            }

            // Check if today
            if (currentDayDate.getTime() === today.getTime()) {
                classes += ' osb-day-today';
            }

            // Check if selected
            if (this.state.date === dateStr) {
                classes += ' osb-day-selected';
            }

            // Check Availability Status (available, booked, holiday, closed)
            // this.state.monthlyAvailability now returns status strings
            const status = this.state.monthlyAvailability ? this.state.monthlyAvailability[dateStr] : null;

            // DEBUG: Log first 7 days to trace the shift issue
            if (day <= 7) {
                console.log(`Day ${day}: dateStr=${dateStr}, dayOfWeek=${currentDayDate.getDay()}, status=${status}`);
            }

            if (status === 'holiday') {
                classes += ' osb-day-holiday';
                onclick = ''; // Not clickable
            } else if (status === 'closed') {
                classes += ' osb-day-closed';
                onclick = ''; // Not clickable
            } else if (status === 'booked') {
                classes += ' osb-day-booked';
                onclick = `onclick="osbApp.showWaitlistOption('${dateStr}')"`; // Clickable for Waitlist
            } else if (status === 'available' || !status) {
                // Available (or unknown - treat as available)
                classes += ' osb-day-available';
            }

            html += `<div class="${classes}" ${onclick} data-date="${dateStr}">${day}</div>`;
        }

        html += `</div>`;
        container.innerHTML = html;
    },

    prevMonth: function () {
        const newDate = new Date(this.state.currentYear, this.state.currentMonth - 1, 1);
        // Prevent going back before today? 
        // Simple check: if new month end is before today, maybe allow viewing but disable days?
        // Let's just render.
        this.renderCalendar(newDate);
    },

    nextMonth: function () {
        const newDate = new Date(this.state.currentYear, this.state.currentMonth + 1, 1);
        this.renderCalendar(newDate);
    },

    selectDate: function (dateStr) {
        console.log('selectDate called with:', dateStr);
        this.state.date = dateStr;
        document.getElementById('osb-date-picker').value = dateStr;

        // Re-render to update selection highlight
        this.renderCalendar(new Date(this.state.currentYear, this.state.currentMonth, 1));

        // Fetch slots
        this.fetchSlots();
    },

    updateCalendarUI: function () {
        // Re-render with new availability data
        if (this.state.currentYear && this.state.currentMonth !== undefined) {
            this.renderCalendar(new Date(this.state.currentYear, this.state.currentMonth, 1));
        }
    },



    fetchMonthlyAvailability: function (year, month) {
        if (!this.state.serviceId) return;

        const monthStr = `${year}-${String(month + 1).padStart(2, '0')}`;
        const cacheKey = `${monthStr}_${this.state.serviceId}`;

        // Prevent Loop: If we already fetched this month/service recently, don't fetch again.
        // This breaks the renderCalendar -> fetch -> updateUI -> renderCalendar loop.
        if (this.state.lastFetchedKey === cacheKey) {
            return;
        }

        // Delayed loading
        let loadingShown = false;
        const loadingTimeout = setTimeout(() => {
            loadingShown = true;
            this.showLoading(true);
        }, 300);

        fetch(`${osbData.apiUrl}availability/month?service_id=${this.state.serviceId}&month=${monthStr}&_t=${new Date().getTime()}`)
            .then(res => res.json())
            .then(data => {
                // DEBUG: Log raw API response
                console.log('Monthly Availability API Response:', data);
                console.log('Sample keys:', Object.keys(data).slice(0, 7));

                this.state.monthlyAvailability = data;
                this.state.lastFetchedKey = cacheKey; // Mark as fetched
                this.updateCalendarUI();
            })
            .catch(err => {
                console.error('Failed to fetch monthly availability:', err);
            })
            .finally(() => {
                clearTimeout(loadingTimeout);
                if (loadingShown) this.showLoading(false);
            });
    },

    showWaitlistOption: function (dateStr) {
        // Placeholder for Waitlist Feature
        // In future: Open modal to collect waitlist signup
        alert(`Dieser Tag (${dateStr}) ist leider ausgebucht. Wartelisten-Funktion kommt bald!`);
    },

    handleExternalAction: function (action, token) {
        this.state.token = token;

        if (action === 'cancel') {
            if (confirm('Möchten Sie diesen Termin wirklich absagen?')) {
                this.performCancellation(token);
            } else {
                window.location.href = window.location.pathname; // Clear params
            }
        } else if (action === 'reschedule') {
            this.state.mode = 'reschedule';
            this.loadBookingForReschedule(token);
        } else if (action === 'accept_proposal') {
            this.handleProposalResponse(token, 'accept');
        } else if (action === 'decline_proposal') {
            this.handleProposalResponse(token, 'decline');
        }
    },

    handleProposalResponse: function (token, response) {
        this.showLoading(true);
        fetch(`${osbData.apiUrl}respond-proposal`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': osbData.nonce },
            body: JSON.stringify({ token: token, response: response })
        })
            .then(res => res.json())
            .then(data => {
                this.showLoading(false);
                if (data.success) {
                    if (response === 'accept') {
                        alert('Termin bestätigt! Vielen Dank.');
                    } else {
                        alert('Vorschlag abgelehnt.');
                    }
                    window.location.href = window.location.pathname; // Clear params
                } else {
                    alert('Fehler: ' + data.message);
                }
            });
    },

    performCancellation: function (token) {
        this.showLoading(true);
        fetch(`${osbData.apiUrl}cancel`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': osbData.nonce },
            body: JSON.stringify({ token: token })
        })
            .then(res => res.json())
            .then(data => {
                this.showLoading(false);
                if (data.success) {
                    alert('Termin erfolgreich abgesagt.');
                    window.location.reload();
                } else {
                    alert('Fehler beim Absagen.');
                }
            });
    },

    loadBookingForReschedule: function (token) {
        this.showLoading(true);
        fetch(`${osbData.apiUrl}booking-by-token?token=${token}`)
            .then(res => res.json())
            .then(booking => {
                this.showLoading(false);
                if (booking.id) {
                    // Set State
                    this.state.serviceId = booking.service_id;
                    this.state.serviceName = booking.service_name;
                    this.state.serviceDuration = booking.duration_minutes;

                    alert(`Termin verschieben: ${booking.service_name}. Bitte wählen Sie ein neues Datum.`);

                    // Skip to Step 2
                    document.getElementById('step-1').classList.add('d-none');
                    document.getElementById('step-2').classList.remove('d-none');
                    this.state.step = 2;
                    this.updateProgress();
                    this.prefetchAvailability(); // Start pre-fetching

                    // Update Header
                    document.querySelector('#step-2 h3').innerText = 'Neuen Termin wählen';
                } else {
                    alert('Ungültiger Link.');
                }
            });
    },

    selectService: function (id, duration, name) {
        this.state.serviceId = id;
        this.state.serviceDuration = duration;
        this.state.serviceName = name;
        this.nextStep();
    },

    prefetchAvailability: function () {
        // Fetch next 14 days
        const today = new Date();
        const endDate = new Date();
        endDate.setDate(today.getDate() + 14);

        const startStr = this.formatDateLocal(today);
        const endStr = this.formatDateLocal(endDate);

        console.log('Pre-fetching availability:', startStr, 'to', endStr);

        fetch(`${osbData.apiUrl}availability?start_date=${startStr}&end_date=${endStr}&service_id=${this.state.serviceId}`)
            .then(response => response.json())
            .then(data => {
                // Store in cache
                this.state.availabilityCache = { ...this.state.availabilityCache, ...data };
                console.log('Availability cached for 14 days');
            })
            .catch(err => console.error('Pre-fetch failed', err));
    },

    fetchSlots: function () {
        const dateInput = document.getElementById('osb-date-picker');
        const date = dateInput.value;
        if (!date) return;

        this.state.date = date;
        const container = document.getElementById('osb-time-slots');

        // Show loading
        container.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary spinner-border-sm"></div></div>';

        // Check Cache First
        if (this.state.availabilityCache[date]) {
            console.log('Using cached availability for', date);
            this.renderSlots(this.state.availabilityCache[date]);
            return;
        }

        // Fallback to Live Fetch with Timeout
        console.log(`Cache miss, fetching live for ${date} (Service ${this.state.serviceId})`);

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 15000); // 15s timeout

        fetch(`${osbData.apiUrl}availability?date=${date}&service_id=${this.state.serviceId}`, {
            signal: controller.signal
        })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                clearTimeout(timeoutId);
                let slots = data;

                // Handle Debug Wrapper
                if (data.debug) {
                    this.traceDebug(data.debug);
                }
                if (data.slots) {
                    slots = data.slots;
                }

                if (!Array.isArray(slots)) {
                    throw new Error('Invalid slots format received');
                }

                // Cache this single date too
                this.state.availabilityCache[date] = slots;
                this.renderSlots(slots);
            })
            .catch(err => {
                clearTimeout(timeoutId);
                console.error('Fetch Slots Error:', err);

                if (err.name === 'AbortError') {
                    container.innerHTML = '<div class="text-danger">Zeitüberschreitung. Bitte erneut versuchen.</div>';
                } else {
                    container.innerHTML = '<div class="text-danger">Fehler beim Laden der Termine.</div>';
                }
            });
    },

    renderSlots: function (slots) {
        const container = document.getElementById('osb-time-slots');
        container.innerHTML = '';
        if (slots.length === 0) {
            container.innerHTML = '<div class="alert alert-warning text-center">Keine Termine verfügbar.</div>';
            return;
        }

        slots.forEach(time => {
            const btn = document.createElement('button');
            btn.className = 'btn btn-outline-secondary w-100 mb-2 text-start';
            btn.innerHTML = `<strong>${time}</strong> Uhr`;
            btn.onclick = () => this.selectTime(time);
            container.appendChild(btn);
        });
    },

    selectTime: function (time) {
        this.state.time = time;
        // Highlight selected
        const btns = document.querySelectorAll('#osb-time-slots button');
        btns.forEach(b => b.classList.remove('btn-primary', 'text-white'));
        event.target.closest('button').classList.add('btn-primary', 'text-white');
        event.target.closest('button').classList.remove('btn-outline-secondary');

        // Validate on Server before advancing (Safety Barrier)
        setTimeout(() => this.validateAndNext(), 300);
    },

    validateAndNext: function () {
        this.showLoading(true);
        const data = {
            date: this.state.date,
            time: this.state.time,
            service_id: this.state.serviceId
        };

        fetch(`${osbData.apiUrl}validate-slot`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(res => res.json())
            .then(data => {
                this.showLoading(false);
                this.showLoading(false);

                // Handle Debug in Success Response? (Validation usually just returns {valid: true})
                // But my PHP code didn't add debug to success response of validate_slot, only error.
                // Wait, if I want to see WHY it's valid, I should have added it to success too?
                // Yes, but let's handle error first.

                if (data.valid) {
                    this.nextStep();
                } else {
                    // Check for Debug Data in Error Response
                    // WP REST API errors come as { code: ..., message: ..., data: { status: ..., debug: ... } }
                    // But here 'data' is the JSON body.
                    if (data.data && data.data.debug) {
                        this.traceDebug(data.data.debug);
                    }

                    // Invalid/Taken
                    alert(data.message || 'Dieser Termin ist leider bereits vergeben. Bitte wähle einen anderen.');
                    // Force refresh slots
                    delete this.state.availabilityCache[this.state.date]; // Clear cache for this day
                    this.fetchSlots();
                }
            })
            .catch(err => {
                this.showLoading(false);
                console.error(err);
                alert('Ein Fehler ist aufgetreten. Bitte versuche es erneut.');
            });
    },

    submitBooking: function (e) {
        e.preventDefault();
        this.showLoading(true);

        if (this.state.mode === 'reschedule') {
            const data = {
                token: this.state.token,
                date: this.state.date,
                time: this.state.time,
                duration: this.state.serviceDuration || 60 // Fallback if not set
            };

            fetch(`${osbData.apiUrl}reschedule`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': osbData.nonce },
                body: JSON.stringify(data)
            })
                .then(res => res.json())
                .then(data => {
                    this.showLoading(false);
                    if (data.success) {
                        alert('Termin erfolgreich verschoben!');
                        window.location.href = window.location.pathname; // Reload/Clear
                    } else {
                        alert('Fehler: ' + data.message);
                    }
                });
            return;
        }

        // New Booking
        // Combine Name Fields
        const salutation = document.getElementById('client_salutation').value;
        const firstName = document.getElementById('client_first_name').value;
        const lastName = document.getElementById('client_last_name').value;
        const fullName = `${salutation} ${firstName} ${lastName}`.trim();

        const data = {
            service_id: this.state.serviceId,
            duration: this.state.serviceDuration, // Required by API
            date: this.state.date,
            time: this.state.time,
            client_name: fullName, // Combined for backend compatibility
            client_salutation: salutation,
            client_first_name: firstName,
            client_last_name: lastName,
            client_email: document.getElementById('client_email').value,
            client_phone: document.getElementById('client_phone').value,
            client_notes: document.getElementById('client_notes').value
        };

        fetch(`${osbData.apiUrl}book`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': osbData.nonce },
            body: JSON.stringify(data)
        })
            .then(res => res.json())
            .then(data => {
                this.showLoading(false);
                if (data.success) {
                    this.nextStep(); // Show Success Step
                } else {
                    alert('Fehler: ' + data.message);
                }
            })
            .catch(err => {
                this.showLoading(false);
                alert('Ein Fehler ist aufgetreten.');
                console.error(err);
            });
    },

    nextStep: function () {
        // Hide current step
        document.getElementById(`step-${this.state.step}`).classList.add('d-none');
        this.state.step++;
        // Show next step
        document.getElementById(`step-${this.state.step}`).classList.remove('d-none');
        this.updateProgress();

        // Pre-fetch availability when entering Step 2
        if (this.state.step === 2 && this.state.serviceId) {
            this.prefetchAvailability();
            // Also re-render calendar to fetch monthly availability (was skipped in init() when no service selected)
            this.renderCalendar(new Date(this.state.currentYear, this.state.currentMonth, 1));
        }

        // Populate Summary Card when entering Step 3
        if (this.state.step === 3) {
            document.getElementById('summary-service').innerText = this.state.serviceName || '-';
            document.getElementById('summary-duration').innerText = (this.state.serviceDuration || '-') + ' Min.';

            // Format Date (German)
            if (this.state.date) {
                const dateObj = new Date(this.state.date + 'T00:00:00');
                const dateStr = dateObj.toLocaleDateString('de-DE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                document.getElementById('summary-date').innerText = dateStr;
            } else {
                document.getElementById('summary-date').innerText = '-';
            }

            document.getElementById('summary-time').innerText = this.state.time ? (this.state.time + ' Uhr') : '-';
        }
    },

    prevStep: function () {
        document.getElementById(`step-${this.state.step}`).classList.add('d-none');
        this.state.step--;
        document.getElementById(`step-${this.state.step}`).classList.remove('d-none');
        this.updateProgress();
    },

    updateProgress: function () {
        const percent = this.state.step * 25;
        document.getElementById('osb-progress').style.width = `${percent}%`;
    },

    showLoading: function (show) {
        const el = document.getElementById('osb-loading');
        if (show) el.classList.remove('d-none');
        else el.classList.add('d-none');
    }
};

document.addEventListener('DOMContentLoaded', () => osbApp.init());
