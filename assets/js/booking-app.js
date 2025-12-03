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
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('osb-date-picker').setAttribute('min', today);

        // Check URL Params
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get('action');
        const token = urlParams.get('token');

        if (action && token) {
            this.handleExternalAction(action, token);
        }
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

        const startStr = today.toISOString().split('T')[0];
        const endStr = endDate.toISOString().split('T')[0];

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

        // Fallback to Live Fetch
        console.log('Cache miss, fetching live for', date);
        fetch(`${osbData.apiUrl}availability?date=${date}&service_id=${this.state.serviceId}`)
            .then(response => response.json())
            .then(slots => {
                // Cache this single date too
                this.state.availabilityCache[date] = slots;
                this.renderSlots(slots);
            })
            .catch(err => {
                console.error(err);
                container.innerHTML = '<div class="text-danger">Fehler beim Laden der Termine.</div>';
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

        // Auto advance after short delay
        setTimeout(() => this.nextStep(), 300);
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
                .then(result => {
                    this.showLoading(false);
                    if (result.success) {
                        alert('Verschiebung angefragt. Sie erhalten eine Bestätigung per E-Mail.');
                        window.location.reload();
                    } else {
                        alert('Fehler: ' + result.message);
                    }
                });
            return;
        }

        const data = {
            service_id: this.state.serviceId,
            service_name: this.state.serviceName,
            duration: this.state.serviceDuration,
            date: this.state.date,
            time: this.state.time,
            client_name: document.getElementById('client_name').value,
            client_email: document.getElementById('client_email').value,
            client_phone: document.getElementById('client_phone').value,
            client_notes: document.getElementById('client_notes').value
        };

        fetch(`${osbData.apiUrl}booking`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': osbData.nonce
            },
            body: JSON.stringify(data)
        })
            .then(response => response.json())
            .then(result => {
                this.showLoading(false);
                if (result.success) {
                    this.nextStep(); // Go to success step
                } else {
                    alert('Fehler: ' + (result.message || 'Buchung fehlgeschlagen'));
                }
            })
            .catch(err => {
                this.showLoading(false);
                alert('Ein unerwarteter Fehler ist aufgetreten.');
                console.error(err);
            });
    },

    nextStep: function () {
        document.getElementById(`step-${this.state.step}`).classList.add('d-none');
        this.state.step++;
        document.getElementById(`step-${this.state.step}`).classList.remove('d-none');
        this.updateProgress();

        // Pre-fetch availability when entering Step 2
        if (this.state.step === 2 && this.state.serviceId) {
            this.prefetchAvailability();
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
