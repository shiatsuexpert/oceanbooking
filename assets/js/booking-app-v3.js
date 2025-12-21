/**
 * Ocean Shiatsu Booking - Frontend V3
 * Plugin Version: 2.0
 * 
 * Features:
 * - State-based architecture
 * - Event delegation (no inline onclick)
 * - XSS-safe DOM manipulation
 * - Manual date parsing (Safari/iOS safe)
 * - Waitlist support
 * - Reminder preferences
 * - i18n via osbConfig.labels
 */

const osbV3 = {
    // ========================================
    // STATE
    // ========================================
    state: {
        step: 1,
        mode: 'booking', // 'booking' | 'reschedule' | 'waitlist'

        // Config from WordPress
        config: null,
        labels: {},

        // Selected data
        selectedService: null,  // {id, name, duration, price, image, description}
        selectedDate: null,     // 'YYYY-MM-DD' format
        selectedTime: null,     // 'HH:MM' format

        // Waitlist
        isWaitlist: false,
        waitTimeFrom: '',  // FIX: Empty default forces user choice
        waitTimeTo: '',    // FIX: Empty default forces user choice

        // Reminder
        reminderPreference: 'none', // 'none' | '24h' | '48h'

        // Form data
        formData: {
            salutation: 'n',    // 'm' | 'w' | 'n' (codes)
            firstName: '',
            lastName: '',
            email: '',
            phone: '',
            notes: '',
            newsletter: false,
        },

        // Calendar state
        currentMonth: new Date(),
        monthlyAvailability: {},
        daySlots: [],

        // UI state
        loading: false,
        error: null,

        // Results
        bookingSummary: null,
        originalBooking: null, // For reschedule

        // Request tracking (for race condition prevention)
        _lastSlotsRequest: null,
        _lastAvailabilityRequest: null,
    },

    // ========================================
    // SVG ICONS
    // ========================================
    icons: {
        // FontAwesome 6 Icons (Solid/Regular as per prototype)
        spa: `<svg aria-hidden="true" viewBox="0 0 576 512" fill="currentColor"><path d="M183.1 235.3c33.7 20.7 62.9 48.1 85.8 80.5c7 9.9 13.4 20.3 19.1 31c5.7-10.8 12.1-21.1 19.1-31c22.9-32.4 52.1-59.8 85.8-80.5C437.6 207.8 490.1 192 546 192l9.9 0c11.1 0 20.1 9 20.1 20.1C576 360.1 456.1 480 308.1 480L288 480l-20.1 0C119.9 480 0 360.1 0 212.1C0 201 9 192 20.1 192l9.9 0c55.9 0 108.4 15.8 153.1 43.3zM301.5 37.6c15.7 16.9 61.1 71.8 84.4 164.6c-38 21.6-71.4 50.8-97.9 85.6c-26.5-34.8-59.9-63.9-97.9-85.6c23.2-92.8 68.6-147.7 84.4-164.6C278 33.9 282.9 32 288 32s10 1.9 13.5 5.6z"/></svg>`,
        calendar: `<svg aria-hidden="true" viewBox="0 0 448 512" fill="currentColor"><path d="M152 24c0-13.3-10.7-24-24-24s-24 10.7-24 24l0 40L64 64C28.7 64 0 92.7 0 128l0 16 0 48L0 448c0 35.3 28.7 64 64 64l320 0c35.3 0 64-28.7 64-64l0-256 0-48 0-16c0-35.3-28.7-64-64-64l-40 0 0-40c0-13.3-10.7-24-24-24s-24 10.7-24 24l0 40L152 64l0-40zM48 192l80 0 0 56-80 0 0-56zm0 104l80 0 0 64-80 0 0-64zm128 0l96 0 0 64-96 0 0-64zm144 0l80 0 0 64-80 0 0-64zm80-48l-80 0 0-56 80 0 0 56zm0 160l0 40c0 8.8-7.2 16-16 16l-64 0 0-56 80 0zm-128 0l0 56-96 0 0-56 96 0zm-144 0l0 56-64 0c-8.8 0-16-7.2-16-16l0-40 80 0zM272 248l-96 0 0-56 96 0 0 56z"/></svg>`,
        user: `<svg aria-hidden="true" viewBox="0 0 448 512" fill="currentColor"><path d="M304 128a80 80 0 1 0 -160 0 80 80 0 1 0 160 0zM96 128a128 128 0 1 1 256 0A128 128 0 1 1 96 128zM49.3 464l349.5 0c-8.9-63.3-63.3-112-129-112l-91.4 0c-65.7 0-120.1 48.7-129 112zM0 482.3C0 383.8 79.8 304 178.3 304l91.4 0C368.2 304 448 383.8 448 482.3c0 16.4-13.3 29.7-29.7 29.7L29.7 512C13.3 512 0 498.7 0 482.3z"/></svg>`,
        check: `<svg aria-hidden="true" viewBox="0 0 448 512" fill="currentColor"><path d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z"/></svg>`,
        chevronLeft: `<svg aria-hidden="true" viewBox="0 0 320 512" fill="currentColor"><path d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l192 192c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256 246.6 86.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-192 192z"/></svg>`,
        chevronRight: `<svg aria-hidden="true" viewBox="0 0 320 512" fill="currentColor"><path d="M310.6 233.4c12.5 12.5 12.5 32.8 0 45.3l-192 192c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L242.7 256 73.4 86.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l192 192z"/></svg>`,

        // Added Extra Icons (Available for use)
        clock: `<svg aria-hidden="true" viewBox="0 0 512 512" fill="currentColor"><path d="M464 256A208 208 0 1 1 48 256a208 208 0 1 1 416 0zM0 256a256 256 0 1 0 512 0A256 256 0 1 0 0 256zM232 120l0 136c0 8 4 15.5 10.7 20l96 64c11 7.4 25.9 4.4 33.3-6.7s4.4-25.9-6.7-33.3L280 243.2 280 120c0-13.3-10.7-24-24-24s-24 10.7-24 24z"/></svg>`,
        success: `<svg aria-hidden="true" viewBox="0 0 512 512" fill="currentColor"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM369 209c9.4-9.4 9.4-24.6 0-33.9s-24.6-9.4-33.9 0l-111 111-47-47c-9.4-9.4-24.6-9.4-33.9 0s-9.4 24.6 0 33.9l64 64c9.4 9.4 24.6 9.4 33.9 0L369 209z"/></svg>`,
        waitlist: `<svg aria-hidden="true" viewBox="0 0 384 512" fill="currentColor"><path d="M32 0C14.3 0 0 14.3 0 32S14.3 64 32 64l0 11c0 42.4 16.9 83.1 46.9 113.1L146.7 256 78.9 323.9C48.9 353.9 32 394.6 32 437l0 11c-17.7 0-32 14.3-32 32s14.3 32 32 32l32 0 256 0 32 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l0-11c0-42.4-16.9-83.1-46.9-113.1L237.3 256l67.9-67.9c30-30 46.9-70.7 46.9-113.1l0-11c17.7 0 32-14.3 32-32s-14.3-32-32-32L320 0 64 0 32 0zM96 75l0-11 192 0 0 11c0 19-5.6 37.4-16 53L112 128c-10.3-15.6-16-34-16-53zm16 309c3.5-5.3 7.6-10.3 12.1-14.9L192 301.3l67.9 67.9c4.6 4.6 8.6 9.6 12.1 14.9L112 384z"/></svg>`,
        info: `<svg aria-hidden="true" viewBox="0 0 512 512" fill="currentColor"><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM216 336l24 0 0-64-24 0c-13.3 0-24-10.7-24-24s10.7-24 24-24l48 0c13.3 0 24 10.7 24 24l0 88 8 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-80 0c-13.3 0-24-10.7-24-24s10.7-24 24-24zm40-208a32 32 0 1 1 0 64 32 32 0 1 1 0-64z"/></svg>`,
        error: `<svg aria-hidden="true" viewBox="0 0 512 512" fill="currentColor"><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zm0-384c13.3 0 24 10.7 24 24l0 112c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-112c0-13.3 10.7-24 24-24zM224 352a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg>`,
        comment: `<svg aria-hidden="true" viewBox="0 0 512 512" fill="currentColor"><path d="M123.6 391.3c12.9-9.4 29.6-11.8 44.6-6.4c26.5 9.6 56.2 15.1 87.8 15.1c124.7 0 208-80.5 208-160s-83.3-160-208-160S48 160.5 48 240c0 32 12.4 62.8 35.7 89.2c8.6 9.7 12.8 22.5 11.8 35.5c-1.4 18.1-5.7 34.7-11.3 49.4c17-7.9 31.1-16.7 39.4-22.7zM21.2 431.9c1.8-2.7 3.5-5.4 5.1-8.1c10-16.6 19.5-38.4 21.4-62.9C17.7 326.8 0 285.1 0 240C0 125.1 114.6 32 256 32s256 93.1 256 208s-114.6 208-256 208c-37.1 0-72.3-6.4-104.1-17.9c-11.9 8.7-31.3 20.6-54.3 30.6c-15.1 6.6-32.3 12.6-50.1 16.1c-.8 .2-1.6 .3-2.4 .5c-4.4 .8-8.7 1.5-13.2 1.9c-.2 0-.5 .1-.7 .1c-5.1 .5-10.2 .8-15.3 .8c-6.5 0-12.3-3.9-14.8-9.9c-2.5-6-1.1-12.8 3.4-17.4c4.1-4.2 7.8-8.7 11.3-13.5c1.7-2.3 3.3-4.6 4.8-6.9l.3-.5z"/></svg>`,
    },

    // ========================================
    // INITIALIZATION
    // ========================================
    init() {
        // Load config from WordPress
        if (typeof osbConfig !== 'undefined') {
            this.state.config = osbConfig;
            this.state.labels = osbConfig.labels || {};
        } else if (typeof osbData !== 'undefined') {
            // Fallback for older localized data
            this.state.config = osbData;
        } else {
            console.error('OSB V3: osbConfig not found');
            return;
        }

        // Find widget container
        this.container = document.querySelector('.booking-widget');
        if (!this.container) {
            console.error('OSB V3: .booking-widget not found');
            return;
        }

        // Setup event delegation
        this.setupEventListeners();

        // Check for URL actions (reschedule/cancel)
        this.checkUrlActions();

        // Parse services from DOM or load via API
        this.loadServices();

        // FIX: Load saved user data from localStorage for returning users
        this.loadSavedUserData();

        // Initial render
        this.renderStep(1);
    },

    setupEventListeners() {
        // Single delegated click handler
        this.container.addEventListener('click', (e) => {
            const target = e.target.closest('[data-action]');
            if (!target) return;

            const action = target.dataset.action;
            const data = target.dataset;

            switch (action) {
                case 'select-service':
                    this.selectService(parseInt(data.serviceId));
                    break;
                case 'prev-month':
                    this.changeMonth(-1);
                    break;
                case 'next-month':
                    this.changeMonth(1);
                    break;
                case 'select-date':
                    this.selectDate(data.date);
                    break;
                case 'select-time':
                    this.selectTime(data.time);
                    break;
                case 'join-waitlist':
                    this.confirmWaitlist();
                    break;
                case 'show-waitlist':
                    this.showWaitlistForCurrentDate();
                    break;
                case 'prev-step':
                    this.prevStep();
                    break;
                case 'next-step':
                    this.nextStep();
                    break;
                case 'submit-booking':
                    this.submitBooking();
                    break;
                case 'go-to-step':
                    this.goToStep(parseInt(data.step));
                    break;
                case 'start-over':
                    this.startOver();
                    break;
            }
        });

        // Form input handler (for two-way binding)
        this.container.addEventListener('input', (e) => {
            const target = e.target;
            const name = target.name;

            if (name && this.state.formData.hasOwnProperty(name)) {
                this.state.formData[name] = target.type === 'checkbox' ? target.checked : target.value;
            }

            if (name === 'reminderPreference') {
                this.state.reminderPreference = target.value;
            }

            if (name === 'waitTimeFrom') {
                this.state.waitTimeFrom = target.value;
            }

            if (name === 'waitTimeTo') {
                this.state.waitTimeTo = target.value;
            }
        });
    },

    // ========================================
    // HELPERS
    // ========================================

    /**
     * Get translated label with optional placeholder replacement.
     */
    getLabel(key, replacements = {}) {
        let text = this.state.labels[key] || key;
        for (const [placeholder, value] of Object.entries(replacements)) {
            text = text.replace(`{${placeholder}}`, this.escapeHtml(String(value)));
        }
        return text;
    },

    /**
     * Escape HTML to prevent XSS.
     */
    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    /**
     * Create element helper (XSS-safe).
     */
    el(tag, attrs = {}, textContent = null) {
        const elem = document.createElement(tag);
        for (const [key, value] of Object.entries(attrs)) {
            if (key === 'className') {
                elem.className = value;
            } else if (key.startsWith('data-')) {
                elem.setAttribute(key, value);
            } else {
                elem.setAttribute(key, value);
            }
        }
        if (textContent !== null) {
            elem.textContent = textContent;
        }
        return elem;
    },

    /**
     * Parse DD.MM.YYYY to Date object (Safari-safe).
     * CRITICAL: Do NOT use Date.parse() or new Date(str).
     */
    parseDate(dateStr) {
        const parts = dateStr.split('.');
        if (parts.length !== 3) return null;
        const [day, month, year] = parts;
        return new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
    },

    /**
     * Format Date to YYYY-MM-DD for API.
     */
    formatDateForAPI(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    },

    /**
     * Format Date to DD.MM.YYYY for display.
     */
    formatDateForDisplay(date) {
        const d = String(date.getDate()).padStart(2, '0');
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const y = date.getFullYear();
        return `${d}.${m}.${y}`;
    },

    /**
     * Format YYYY-MM-DD to DD.MM.YYYY.
     */
    formatAPIDateForDisplay(apiDate) {
        const [y, m, d] = apiDate.split('-');
        return `${d}.${m}.${y}`;
    },

    showLoading() {
        this.state.loading = true;
        const overlay = this.container.querySelector('.loading-overlay');
        if (overlay) overlay.classList.remove('hidden');
    },

    hideLoading() {
        this.state.loading = false;
        const overlay = this.container.querySelector('.loading-overlay');
        if (overlay) overlay.classList.add('hidden');
    },

    /**
     * Show error message to user (instead of silent failure).
     */
    showError(message, container = null) {
        const target = container || this.container.querySelector('.step-content-area');
        if (!target) return;

        // Remove any existing error
        const existing = target.querySelector('.osb-error-alert');
        if (existing) existing.remove();

        // Create error alert
        const alert = this.el('div', { className: 'osb-error-alert alert alert-danger mb-3' });
        alert.textContent = message;

        // Insert at top of container
        target.insertBefore(alert, target.firstChild);
    },

    /**
     * Clear error messages.
     */
    clearError() {
        const errors = this.container.querySelectorAll('.osb-error-alert');
        errors.forEach(e => e.remove());
    },

    // ========================================
    // LOCAL STORAGE (FIX: Returning user persistence)
    // ========================================
    loadSavedUserData() {
        try {
            const saved = localStorage.getItem('osb_user_data');
            if (saved) {
                const data = JSON.parse(saved);
                // Only load basic contact info, not booking specifics
                if (data.firstName) this.state.formData.firstName = data.firstName;
                if (data.lastName) this.state.formData.lastName = data.lastName;
                if (data.email) this.state.formData.email = data.email;
                if (data.phone) this.state.formData.phone = data.phone;
                if (data.salutation) this.state.formData.salutation = data.salutation;
                console.log('OSB V3: Loaded saved user data');
            }
        } catch (e) {
            console.warn('OSB V3: Failed to load saved user data', e);
        }
    },

    saveUserData() {
        try {
            const data = {
                firstName: this.state.formData.firstName,
                lastName: this.state.formData.lastName,
                email: this.state.formData.email,
                phone: this.state.formData.phone,
                salutation: this.state.formData.salutation,
            };
            localStorage.setItem('osb_user_data', JSON.stringify(data));
            console.log('OSB V3: Saved user data to localStorage');
        } catch (e) {
            console.warn('OSB V3: Failed to save user data', e);
        }
    },

    // ========================================
    // SERVICES
    // ========================================
    services: [],

    loadServices() {
        // Parse services from DOM (existing cards) or fetch from API
        const cards = this.container.querySelectorAll('[data-service-id]');
        if (cards.length > 0) {
            this.services = Array.from(cards).map(card => ({
                id: parseInt(card.dataset.serviceId),
                name: card.dataset.serviceName || '',
                duration: parseInt(card.dataset.serviceDuration) || 60,
                price: card.dataset.servicePrice || '',
                image: card.dataset.serviceImage || '',
                description: card.dataset.serviceDescription || '',
            }));
        } else {
            // Fetch from API if no DOM services
            this.fetchServices();
        }
    },

    async fetchServices() {
        this.showLoading();
        try {
            const response = await fetch(`${this.state.config.apiUrl}services`, {
                headers: { 'X-WP-Nonce': this.state.config.nonce }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            this.services = data.map(s => ({
                id: s.id,
                name: s.name,
                duration: s.duration,
                price: s.price_range || s.price || '',
                image: s.image_url || '',
                description: s.description || '',
            }));
            this.renderStep(1);
        } catch (err) {
            console.error('OSB V3: Failed to load services', err);
            // FIX: Show user-visible error instead of silent failure
            const contentArea = this.container.querySelector('.step-content-area');
            if (contentArea) {
                contentArea.innerHTML = '';
                this.showError('Die Behandlungen konnten nicht geladen werden. Bitte versuche es später erneut.', contentArea);
            }
        } finally {
            this.hideLoading();
        }
    },

    selectService(serviceId) {
        // FIX: Use loose equality to handle string/number ID mismatch
        const service = this.services.find(s => s.id == serviceId);
        if (service) {
            // FIX: Reset calendar/time state when service changes
            this.state.selectedDate = null;
            this.state.selectedTime = null;
            this.state.monthlyAvailability = {};
            this.state.daySlots = [];
            this.state.isWaitlist = false;
            this.state.mode = 'booking';

            this.state.selectedService = service;
            // Visually update cards
            this.container.querySelectorAll('.service-card').forEach(card => {
                const cardId = card.dataset.serviceId;
                card.classList.toggle('selected', cardId == serviceId);
            });

            // FIX: Re-render footer to enable/disable Next button based on selection
            this.renderFooter();
        }
    },

    // ========================================
    // NAVIGATION
    // ========================================
    goToStep(num) {
        if (num < 1 || num > 4) return;
        if (num > this.state.step + 1) return; // Can't skip ahead
        this.state.step = num;
        this.renderStep(num);
    },

    prevStep() {
        if (this.state.step > 1) {
            this.goToStep(this.state.step - 1);
        }
    },

    nextStep() {
        // Validate before proceeding
        if (!this.validateCurrentStep()) return;

        if (this.state.step < 4) {
            this.goToStep(this.state.step + 1);
        }
    },

    validateCurrentStep() {
        const { step, selectedService, selectedDate, selectedTime, isWaitlist, waitTimeFrom, waitTimeTo, formData } = this.state;

        if (step === 1 && !selectedService) {
            alert(this.getLabel('error_required'));
            return false;
        }

        if (step === 2) {
            if (!selectedDate) {
                alert(this.getLabel('error_required'));
                return false;
            }
            if (!isWaitlist && !selectedTime) {
                alert(this.getLabel('error_required'));
                return false;
            }
            if (isWaitlist && (!waitTimeFrom || !waitTimeTo || waitTimeFrom >= waitTimeTo)) {
                alert('Bitte wähle einen gültigen Zeitraum.');
                return false;
            }
        }

        if (step === 3) {
            if (!formData.firstName || !formData.lastName || !formData.email || !formData.phone) {
                alert(this.getLabel('error_required'));
                return false;
            }
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(formData.email)) {
                alert(this.getLabel('error_email'));
                return false;
            }
        }

        return true;
    },

    // ========================================
    // RENDERING
    // ========================================
    renderStep(num) {
        this.state.step = num;
        this.updateProgressIndicators();

        const contentArea = this.container.querySelector('.step-content-area');
        if (!contentArea) return;

        // Clear and render
        contentArea.innerHTML = '';

        switch (num) {
            case 1:
                this.renderStep1Services(contentArea);
                break;
            case 2:
                this.renderStep2Calendar(contentArea);
                break;
            case 3:
                this.renderStep3Form(contentArea);
                break;
            case 4:
                this.renderStep4Confirmation(contentArea);
                break;
        }

        // Focus management for accessibility
        contentArea.setAttribute('tabindex', '-1');
        contentArea.focus();

        // Update footer buttons
        this.renderFooter();
    },

    updateProgressIndicators() {
        const indicators = this.container.querySelectorAll('.step-indicator');
        indicators.forEach((ind, idx) => {
            const stepNum = idx + 1;
            ind.classList.remove('active', 'completed');

            if (stepNum === this.state.step) {
                ind.classList.add('active');
            } else if (stepNum < this.state.step) {
                ind.classList.add('completed');
            }
        });
    },

    // --- Step 1: Services ---
    renderStep1Services(container) {
        const title = this.el('h3', { className: 'text-center mb-4' }, this.getLabel('select_service_title'));
        container.appendChild(title);

        const serviceList = this.el('div', { className: 'service-list-container' });

        this.services.forEach(service => {
            const card = this.el('div', {
                className: `service-card ${this.state.selectedService?.id === service.id ? 'selected' : ''}`,
                'data-action': 'select-service',
                'data-service-id': service.id,
            });

            // Image
            if (service.image) {
                const img = this.el('img', {
                    className: 'service-img',
                    src: service.image,
                    alt: service.name,
                });
                card.appendChild(img);
            }

            // Info
            const info = this.el('div', { className: 'service-info' });
            const nameEl = this.el('h5', { className: 'mb-2' }, service.name);
            info.appendChild(nameEl);

            if (service.description) {
                const desc = this.el('p', { className: 'service-desc mb-0' }, service.description);
                info.appendChild(desc);
            }

            card.appendChild(info);

            // Meta
            const meta = this.el('div', { className: 'service-meta-block' });
            const duration = this.el('div', { className: 'service-duration-main' }, `${service.duration} Min`);
            meta.appendChild(duration);

            if (service.price) {
                const price = this.el('div', { className: 'service-cost-sub' }, service.price);
                meta.appendChild(price);
            }

            card.appendChild(meta);
            serviceList.appendChild(card);
        });

        container.appendChild(serviceList);
    },

    // --- Step 2: Calendar ---
    renderStep2Calendar(container) {
        const title = this.el('h3', { className: 'text-center mb-4' }, this.getLabel('select_date_title'));
        container.appendChild(title);

        // Calendar card
        const calendarCard = this.el('div', { className: 'calendar-card' });
        calendarCard.id = 'calendarContainer';
        container.appendChild(calendarCard);

        // Time slots container
        const slotsContainer = this.el('div', { className: 'time-slots-container' });
        slotsContainer.id = 'timeSlotsContainer';
        container.appendChild(slotsContainer);

        // Render calendar grid
        this.renderCalendarGrid();

        // Fetch availability for current month
        const date = this.state.currentMonth;
        this.fetchMonthlyAvailability(date.getFullYear(), date.getMonth() + 1);
    },

    renderCalendarGrid() {
        const calendarCard = this.container.querySelector('#calendarContainer');
        if (!calendarCard) return;

        const date = this.state.currentMonth;
        const year = date.getFullYear();
        const month = date.getMonth();

        // Header
        const header = this.el('div', { className: 'calendar-header' });

        const prevBtn = this.el('button', { className: 'calendar-nav-btn', 'data-action': 'prev-month' });
        prevBtn.innerHTML = this.icons.chevronLeft;

        const monthTitle = this.el('span', { className: 'calendar-month-title' });
        const monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        monthTitle.textContent = `${monthNames[month]} ${year}`;

        const nextBtn = this.el('button', { className: 'calendar-nav-btn', 'data-action': 'next-month' });
        nextBtn.innerHTML = this.icons.chevronRight;

        header.appendChild(prevBtn);
        header.appendChild(monthTitle);
        header.appendChild(nextBtn);

        // Grid
        const grid = this.el('div', { className: 'calendar-grid' });

        // Day names
        const dayNames = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
        dayNames.forEach(name => {
            const nameEl = this.el('div', { className: 'day-name' }, name);
            grid.appendChild(nameEl);
        });

        // Calculate days
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startOffset = (firstDay.getDay() + 6) % 7; // Monday = 0
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Empty cells for offset
        for (let i = 0; i < startOffset; i++) {
            grid.appendChild(this.el('div', { className: 'calendar-day disabled' }));
        }

        // Day cells
        for (let d = 1; d <= lastDay.getDate(); d++) {
            const cellDate = new Date(year, month, d);
            const dateStr = this.formatDateForAPI(cellDate);
            const availability = this.state.monthlyAvailability[dateStr];

            const dayEl = this.el('div', {
                className: 'calendar-day',
                'data-action': 'select-date',
                'data-date': dateStr,
            }, String(d));

            // Past days
            if (cellDate < today) {
                dayEl.className = 'calendar-day disabled';
                dayEl.removeAttribute('data-action');
            } else if (availability === 'available') {
                dayEl.classList.add('available');
            } else if (availability === 'booked') {
                dayEl.classList.add('booked');
            }

            // Selected
            if (this.state.selectedDate === dateStr) {
                dayEl.classList.add('selected');
            }

            grid.appendChild(dayEl);
        }

        // Clear and render
        calendarCard.innerHTML = '';
        calendarCard.appendChild(header);
        calendarCard.appendChild(grid);
    },

    async fetchMonthlyAvailability(year, month) {
        this.showLoading();

        // FIX: Track request to prevent race condition (same pattern as slots)
        const requestId = Date.now();
        this.state._lastAvailabilityRequest = requestId;

        try {
            const serviceId = this.state.selectedService?.id;
            const response = await fetch(
                `${this.state.config.apiUrl}availability?year=${year}&month=${month}&service_id=${serviceId}`,
                { headers: { 'X-WP-Nonce': this.state.config.nonce } }
            );

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            // FIX: Only update state if this is still the latest request
            if (this.state._lastAvailabilityRequest !== requestId) {
                return; // Stale response, ignore
            }

            // Merge into state
            if (data.dates) {
                Object.assign(this.state.monthlyAvailability, data.dates);
            }

            // Re-render calendar with availability
            this.renderCalendarGrid();
        } catch (err) {
            console.error('OSB V3: Failed to fetch availability', err);
            // FIX: Show user-visible error
            const calendarCard = this.container.querySelector('#calendarContainer');
            if (calendarCard) {
                this.showError('Verfügbarkeit konnte nicht geladen werden.', calendarCard);
            }
        } finally {
            this.hideLoading();
        }
    },

    changeMonth(delta) {
        const date = this.state.currentMonth;
        date.setMonth(date.getMonth() + delta);
        this.state.currentMonth = new Date(date);
        this.renderCalendarGrid();
        this.fetchMonthlyAvailability(date.getFullYear(), date.getMonth() + 1);
    },

    selectDate(dateStr) {
        this.state.selectedDate = dateStr;
        this.state.selectedTime = null;

        // Check if booked → show waitlist
        const availability = this.state.monthlyAvailability[dateStr];
        if (availability === 'booked') {
            this.state.isWaitlist = true;
            this.state.mode = 'waitlist';
            this.renderWaitlistForm();
            // FIX: Scroll to waitlist form for mobile visibility
            setTimeout(() => {
                const slotsContainer = this.container.querySelector('#timeSlotsContainer');
                if (slotsContainer) slotsContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 100);
        } else {
            this.state.isWaitlist = false;
            this.state.mode = 'booking';
            this.fetchTimeSlots(dateStr);
        }

        // Update calendar visual
        this.container.querySelectorAll('.calendar-day').forEach(day => {
            day.classList.toggle('selected', day.dataset.date === dateStr);
        });

        // FIX: Re-render footer to update Next button state
        this.renderFooter();
    },

    async fetchTimeSlots(dateStr) {
        this.showLoading();
        const slotsContainer = this.container.querySelector('#timeSlotsContainer');
        if (slotsContainer) slotsContainer.innerHTML = '';

        // FIX: Track request to prevent race condition
        const requestId = Date.now();
        this.state._lastSlotsRequest = requestId;

        try {
            const serviceId = this.state.selectedService?.id;
            const response = await fetch(
                `${this.state.config.apiUrl}timeslots?date=${dateStr}&service_id=${serviceId}`,
                { headers: { 'X-WP-Nonce': this.state.config.nonce } }
            );
            const data = await response.json();

            // FIX: Only update state if this is still the latest request
            if (this.state._lastSlotsRequest !== requestId) {
                return; // Stale response, ignore
            }

            this.state.daySlots = data.slots || [];
            this.renderTimeSlots();
        } catch (err) {
            console.error('OSB V3: Failed to fetch time slots', err);
            // FIX: Show error to user
            if (slotsContainer) {
                slotsContainer.innerHTML = '';
                const errorMsg = this.el('p', { className: 'text-center text-danger' },
                    'Fehler beim Laden der Zeiten. Bitte versuche es erneut.');
                slotsContainer.appendChild(errorMsg);
            }
        } finally {
            this.hideLoading();
        }
    },

    renderTimeSlots() {
        const slotsContainer = this.container.querySelector('#timeSlotsContainer');
        if (!slotsContainer) return;

        slotsContainer.innerHTML = '';

        if (this.state.daySlots.length === 0) {
            const msg = this.el('p', { className: 'text-center text-muted' }, 'Keine Zeiten verfügbar');
            slotsContainer.appendChild(msg);

            // FIX: Add waitlist option when no slots available
            const waitlistBtn = this.el('button', {
                className: 'btn btn-outline-os mt-3 d-block mx-auto',
                'data-action': 'show-waitlist',
            }, 'Auf Warteliste setzen');
            slotsContainer.appendChild(waitlistBtn);
            return;
        }

        const grid = this.el('div', { className: 'time-slots' });

        this.state.daySlots.forEach(slot => {
            const slotEl = this.el('div', {
                className: `time-slot ${this.state.selectedTime === slot.time ? 'selected' : ''}`,
                'data-action': 'select-time',
                'data-time': slot.time,
            }, slot.time);
            grid.appendChild(slotEl);
        });

        slotsContainer.appendChild(grid);

        // FIX: Scroll to time slots for mobile visibility
        setTimeout(() => {
            slotsContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    },

    selectTime(time) {
        this.state.selectedTime = time;

        // Update visual selection
        this.container.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.toggle('selected', slot.dataset.time === time);
        });

        // FIX: Re-render footer to enable Next button
        this.renderFooter();
    },

    renderWaitlistForm() {
        const slotsContainer = this.container.querySelector('#timeSlotsContainer');
        if (!slotsContainer) return;

        slotsContainer.innerHTML = '';

        const wrapper = this.el('div', { className: 'waitlist-container' });

        const info = this.el('p', { className: 'waitlist-info' });
        info.textContent = 'Dieser Tag ist bereits ausgebucht. Du kannst dich auf die Warteliste setzen lassen.';
        wrapper.appendChild(info);

        const rangeRow = this.el('div', { className: 'waitlist-time-range' });

        // From select
        const fromLabel = this.el('label', {}, this.getLabel('time_range_from'));
        rangeRow.appendChild(fromLabel);

        const fromSelect = this.el('select', { name: 'waitTimeFrom', className: 'form-select' });
        this.generateTimeOptions(fromSelect, this.state.waitTimeFrom);
        rangeRow.appendChild(fromSelect);

        // To select
        const toLabel = this.el('label', {}, this.getLabel('time_range_to'));
        rangeRow.appendChild(toLabel);

        const toSelect = this.el('select', { name: 'waitTimeTo', className: 'form-select' });
        this.generateTimeOptions(toSelect, this.state.waitTimeTo);
        rangeRow.appendChild(toSelect);

        wrapper.appendChild(rangeRow);

        // Join button
        const btn = this.el('button', {
            className: 'btn btn-nav btn-primary-os mt-3',
            'data-action': 'join-waitlist',
        }, this.getLabel('join_waitlist'));
        wrapper.appendChild(btn);

        slotsContainer.appendChild(wrapper);
    },

    /**
     * Show waitlist form for current selected date (when slots are empty).
     */
    showWaitlistForCurrentDate() {
        this.state.isWaitlist = true;
        this.state.mode = 'waitlist';
        this.renderWaitlistForm();
    },

    /**
     * Reset state and start a new booking.
     */
    startOver() {
        // Reset all state to initial values
        this.state.step = 1;
        this.state.mode = 'booking';
        this.state.selectedService = null;
        this.state.selectedDate = null;
        this.state.selectedTime = null;
        this.state.isWaitlist = false;
        this.state.waitTimeFrom = '';
        this.state.waitTimeTo = '';
        this.state.reminderPreference = 'none';
        this.state.formData = {
            salutation: 'n',
            firstName: '',
            lastName: '',
            email: '',
            phone: '',
            notes: '',
            newsletter: false,
        };
        this.state.currentMonth = new Date();
        this.state.monthlyAvailability = {};
        this.state.daySlots = [];
        this.state.bookingSummary = null;
        this.state.originalBooking = null;

        // Re-render Step 1
        this.renderStep(1);
    },

    generateTimeOptions(select, selectedValue) {
        // FIX: Add placeholder option to force user choice
        const placeholder = this.el('option', { value: '', disabled: 'disabled' }, '--:--');
        if (!selectedValue) placeholder.selected = true;
        select.appendChild(placeholder);

        for (let h = 8; h <= 20; h++) {
            for (let m = 0; m < 60; m += 30) {
                const time = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
                const option = this.el('option', { value: time }, time);
                if (time === selectedValue) option.selected = true;
                select.appendChild(option);
            }
        }
    },

    confirmWaitlist() {
        // Validate time range
        if (this.state.waitTimeFrom >= this.state.waitTimeTo) {
            alert('Bitte wähle einen gültigen Zeitraum (Von muss vor Bis liegen).');
            return;
        }
        this.nextStep();
    },

    // --- Step 3: Form ---
    renderStep3Form(container) {
        const title = this.el('h3', { className: 'text-center mb-4' }, this.getLabel('your_details'));
        container.appendChild(title);

        // Summary card
        const summary = this.el('div', { className: 'form-section-card' });
        const summaryTitle = this.el('div', { className: 'form-section-title' }, 'Deine Buchung');
        summary.appendChild(summaryTitle);

        const addSummaryRow = (label, value) => {
            const row = this.el('div', { className: 'summary-item-row' });
            row.appendChild(this.el('span', {}, label));
            row.appendChild(this.el('span', { className: 'text-end fw-medium' }, value));
            summary.appendChild(row);
        };

        addSummaryRow('Behandlung', this.state.selectedService?.name || '');
        addSummaryRow('Datum', this.formatAPIDateForDisplay(this.state.selectedDate));
        if (this.state.isWaitlist) {
            addSummaryRow('Zeitraum', `${this.state.waitTimeFrom} - ${this.state.waitTimeTo}`);
            addSummaryRow('Status', 'Warteliste');
        } else {
            addSummaryRow('Zeit', this.state.selectedTime);
        }

        container.appendChild(summary);

        // Contact form
        const form = this.el('div', { className: 'form-section-card' });
        const formTitle = this.el('div', { className: 'form-section-title' }, this.getLabel('your_details'));
        form.appendChild(formTitle);

        // Salutation
        const salRow = this.el('div', { className: 'mb-3' });
        const salLabel = this.el('label', { className: 'form-label' }, this.getLabel('salutation'));
        salRow.appendChild(salLabel);

        const salSelect = this.el('select', { name: 'salutation', className: 'form-select' });
        [
            { value: 'n', label: this.getLabel('salutation_none') },
            { value: 'm', label: this.getLabel('salutation_mr') },
            { value: 'w', label: this.getLabel('salutation_mrs') },
        ].forEach(opt => {
            const option = this.el('option', { value: opt.value }, opt.label);
            if (this.state.formData.salutation === opt.value) option.selected = true;
            salSelect.appendChild(option);
        });
        salRow.appendChild(salSelect);
        form.appendChild(salRow);

        // Name row
        const nameRow = this.el('div', { className: 'row mb-3' });

        const fnCol = this.el('div', { className: 'col-md-6' });
        fnCol.appendChild(this.el('label', { className: 'form-label' }, this.getLabel('first_name') + ' *'));
        const fnInput = this.el('input', {
            type: 'text',
            name: 'firstName',
            className: 'form-control',
            value: this.state.formData.firstName,
            required: 'required',
        });
        fnCol.appendChild(fnInput);
        nameRow.appendChild(fnCol);

        const lnCol = this.el('div', { className: 'col-md-6' });
        lnCol.appendChild(this.el('label', { className: 'form-label' }, this.getLabel('last_name') + ' *'));
        const lnInput = this.el('input', {
            type: 'text',
            name: 'lastName',
            className: 'form-control',
            value: this.state.formData.lastName,
            required: 'required',
        });
        lnCol.appendChild(lnInput);
        nameRow.appendChild(lnCol);

        form.appendChild(nameRow);

        // Email
        const emailRow = this.el('div', { className: 'mb-3' });
        emailRow.appendChild(this.el('label', { className: 'form-label' }, this.getLabel('email') + ' *'));
        const emailInput = this.el('input', {
            type: 'email',
            name: 'email',
            className: 'form-control',
            value: this.state.formData.email,
            required: 'required',
        });
        emailRow.appendChild(emailInput);
        form.appendChild(emailRow);

        // Phone (required)
        const phoneRow = this.el('div', { className: 'mb-3' });
        phoneRow.appendChild(this.el('label', { className: 'form-label' }, this.getLabel('phone') + ' *'));
        const phoneInput = this.el('input', {
            type: 'tel',
            name: 'phone',
            className: 'form-control',
            value: this.state.formData.phone,
            required: 'required',
        });
        phoneRow.appendChild(phoneInput);
        form.appendChild(phoneRow);

        // Notes
        const notesRow = this.el('div', { className: 'mb-3' });
        notesRow.appendChild(this.el('label', { className: 'form-label' }, this.getLabel('notes')));
        const notesInput = this.el('textarea', {
            name: 'notes',
            className: 'form-control',
            rows: '3',
            placeholder: this.getLabel('notes_placeholder'),
        });
        notesInput.value = this.state.formData.notes;
        notesRow.appendChild(notesInput);
        form.appendChild(notesRow);

        // Reminder preference
        const reminderRow = this.el('div', { className: 'mb-3' });
        reminderRow.appendChild(this.el('label', { className: 'form-label' }, this.getLabel('reminder_preference')));
        const reminderSelect = this.el('select', { name: 'reminderPreference', className: 'form-select' });
        [
            { value: 'none', label: this.getLabel('reminder_none') },
            { value: '24h', label: this.getLabel('reminder_24h') },
            { value: '48h', label: this.getLabel('reminder_48h') },
        ].forEach(opt => {
            const option = this.el('option', { value: opt.value }, opt.label);
            if (this.state.reminderPreference === opt.value) option.selected = true;
            reminderSelect.appendChild(option);
        });
        reminderRow.appendChild(reminderSelect);
        form.appendChild(reminderRow);

        // Newsletter
        const newsletterRow = this.el('div', { className: 'form-check mb-3' });
        const nlInput = this.el('input', {
            type: 'checkbox',
            name: 'newsletter',
            className: 'form-check-input',
            id: 'newsletterCheck',
        });
        if (this.state.formData.newsletter) nlInput.checked = true;
        newsletterRow.appendChild(nlInput);
        const nlLabel = this.el('label', { className: 'form-check-label', for: 'newsletterCheck' }, this.getLabel('newsletter_opt_in'));
        newsletterRow.appendChild(nlLabel);
        form.appendChild(newsletterRow);

        container.appendChild(form);
    },

    // --- Step 4: Confirmation ---
    renderStep4Confirmation(container) {
        const summary = this.state.bookingSummary;

        const title = this.el('h3', { className: 'text-center mb-4' }, this.getLabel('confirmation_title'));
        container.appendChild(title);

        const msg = this.el('p', { className: 'text-center mb-4' });
        if (this.state.isWaitlist) {
            msg.textContent = this.getLabel('waitlist_confirmation');
        } else {
            msg.textContent = this.getLabel('confirmation_message');
        }
        container.appendChild(msg);

        // Summary card
        const card = this.el('div', { className: 'summary-card' });

        const addRow = (label, value) => {
            const row = this.el('div', { className: 'summary-row' });
            row.appendChild(this.el('span', {}, label));
            row.appendChild(this.el('span', { className: 'fw-medium' }, value));
            card.appendChild(row);
        };

        addRow('Behandlung', this.state.selectedService?.name || '');
        addRow('Datum', this.formatAPIDateForDisplay(this.state.selectedDate));

        if (this.state.isWaitlist) {
            addRow('Zeitraum', `${this.state.waitTimeFrom} - ${this.state.waitTimeTo}`);
        } else {
            addRow('Zeit', this.state.selectedTime);
        }

        addRow('Name', `${this.state.formData.firstName} ${this.state.formData.lastName}`);
        addRow('E-Mail', this.state.formData.email);

        if (this.state.formData.phone) {
            addRow('Telefon', this.state.formData.phone);
        }

        container.appendChild(card);

        // FIX: Add "Start Over" button
        const newBookingBtn = this.el('button', {
            className: 'btn btn-nav btn-outline-os mt-4 d-block mx-auto',
            'data-action': 'start-over',
        }, 'Neuen Termin buchen');
        container.appendChild(newBookingBtn);
    },

    renderFooter() {
        const footer = this.container.querySelector('.booking-footer');
        if (!footer) return;

        footer.innerHTML = '';

        const { step } = this.state;

        // Back button (steps 2-3)
        if (step > 1 && step < 4) {
            const backBtn = this.el('button', {
                className: 'btn btn-nav btn-outline-os',
                'data-action': 'prev-step',
            }, this.getLabel('btn_back'));
            footer.appendChild(backBtn);
        } else {
            footer.appendChild(this.el('div')); // Spacer
        }

        // Next/Submit button
        if (step === 1) {
            const nextBtn = this.el('button', {
                className: 'btn btn-nav btn-primary-os',
                'data-action': 'next-step',
            }, this.getLabel('btn_next'));
            if (!this.state.selectedService) nextBtn.disabled = true;
            footer.appendChild(nextBtn);
        } else if (step === 2) {
            const nextBtn = this.el('button', {
                className: 'btn btn-nav btn-primary-os',
                'data-action': 'next-step',
            }, this.getLabel('btn_next'));
            const canProceed = this.state.isWaitlist ?
                (this.state.waitTimeFrom && this.state.waitTimeTo) :
                (this.state.selectedDate && this.state.selectedTime);
            if (!canProceed) nextBtn.disabled = true;
            footer.appendChild(nextBtn);
        } else if (step === 3) {
            const submitBtn = this.el('button', {
                className: 'btn btn-nav btn-primary-os',
                'data-action': 'submit-booking',
            }, this.state.isWaitlist ? this.getLabel('btn_submit_waitlist') : this.getLabel('btn_submit'));
            footer.appendChild(submitBtn);
        } else if (step === 4) {
            // No button on confirmation, or "New Booking" button
        }
    },

    // ========================================
    // SUBMISSION
    // ========================================
    async submitBooking() {
        if (!this.validateCurrentStep()) return;

        this.showLoading();

        const { state } = this;
        const payload = {
            service_id: state.selectedService.id,
            date: state.selectedDate,
            time: state.selectedTime || '00:00',
            client_salutation: state.formData.salutation,
            client_first_name: state.formData.firstName,
            client_last_name: state.formData.lastName,
            client_name: `${state.formData.firstName} ${state.formData.lastName}`,
            client_email: state.formData.email,
            client_phone: state.formData.phone,
            client_notes: state.formData.notes,
            newsletter: state.formData.newsletter ? 1 : 0,
            reminder_preference: state.reminderPreference,
            language: state.config.language || 'de',
        };

        // Waitlist additions
        if (state.isWaitlist) {
            payload.type = 'waitlist';
            payload.wait_time_from = state.waitTimeFrom;
            payload.wait_time_to = state.waitTimeTo;
        }

        try {
            const response = await fetch(`${state.config.apiUrl}booking`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': state.config.nonce,
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!response.ok) {
                // Handle 409 Conflict (slot taken)
                if (response.status === 409) {
                    alert(this.getLabel('error_slot_taken'));
                    this.goToStep(2);
                    return;
                }
                throw new Error(data.message || 'Booking failed');
            }

            // Success
            state.bookingSummary = data;

            // FIX: Save user data to localStorage for returning users
            this.saveUserData();

            this.goToStep(4);

        } catch (err) {
            console.error('OSB V3: Booking error', err);
            alert('Ein Fehler ist aufgetreten. Bitte versuche es erneut.');
        } finally {
            this.hideLoading();
        }
    },

    // ========================================
    // URL ACTIONS (Reschedule/Cancel)
    // ========================================
    checkUrlActions() {
        const params = new URLSearchParams(window.location.search);
        const action = params.get('action');
        const token = params.get('token');

        if (!action || !token) return;

        if (action === 'cancel') {
            this.performCancellation(token);
        } else if (action === 'reschedule') {
            this.loadBookingForReschedule(token);
        }
    },

    async performCancellation(token) {
        if (!confirm('Möchtest du diesen Termin wirklich absagen?')) return;

        this.showLoading();
        try {
            const response = await fetch(`${this.state.config.apiUrl}cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.state.config.nonce,
                },
                body: JSON.stringify({ token }),
            });

            const data = await response.json();

            if (response.ok) {
                alert('Dein Termin wurde erfolgreich abgesagt.');
                window.location.href = this.state.config.bookingPageUrl;
            } else {
                alert(data.message || 'Absage fehlgeschlagen.');
            }
        } catch (err) {
            console.error('OSB V3: Cancellation error', err);
            alert('Ein Fehler ist aufgetreten.');
        } finally {
            this.hideLoading();
        }
    },

    async loadBookingForReschedule(token) {
        this.showLoading();
        try {
            const response = await fetch(`${this.state.config.apiUrl}booking?token=${token}`, {
                headers: { 'X-WP-Nonce': this.state.config.nonce },
            });

            const data = await response.json();

            if (!response.ok) {
                alert(data.message || 'Termin nicht gefunden.');
                this.hideLoading();
                return;
            }

            // Set mode and prefill
            this.state.mode = 'reschedule';
            this.state.originalBooking = data;

            // Set service (locked)
            const service = this.services.find(s => s.id === data.service_id);
            if (service) this.state.selectedService = service;

            // FIX: Set calendar to the month of the original booking
            if (data.start_time) {
                const bookingDate = new Date(data.start_time);
                this.state.currentMonth = new Date(bookingDate.getFullYear(), bookingDate.getMonth(), 1);
            }

            // Prefill form data
            this.state.formData = {
                salutation: data.client_salutation || 'n',
                firstName: data.client_first_name || '',
                lastName: data.client_last_name || '',
                email: data.client_email || '',
                phone: data.client_phone || '',
                notes: data.client_notes || '',
                newsletter: false,
            };

            // Skip to step 2 (calendar)
            this.goToStep(2);

        } catch (err) {
            console.error('OSB V3: Reschedule load error', err);
            alert('Ein Fehler ist aufgetreten.');
        } finally {
            this.hideLoading();
        }
    },
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => osbV3.init());
