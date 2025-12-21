const osbV2 = {
    state: {
        step: 1,
        mode: 'booking', // booking, reschedule
        isDesktop: false,

        // Data
        config: null,
        provider: { name: '', image: '' },
        services: [], // Services could come from HTML or API, usually HTML in V1. We might need to fetch them or parse them.
        // V1 parsed them from DOM. V2 'buildLayout' wipes DOM. 
        // FIX: We must Parse services BEFORE wiping DOM.

        // Selection
        serviceId: null,
        serviceName: null,
        serviceDuration: null,
        servicePrice: null, // "Preis variabel" usually
        date: null,
        time: null,

        // Availability
        availabilityCache: {},
        monthlyAvailability: null,
        lastFetchedKey: null,

        token: null, // For external actions
        confirmationType: null, // 'request_submitted', 'booking_confirmed', 'cancelled', 'reschedule_request'
        bookingSummary: null, // Data for step 4
        originalBooking: null, // For reschedule flow
        clientData: null // For pre-filling form
    },

    init: async function () {
        console.log("OSB v2 Init");

        // 1. Fetch Config & Provider Data
        await this.loadConfig();

        // 2. Parse existing Services from V1 HTML (before we wipe it)
        this.parseServicesFromDOM();

        // 3. Build V2 Layout (Wipes existing content)
        this.buildStructure();

        // 4. Initial Render
        this.handleResize(); // Sets isDesktop and calls renderActiveStep
        window.addEventListener('resize', () => this.handleResize());

        // 5. Check URL Params (Actions)
        this.checkUrlActions();
    },

    loadConfig: async function () {
        try {
            const res = await fetch(`${osbData.apiUrl}config`);
            const data = await res.json();
            this.state.config = data;
            if (data.provider) {
                this.state.provider = data.provider;
            }
        } catch (e) {
            console.error("Config fetch failed", e);
        }
    },

    parseServicesFromDOM: function () {
        // V1 has .osb-service-card elements. We scrape data attributes.
        const cards = document.querySelectorAll('.osb-service-card');
        this.state.services = [];
        cards.forEach(card => {
            this.state.services.push({
                id: card.dataset.id,
                duration: card.dataset.duration,
                name: card.querySelector('h5, strong')?.innerText || 'Service',
                description: card.querySelector('p')?.innerText || '',
                price: card.dataset.price || 'Preis variabel'
            });
        });
    },

    buildStructure: function () {
        const root = document.getElementById('osb-booking-wizard');
        if (!root) return;

        root.id = 'osb-booking-wizard-v2'; // Switch ID for CSS scoping
        root.innerHTML = ''; // Clear V1 content

        // Create Split Layout
        const container = document.createElement('div');
        container.className = 'row osb-app-container';

        // Left Column (Desktop Main)
        const leftCol = document.createElement('div');
        leftCol.className = 'col-lg-8 osb-desktop-main d-none d-lg-block';
        leftCol.id = 'osb-desktop-main';

        // Right Column (Process Stack / Sidebar)
        const rightCol = document.createElement('div');
        rightCol.className = 'col-lg-4 col-12 osb-process-stack';
        rightCol.id = 'osb-process-stack';

        // --- Build Steps in Stack ---

        // Step 1: Services
        rightCol.innerHTML += `
            <div class="osb-step-card active" id="osb-step-card-1">
                <div class="osb-step-header">
                    <h3 class="osb-step-title">1. Leistungen</h3>
                    <button class="btn btn-sm btn-link text-decoration-none d-none" id="btn-edit-1" onclick="osbV2.goToStep(1)">ÄNDERN</button>
                </div>
                <div class="osb-step-content" id="osb-step-content-1">
                    <!-- Mobile Content Injected Here -->
                    <div id="osb-mobile-hook-1"></div> 
                    <!-- Summary Injected Here -->
                    <div id="osb-summary-hook-1" class="d-none"></div>
                </div>
            </div>
        `;

        // Step 2: Calendar
        rightCol.innerHTML += `
            <div class="osb-step-card disabled" id="osb-step-card-2">
                <div class="osb-step-header">
                    <h3 class="osb-step-title">2. Termin wählen</h3>
                    <button class="btn btn-sm btn-link text-decoration-none d-none" id="btn-edit-2" onclick="osbV2.goToStep(2)">ÄNDERN</button>
                </div>
                <div class="osb-step-content" id="osb-step-content-2">
                    <div id="osb-mobile-hook-2"></div>
                    <div id="osb-summary-hook-2" class="d-none"></div>
                </div>
            </div>
        `;

        // Step 3: Data
        rightCol.innerHTML += `
            <div class="osb-step-card disabled" id="osb-step-card-3">
                <div class="osb-step-header">
                    <h3 class="osb-step-title">3. Daten eingeben</h3>
                </div>
                <div class="osb-step-content" id="osb-step-content-3">
                    <div id="osb-mobile-hook-3"></div>
                </div>
            </div>

            <!-- Step 4: Confirmation -->
            <div class="osb-step-card disabled" id="osb-step-card-4">
                <div class="osb-step-header">
                    <h3 class="osb-step-title">4. Bestätigung</h3>
                </div>
                <div class="osb-step-content" id="osb-step-content-4">
                    <div id="osb-mobile-hook-4"></div>
                </div>
            </div>
        `;

        container.appendChild(leftCol);
        container.appendChild(rightCol);
        root.appendChild(container);

        // Render Service List immediately into memory/temp
        this.uiServiceList = this.generateServiceListUI();
    },

    handleResize: function () {
        const isDesktop = window.innerWidth >= 992;
        if (this.state.isDesktop !== isDesktop) {
            this.state.isDesktop = isDesktop;
            // Mode changed, move content
            this.moveContent();
        }
    },

    moveContent: function () {
        const step = this.state.step;
        const mainDesktop = document.getElementById('osb-desktop-main');
        const mobileHook = document.getElementById(`osb-mobile-hook-${step}`);

        // Find current active content
        // We need a reference to the active 'view' element.
        // Let's assume we store it in `this.currentViewEl`.

        if (!this.currentViewEl) {
            // Initial load, create view
            this.renderActiveStep();
            return;
        }

        if (this.state.isDesktop) {
            // Move to Desktop Column
            mainDesktop.appendChild(this.currentViewEl);
            // Ensure Desktop col is visible (handled by CSS d-lg-block but let's be safe)
        } else {
            // Move to Mobile Stack Hook
            // console.log(`Moving content to Mobile Hook: osb-mobile-hook-${step}`);
            if (mobileHook) {
                mobileHook.appendChild(this.currentViewEl);
            } else {
                console.error(`Mobile Hook not found: osb-mobile-hook-${step}`);
            }
        }
    },

    renderActiveStep: function () {
        // Clear old view
        const mainDesktop = document.getElementById('osb-desktop-main');
        mainDesktop.innerHTML = '';

        // Identify Mobile Hook
        for (let i = 1; i <= 4; i++) {
            const h = document.getElementById(`osb-mobile-hook-${i}`);
            if (h) h.innerHTML = '';
        }

        // Generate New View
        let viewHtml = document.createElement('div');
        viewHtml.className = 'osb-fade-in';

        if (this.state.step === 1) {
            // Note: We regenerate each time because cloneNode doesn't preserve onclick handlers
            viewHtml.appendChild(this.generateServiceListUI());
        } else if (this.state.step === 2) {
            viewHtml.appendChild(this.generateCalendarUI());
        } else if (this.state.step === 3) {
            viewHtml.appendChild(this.generateFormUI());
        } else if (this.state.step === 4) {
            viewHtml.appendChild(this.generateConfirmationUI());
        }

        this.currentViewEl = viewHtml;

        // Place it
        this.moveContent();

        // Update Stack Classes (Active/Disabled)
        this.updateStackState();

        // Post-Render Actions (Calendar Init)
        if (this.state.step === 2) {
            this.initCalendarLogic();
        }
    },

    updateStackState: function () {
        for (let i = 1; i <= 4; i++) {
            const card = document.getElementById(`osb-step-card-${i}`);
            const summary = document.getElementById(`osb-summary-hook-${i}`);
            const editBtn = document.getElementById(`btn-edit-${i}`);

            card.classList.remove('active', 'disabled');

            if (i === this.state.step) {
                card.classList.add('active');
                if (summary) summary.classList.add('d-none');
                if (editBtn) editBtn.classList.add('d-none');
            } else if (i < this.state.step) {
                // Completed
                if (summary) {
                    summary.classList.remove('d-none');
                    this.renderSummary(i);
                }
                if (editBtn) editBtn.classList.remove('d-none');
            } else {
                // Future
                card.classList.add('disabled');
                if (summary) summary.classList.add('d-none');
                if (editBtn) editBtn.classList.add('d-none');
            }
        }
    },

    renderSummary: function (step) {
        const container = document.getElementById(`osb-summary-hook-${step}`);
        if (step === 1) {
            container.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${this.state.serviceName}</strong><br>
                        <small class="text-muted">${this.state.serviceDuration} Min | ${this.state.servicePrice}</small>
                    </div>
                </div>
            `;
        } else if (step === 2) {
            // Rich Summary with Provider
            const dateStr = this.formatDateGerman(this.state.date);
            const pName = this.state.provider.name ? `<div style="font-size:0.9rem; margin-top:5px;">${this.state.config.provider.name}</div>` : '';
            const pImg = this.state.provider.image ? `<img src="${this.state.provider.image}" class="osb-provider-img" style="width:50px;height:50px;margin:0 auto 5px;">` : '';

            // On mobile, this summary replaces the content in the card.
            // On desktop sidebar, it's just text. 
            // The prompt "Step 2: Summary (RICH)" specifically requested the photo.

            container.innerHTML = `
                <div class="text-center mt-2">
                    ${pImg}
                    <div class="fw-bold">${dateStr}</div>
                    <div class="badge bg-secondary text-dark rounded-pill">${this.state.time} Uhr</div>
                    ${pName}
                </div>
            `;
        }
    },

    // --- UI Generators ---

    generateServiceListUI: function () {
        const div = document.createElement('div');
        div.innerHTML = `<h5 class="mb-3 d-lg-none">Bitte wähle die gewünschte Leistung</h5>
                         <h2 class="mb-4 d-none d-lg-block">Deine Buchung</h2>`;

        this.state.services.forEach(s => {
            const card = document.createElement('div');
            card.className = 'osb-service-card';
            card.onclick = () => this.selectService(s);

            // Determine Title (add Provider if available)
            let title = s.name;
            if (this.state.provider.name && !title.includes('mit')) {
                // Fix: Check if provider name exists and split carefully
                const pName = this.state.provider.name.split(' ')[0] || '';
                title += ` mit ${pName}`;
            }

            card.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h5 class="mb-1" style="color:var(--osb-teal); font-weight:600;">${title}</h5>
                        <div class="text-muted small mb-2">${s.duration} Min | ${s.price}</div>
                        <p class="mb-0 small text-secondary">${s.description || 'Shiatsu Behandlung'}</p>
                    </div>
                    <div>
                         <button class="btn btn-sm btn-outline-primary rounded-pill">Auswählen</button>
                    </div>
                </div>
            `;
            div.appendChild(card);
        });
        return div;
    },

    generateCalendarUI: function () {
        const div = document.createElement('div');
        div.innerHTML = `
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <h5 class="d-lg-none mb-3">Tag wählen</h5>
                    <div id="osb-calendar-wrapper" class="osb-calendar"></div>
                </div>
                <div class="col-lg-6">
                    <h5 class="d-lg-none mb-3">Uhrzeit wählen</h5>
                    <div id="osb-time-slots" class="osb-time-grid">
                        <div class="text-center text-muted w-100 py-5 grid-span-all">
                             Bitte wähle zuerst einen Tag.
                        </div>
                    </div>
                </div>
            </div>
         `;
        return div;
    },

    generateFormUI: function () {
        const div = document.createElement('div');

        // Desktop Top Summary
        const dateStr = this.formatDateGerman(this.state.date);

        // Handle Reschedule Mode vs New Booking
        const isReschedule = this.state.mode === 'reschedule';
        const client = isReschedule && this.state.clientData ? this.state.clientData : {};
        const readonlyAttr = isReschedule ? 'readonly' : '';
        const readonlyStyle = isReschedule ? 'background-color: #f8f9fa;' : '';

        div.innerHTML = `
             <div class="osb-summary-card mb-4">
                 <h5 class="mb-3">Deine Auswahl</h5>
                 <div class="osb-summary-item">
                     <span class="osb-summary-label">Behandlung</span>
                     <span class="osb-summary-value">${this.state.serviceName}</span>
                 </div>
                 <div class="osb-summary-item">
                     <span class="osb-summary-label">Datum</span>
                     <span class="osb-summary-value">${dateStr}</span>
                 </div>
                 <div class="osb-summary-item">
                     <span class="osb-summary-label">Zeit</span>
                     <span class="osb-summary-value">${this.state.time} Uhr</span>
                 </div>
             </div>

             <h4 class="mb-3">Deine Daten</h4>
             <form id="osb-booking-form" onsubmit="osbV2.submitBooking(event)">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Anrede *</label>
                    <select class="form-select osb-form-control" id="client_salutation" required ${readonlyAttr} style="${readonlyStyle}">
                        <option value="">bitte wählen</option>
                        <option value="Herr">Herr</option>
                        <option value="Frau">Frau</option>
                        <option value="Divers">Divers</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Vorname *</label>
                    <input type="text" class="form-control osb-form-control" id="client_first_name" required value="${client.first_name || ''}" ${readonlyAttr} style="${readonlyStyle}">
                </div>
                 <div class="mb-3">
                    <label class="form-label small fw-bold">Name *</label>
                    <input type="text" class="form-control osb-form-control" id="client_last_name" required value="${client.last_name || ''}" ${readonlyAttr} style="${readonlyStyle}">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">E-Mail *</label>
                    <input type="email" class="form-control osb-form-control" id="client_email" required value="${client.email || ''}" ${readonlyAttr} style="${readonlyStyle}">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Telefon</label>
                    <input type="tel" class="form-control osb-form-control" id="client_phone" value="${client.phone || ''}" ${readonlyAttr} style="${readonlyStyle}">
                </div>
                <!-- Allow Notes editing? Usually yes, even for reschedule. Requirements didn't specify, but implies Contact Data is read-only. Let's keep notes editable. -->
                <div class="mb-3">
                    <label class="form-label small fw-bold">Bemerkungen</label>
                    <textarea class="form-control osb-form-control" style="border-radius:20px !important;" id="client_notes" rows="2">${client.notes || ''}</textarea>
                </div>
                
                <div class="osb-actions-footer">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="osbV2.prevStep()">ZURÜCK</button>
                    ${isReschedule ?
                `<button type="submit" class="btn btn-primary rounded-pill flex-grow-1 text-white fw-bold">ÄNDERUNG BESTÄTIGEN</button>` :
                `<button type="submit" class="btn btn-primary rounded-pill flex-grow-1 text-white fw-bold">TERMINANFRAGE SENDEN</button>`
            }
                </div>
             </form>
        `;

        // Post-render: Set Select Value manually if needed
        setTimeout(() => {
            if (client.salutation) {
                const el = document.getElementById('client_salutation');
                if (el) el.value = client.salutation;
            }
        }, 0);

        return div;
    },

    generateConfirmationUI: function () {
        const div = document.createElement('div');
        div.className = 'text-center p-4';

        const type = this.state.confirmationType || 'request_submitted';
        const s = this.state.bookingSummary || {};

        let icon = '<i class="bi bi-envelope-paper" style="font-size:3rem; color:var(--osb-teal);"></i>';
        let title = 'Terminanfrage gesendet!';
        let message = `Vielen Dank für deine Shiatsu Terminanfrage!\n\nIch melde mich in Kürze bei dir, um den Termin zu bestätigen.\n\nDu erhältst gleich eine E-Mail mit den Details deiner Anfrage.`;

        switch (type) {
            case 'booking_confirmed':
                icon = '<i class="bi bi-check-circle-fill" style="font-size:3rem; color:var(--osb-teal);"></i>';
                title = 'Termin bestätigt!';
                message = `Dein Termin wurde erfolgreich gebucht und bestätigt.\n\nEine Bestätigung wurde an deine E-Mail-Adresse gesendet.`;
                break;
            case 'reschedule_request': // Or 'reschedule_submitted'
                icon = '<i class="bi bi-arrow-repeat" style="font-size:3rem; color:var(--osb-teal);"></i>';
                title = 'Änderung eingereicht';
                message = `Vielen Dank für deine Terminänderung.\n\nDiese muss noch bestätigt werden. Du erhältst in Kürze eine Email.\n\nBitte überprüfe auch deinen Spam-Ordner.`;
                break;
            case 'gcal_sync_failed': // Fallback Option B
                icon = '<i class="bi bi-exclamation-triangle" style="font-size:3rem; color:#f0ad4e;"></i>';
                title = 'Fast geschafft';
                message = `Dein Termin muss noch manuell bestätigt werden. Du erhältst in Kürze eine Email!`;
                break;
            case 'cancelled':
                icon = '<i class="bi bi-x-circle" style="font-size:3rem; color:#dc3545;"></i>';
                title = 'Termin storniert';
                message = `Der Termin wurde erfolgreich storniert.`;
                break;
        }

        // New Booking Button for cancelled state
        let buttonHtml = '';
        if (type === 'cancelled') {
            buttonHtml = `<button class="btn btn-outline-primary rounded-pill mt-4" onclick="window.location.href = osbData.bookingPageUrl">NEUEN TERMIN BUCHEN</button>`;
        }

        div.innerHTML = `
            <div class="mb-4">${icon}</div>
            <h2 class="osb-confirmation-title mb-3">${title}</h2>
            
            <div class="osb-summary-card mb-4 text-start mx-auto" style="max-width:400px;">
                <div class="osb-summary-item">
                    <span class="osb-summary-label">Leistung</span>
                    <span class="osb-summary-value">${s.service_name || this.state.serviceName}</span>
                </div>
                <div class="osb-summary-item">
                    <span class="osb-summary-label">Termin</span>
                    <span class="osb-summary-value">${this.formatDateGerman(s.date || this.state.date)} um ${s.time || this.state.time} Uhr</span>
                </div>
            </div>
            <p class="osb-confirmation-message">${message}</p>
            ${buttonHtml}
        `;
        return div;
    },

    // --- Actions ---

    selectService: function (service) {
        this.state.serviceId = service.id;
        this.state.serviceDuration = service.duration;
        this.state.servicePrice = service.price;
        this.state.serviceName = service.name;
        this.goToStep(2);
    },

    goToStep: function (num) {
        this.state.step = num;
        this.renderActiveStep();
    },

    prevStep: function () {
        if (this.state.step > 1) {
            this.goToStep(this.state.step - 1);
        }
    },

    nextStep: function () {
        if (this.state.step < 3) {
            this.goToStep(this.state.step + 1);
        }
    },

    // --- Calendar Logic (Similar to V1 but refactored DOM) ---

    initCalendarLogic: function () {
        // Bug #12 Fix: Restore selected month if user navigates back
        let startDate = new Date();
        if (this.state.date) {
            startDate = new Date(this.state.date + 'T00:00:00');
        } else if (this.state.currentYear && this.state.currentMonth !== undefined) {
            startDate = new Date(this.state.currentYear, this.state.currentMonth, 1);
        }
        this.renderCalendarGrid(startDate);

        // Bug #12 Fix: Re-render time slots if date was already selected
        if (this.state.date) {
            this.fetchTimeSlots(this.state.date);
        }
    },

    renderCalendarGrid: function (date) {
        const wrapper = document.getElementById('osb-calendar-wrapper');
        if (!wrapper) return;

        const year = date.getFullYear();
        const month = date.getMonth();
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDay = (firstDay.getDay() + 6) % 7;
        const monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

        this.state.currentYear = year;
        this.state.currentMonth = month;

        // Header
        let html = `
            <div class="osb-calendar-header">
                <button class="btn btn-sm btn-outline-light text-dark border-0" onclick="osbV2.changeMonth(-1)">&lt;</button>
                <div class="fw-bold">${monthNames[month]} ${year}</div>
                <button class="btn btn-sm btn-outline-light text-dark border-0" onclick="osbV2.changeMonth(1)">&gt;</button>
            </div>
            <div class="osb-calendar-grid">
                 <div class="small fw-bold text-muted">Mo</div>
                 <div class="small fw-bold text-muted">Di</div>
                 <div class="small fw-bold text-muted">Mi</div>
                 <div class="small fw-bold text-muted">Do</div>
                 <div class="small fw-bold text-muted">Fr</div>
                 <div class="small fw-bold text-muted">Sa</div>
                 <div class="small fw-bold text-muted">So</div>
        `;

        // Empty
        for (let i = 0; i < startingDay; i++) {
            html += `<div></div>`;
        }

        // Days
        // Check cache for status
        const cacheKey = `${year}-${String(month + 1).padStart(2, '0')}_${this.state.serviceId}`;
        const availability = this.state.monthlyAvailability && this.state.lastFetchedKey === cacheKey ? this.state.monthlyAvailability : {};

        // Trigger fetch if not loaded
        if (this.state.lastFetchedKey !== cacheKey) {
            this.fetchMonthlyAvailability(year, month);
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        for (let d = 1; d <= daysInMonth; d++) {
            const current = new Date(year, month, d);
            const dateStr = this.formatDateLocal(current);

            let classes = 'osb-cal-day';
            let status = availability[dateStr] || 'available'; // default

            if (current < today) {
                classes += ' disabled';
            } else if (status === 'holiday' || status === 'closed') {
                classes += ' disabled';
            } else if (status === 'booked') {
                // classes += ' booked'; // Waitlist logic?
                classes += ' disabled';
            } else {
                classes += ' available';
            }

            if (this.state.date === dateStr) classes += ' selected';

            html += `<div class="${classes}" onclick="osbV2.selectDate('${dateStr}')">${d}</div>`;
        }

        html += `</div>`;
        wrapper.innerHTML = html;
    },

    changeMonth: function (delta) {
        const newDate = new Date(this.state.currentYear, this.state.currentMonth + delta, 1);
        this.renderCalendarGrid(newDate);
    },

    fetchMonthlyAvailability: function (year, month) {
        const monthStr = `${year}-${String(month + 1).padStart(2, '0')}`;
        const wrapper = document.getElementById('osb-calendar-wrapper');

        // Bug #17 Fix: Show loading state during month fetch
        if (wrapper) {
            const header = wrapper.querySelector('.osb-calendar-header');
            if (header) {
                const loadingIndicator = document.createElement('span');
                loadingIndicator.className = 'osb-month-loading spinner-border spinner-border-sm text-muted ms-2';
                header.appendChild(loadingIndicator);
            }
        }

        fetch(`${osbData.apiUrl}availability/month?service_id=${this.state.serviceId}&month=${monthStr}&_wpnonce=${osbData.nonce}`)
            .then(res => res.json())
            .then(data => {
                this.state.monthlyAvailability = data;
                this.state.lastFetchedKey = `${monthStr}_${this.state.serviceId}`;
                this.renderCalendarGrid(new Date(year, month, 1)); // Re-render
            })
            .catch(err => {
                // Bug #4 Fix: Error handler
                console.error('Monthly availability fetch failed:', err);
                // Remove loading indicator
                const spinner = document.querySelector('.osb-month-loading');
                if (spinner) spinner.remove();
            });
    },

    selectDate: function (dateStr) {
        this.state.date = dateStr;
        this.renderCalendarGrid(new Date(this.state.currentYear, this.state.currentMonth, 1));
        this.fetchTimeSlots(dateStr);
    },

    fetchTimeSlots: function (dateStr) {
        const container = document.getElementById('osb-time-slots');
        if (!container) return;

        // Bug #13 Fix: Track current request to handle race conditions
        this.state.pendingSlotRequest = dateStr;

        container.innerHTML = '<div class="grid-span-all text-center"><div class="spinner-border spinner-border-sm text-primary"></div></div>';

        fetch(`${osbData.apiUrl}availability?date=${dateStr}&service_id=${this.state.serviceId}&_wpnonce=${osbData.nonce}`)
            .then(async res => {
                // v1.7.2 Fix: Check response status before parsing JSON
                if (!res.ok) {
                    const text = await res.text();
                    // Truncate error text to avoid flooding UI with HTML
                    const shortText = text.substring(0, 100) + (text.length > 100 ? '...' : '');
                    throw new Error(`Server Error (${res.status}): ${shortText}`);
                }
                return res.json();
            })
            .then(data => {
                // Bug #13 Fix: Ignore stale responses
                if (this.state.pendingSlotRequest !== dateStr) {
                    console.log('Ignoring stale slot response for', dateStr);
                    return;
                }

                // If wrapped debug
                let slots = data.slots || data;
                this.renderTimeSlots(slots);
            })
            .catch(err => {
                // Bug #4 Fix: Error handler
                console.error('Time slots fetch failed:', err);
                if (this.state.pendingSlotRequest === dateStr) {
                    // Show actual error if available or generic
                    const msg = err.message || "Fehler beim Laden. Bitte erneut versuchen.";
                    container.innerHTML = `<div class="grid-span-all text-center text-danger small">${msg}</div>`;
                }
            });
    },

    renderTimeSlots: function (slots) {
        const container = document.getElementById('osb-time-slots');
        container.innerHTML = '';

        if (!slots || slots.length === 0) {
            container.innerHTML = '<div class="text-muted small grid-span-all text-center">Keine Termine frei.</div>';
            return;
        }

        slots.forEach(time => {
            const div = document.createElement('div');
            div.className = 'osb-time-slot';
            if (this.state.time === time) div.classList.add('selected');
            div.innerText = time;
            div.onclick = () => this.selectTime(time);
            container.appendChild(div);
        });
    },

    selectTime: function (time) {
        this.state.time = time;
        // Re-render to highlight
        const btns = document.querySelectorAll('.osb-time-slot');
        btns.forEach(b => {
            b.classList.remove('selected');
            if (b.innerText === time) b.classList.add('selected');
        });

        // Bug #1 Fix: Call validateAndNext() instead of direct goToStep()
        setTimeout(() => this.validateAndNext(), 300);
    },

    // Bug #2 Fix: Add validateAndNext() function (ported from V1)
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
                if (data.valid) {
                    this.goToStep(3);
                } else {
                    // Slot was taken
                    alert(data.message || 'Dieser Termin ist leider bereits vergeben. Bitte wähle einen anderen.');
                    // Force refresh slots
                    this.state.time = null;
                    this.fetchTimeSlots(this.state.date);
                }
            })
            .catch(err => {
                // Bug #4 Fix: Error handler
                this.showLoading(false);
                console.error('Slot validation failed:', err);
                alert('Ein Fehler ist aufgetreten. Bitte versuche es erneut.');
            });
    },

    showLoading: function (show) {
        // Simple loading overlay
        let overlay = document.getElementById('osb-loading-overlay');
        if (show) {
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'osb-loading-overlay';
                overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.7);z-index:9999;display:flex;align-items:center;justify-content:center;';
                overlay.innerHTML = '<div class="spinner-border text-primary"></div>';
                document.body.appendChild(overlay);
            }
            overlay.style.display = 'flex';
        } else {
            if (overlay) overlay.style.display = 'none';
        }
    },

    submitBooking: function (e) {
        e.preventDefault();
        // (V1 Submit Logic)
        const salutation = document.getElementById('client_salutation').value;
        const firstName = document.getElementById('client_first_name').value;
        const lastName = document.getElementById('client_last_name').value;

        const data = {
            service_id: this.state.serviceId,
            duration: this.state.serviceDuration,
            date: this.state.date,
            time: this.state.time,
            client_name: `${salutation} ${firstName} ${lastName}`,
            client_salutation: salutation,
            client_first_name: firstName,
            client_last_name: lastName,
            client_email: document.getElementById('client_email').value,
            client_phone: document.getElementById('client_phone').value,
            client_notes: document.getElementById('client_notes').value
        };

        const btn = document.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerText = 'Senden...';

        fetch(`${osbData.apiUrl}booking`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': osbData.nonce },
            body: JSON.stringify(data)
        })
            .then(res => {
                if (res.status === 403) throw new Error('NONCE_FAIL');
                return res.json();
            })
            .then(res => {
                if (res.success) {
                    this.state.confirmationType = res.confirmation_type;
                    this.state.bookingSummary = res.booking_summary;
                    // Move to Step 4
                    this.goToStep(4);
                    // Scroll to top
                    window.scrollTo(0, 0);
                } else {
                    alert('Fehler: ' + (res.message || 'Unbekannter Fehler'));
                    btn.disabled = false;
                    btn.innerText = 'TERMINANFRAGE SENDEN';
                }
            })
            .catch(err => {
                console.error('Booking submission failed:', err);

                if (err.message === 'NONCE_FAIL') {
                    alert('Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.');
                    window.location.reload();
                    return;
                }

                alert('Verbindungsfehler. Bitte versuche es erneut.');
                btn.disabled = false;
                btn.innerText = 'TERMINANFRAGE SENDEN';
            });
    },

    // --- Helpers ---
    formatDateLocal: function (date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    },

    formatDateGerman: function (dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr + 'T00:00:00'); // Safe Parse
        return date.toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
    },

    checkUrlActions: function () {
        const params = new URLSearchParams(window.location.search);
        const action = params.get('action');
        const token = params.get('token');

        if (!action || !token) return;
        this.state.token = token;

        if (action === 'cancel') {
            if (confirm('Möchtest du diesen Termin wirklich stornieren?')) {
                this.performCancellation(token);
            }
        } else if (action === 'reschedule') {
            this.loadBookingForReschedule(token);
        }
    },

    performCancellation: function (token) {
        fetch(`${osbData.apiUrl}action?action=cancel&token=${token}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.state.confirmationType = 'cancelled';
                    this.state.bookingSummary = data.booking_summary; // API should provide this
                    this.goToStep(4);
                } else {
                    alert('Fehler: ' + (data.message || 'Stornierung fehlgeschlagen'));
                }
            });
    },

    loadBookingForReschedule: function (token) {
        fetch(`${osbData.apiUrl}booking-by-token?token=${token}`)
            .then(res => res.json())
            .then(data => {
                if (data.id) {
                    // Pre-populate state
                    this.state.mode = 'reschedule';
                    this.state.originalBooking = { date: data.date, time: data.time };
                    this.state.serviceId = data.service_id;
                    this.state.serviceName = data.service_name;
                    this.state.serviceDuration = data.duration;

                    // Client Data for Pre-fill
                    this.state.clientData = {
                        salutation: data.client_salutation || '',
                        first_name: data.client_first_name || '',
                        last_name: data.client_last_name || '',
                        email: data.client_email,
                        phone: data.client_phone,
                        notes: data.client_notes
                    };

                    this.goToStep(2); // Jump to calendar
                }
            });
    }
};

document.addEventListener('DOMContentLoaded', () => osbV2.init());
