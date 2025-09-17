/**
 * GA4 Funnel Tracking - Frontend JavaScript
 * 
 * Tracks booking funnel events: form view, date selection, time selection,
 * form submission, confirmation, and errors with deduplication.
 */

(function($) {
    'use strict';
    
    // Check if GA4 funnel tracking is enabled and dataLayer is available
    if (typeof rbfGA4Funnel === 'undefined' || typeof window.dataLayer === 'undefined') {
        return;
    }
    
    const FunnelTracker = {
        sessionId: rbfGA4Funnel.sessionId,
        measurementId: rbfGA4Funnel.measurementId,
        debug: rbfGA4Funnel.debug || false,
        trackedEvents: new Set(), // Prevent duplicate events
        clientId: null,
        clientIdRequestPending: false,
        
        /**
         * Log debug messages if debug mode is enabled
         */
        log: function(message, data = null) {
            if (this.debug && window.console) {
                console.log('RBF GA4 Funnel:', message, data || '');
            }
        },
        
        /**
         * Store detected GA4 client ID and optionally persist it for reuse
         */
        setClientId: function(clientId, source = 'unknown') {
            if (!clientId) {
                return;
            }

            if (this.clientId === clientId) {
                return;
            }

            this.clientId = clientId;
            this.log(`GA4 client ID detected (${source})`, clientId);

            try {
                const maxAge = 30 * 60; // 30 minutes
                document.cookie = `rbf_ga_client_id=${encodeURIComponent(clientId)}; path=/; max-age=${maxAge}`;
            } catch (cookieError) {
                this.log('Unable to persist GA4 client ID cookie', cookieError);
            }
        },

        /**
         * Build the GA4 measurement-specific cookie name
         */
        getMeasurementCookieName: function() {
            if (!this.measurementId) {
                return null;
            }

            return `_ga_${this.measurementId.replace(/^G-/, '').replace(/[^A-Z0-9_]/gi, '')}`;
        },

        /**
         * Extract GA4 client ID from cookie value
         */
        parseGaCookieValue: function(value) {
            if (!value) {
                return null;
            }

            const parts = value.split('.').filter(Boolean);
            if (parts.length >= 2) {
                const last = parts.length - 1;
                return `${parts[last - 1]}.${parts[last]}`;
            }

            return null;
        },

        /**
         * Attempt to read GA4 client ID from available cookies
         */
        getClientIdFromCookie: function() {
            if (typeof document === 'undefined' || !document.cookie) {
                return null;
            }

            const measurementCookie = this.getMeasurementCookieName();
            const cookies = document.cookie.split(';');
            let fallbackClientId = null;

            for (let i = 0; i < cookies.length; i++) {
                const cookie = cookies[i].trim();
                if (!cookie) {
                    continue;
                }

                const separatorIndex = cookie.indexOf('=');
                if (separatorIndex === -1) {
                    continue;
                }

                const name = cookie.substring(0, separatorIndex);
                const value = decodeURIComponent(cookie.substring(separatorIndex + 1));

                if (measurementCookie && name === measurementCookie) {
                    const parsed = this.parseGaCookieValue(value);
                    if (parsed) {
                        return parsed;
                    }
                }

                if (!fallbackClientId && name.indexOf('_ga') === 0) {
                    const parsedFallback = this.parseGaCookieValue(value);
                    if (parsedFallback) {
                        fallbackClientId = parsedFallback;
                    }
                }
            }

            return fallbackClientId;
        },

        /**
         * Ensure the GA4 client ID is available when tracking events
         */
        requestClientId: function(force = false) {
            if (!force && this.clientId) {
                return this.clientId;
            }

            const cookieClientId = this.getClientIdFromCookie();
            if (cookieClientId) {
                this.setClientId(cookieClientId, 'cookie');
            }

            if (typeof gtag === 'function' && this.measurementId && (!this.clientIdRequestPending || force)) {
                this.clientIdRequestPending = true;

                try {
                    gtag('get', this.measurementId, 'client_id', (clientId) => {
                        this.clientIdRequestPending = false;
                        if (clientId) {
                            this.setClientId(clientId, 'gtag');
                        } else if (!this.clientId) {
                            const fallback = this.getClientIdFromCookie();
                            if (fallback) {
                                this.setClientId(fallback, 'cookie_fallback');
                            }
                        }
                    });
                } catch (error) {
                    this.clientIdRequestPending = false;
                    this.log('Error retrieving GA4 client ID via gtag', error);
                }
            }

            return this.clientId;
        },

        /**
         * Generate unique event ID for deduplication
         */
        generateEventId: function(eventType) {
            const now = Date.now();
            const performanceNow = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Math.random() * 1000;
            const microseconds = String(performanceNow).replace('.', '').padEnd(16, '0').substr(0, 6);
            return `rbf_${eventType}_${this.sessionId}_${Math.floor(now / 1000)}_${microseconds}`;
        },
        
        /**
         * Track event via dataLayer and gtag (if available), and server-side (optional)
         */
        trackEvent: function(eventName, params = {}, options = {}) {
            this.requestClientId();

            const eventId = options.eventId || this.generateEventId(eventName);
            const dedupeKey = `${eventName}_${JSON.stringify(params)}`;
            
            // Prevent duplicate tracking of identical events
            if (this.trackedEvents.has(dedupeKey) && !options.allowDuplicate) {
                this.log(`Skipping duplicate event: ${eventName}`, params);
                return;
            }
            
            this.trackedEvents.add(dedupeKey);
            
            // Add common parameters
            const enhancedParams = {
                ...params,
                session_id: this.sessionId,
                event_id: eventId,
                page_url: window.location.href,
                page_title: document.title,
                timestamp: Math.floor(Date.now() / 1000),
                vertical: 'restaurant'
            };
            
            this.log(`Tracking event: ${eventName}`, enhancedParams);

            // Push to dataLayer (available even if gtag isn't)
            window.dataLayer.push({ event: eventName, ...enhancedParams });
            this.log(`Event pushed to dataLayer: ${eventName}`);

            // Then track via gtag if available and not in conflicting hybrid mode
            if (typeof gtag === 'function') {
                // Check if we should avoid direct gtag calls in hybrid GTM mode
                var isGtmHybrid = typeof rbfGA4Funnel.gtmHybrid !== 'undefined' ? rbfGA4Funnel.gtmHybrid : false;
                if (!isGtmHybrid || options.forceGtag) {
                    gtag('event', eventName, enhancedParams);
                    this.log(`Event sent to gtag: ${eventName}`);
                } else {
                    this.log(`Skipping gtag call in GTM hybrid mode: ${eventName}`);
                }
            }
            
            // Also send to server for Measurement Protocol (if configured)
            if (options.serverSide !== false) {
                const clientIdOverride = options.clientId || this.clientId;
                this.sendToServer(eventName, enhancedParams, eventId, clientIdOverride);
            }
        },
        
        /**
         * Send event to server for Measurement Protocol tracking
         */
        sendToServer: function(eventName, params, eventId, clientId) {
            // Check if jQuery and AJAX are available
            if (typeof $ === 'undefined' || typeof $.ajax !== 'function') {
                this.log(`Server tracking skipped - jQuery AJAX not available for: ${eventName}`);
                return;
            }
            
            $.ajax({
                url: rbfGA4Funnel.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rbf_track_ga4_event',
                    event_name: eventName,
                    event_params: params,
                    session_id: this.sessionId,
                    event_id: eventId,
                    client_id: clientId || '',
                    nonce: rbfGA4Funnel.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.log(`Server tracking successful for: ${eventName}`);
                    } else {
                        this.log(`Server tracking failed for: ${eventName}`, response.data);
                    }
                },
                error: (xhr, status, error) => {
                    this.log(`Server tracking error for: ${eventName}`, error);
                }
            });
        },
        
        /**
         * Track form view event
         */
        trackFormView: function() {
            this.trackEvent('form_view', {
                form_type: 'restaurant_booking',
                funnel_step: 1,
                step_name: 'form_view'
            });
        },
        
        /**
         * Track meal selection
         */
        trackMealSelection: function(mealType) {
            this.trackEvent('meal_selected', {
                meal_type: mealType,
                funnel_step: 2,
                step_name: 'meal_selection'
            });
        },
        
        /**
         * Track date selection
         */
        trackDateSelection: function(selectedDate, mealType) {
            this.trackEvent('date_selected', {
                selected_date: selectedDate,
                meal_type: mealType,
                funnel_step: 3,
                step_name: 'date_selection'
            });
        },
        
        /**
         * Track time selection
         */
        trackTimeSelection: function(selectedTime, selectedDate, mealType) {
            this.trackEvent('time_selected', {
                selected_time: selectedTime,
                selected_date: selectedDate,
                meal_type: mealType,
                funnel_step: 4,
                step_name: 'time_selection'
            });
        },
        
        /**
         * Track people count selection
         */
        trackPeopleSelection: function(peopleCount, mealType) {
            this.trackEvent('people_selected', {
                people_count: parseInt(peopleCount),
                meal_type: mealType,
                funnel_step: 5,
                step_name: 'people_selection'
            });
        },
        
        /**
         * Track form submission attempt
         */
        trackFormSubmission: function(formData) {
            this.trackEvent('form_submitted', {
                meal_type: formData.meal || '',
                people_count: parseInt(formData.people) || 1,
                has_phone: !!(formData.phone || '').trim(),
                has_notes: !!(formData.notes || '').trim(),
                marketing_consent: formData.marketing === 'yes',
                funnel_step: 6,
                step_name: 'form_submission'
            });
        },
        
        /**
         * Track booking confirmation
         */
        trackBookingConfirmation: function(bookingData = {}) {
            const responseData = bookingData || {};
            const eventParams = responseData.event_params ? { ...responseData.event_params } : {};

            const candidateClientId = responseData.client_id || eventParams.client_id;
            if (candidateClientId) {
                this.setClientId(candidateClientId, 'booking_completion');
            }

            const ensureParam = (key, value, transform) => {
                if (typeof eventParams[key] === 'undefined' && value !== undefined && value !== null && value !== '') {
                    eventParams[key] = typeof transform === 'function' ? transform(value) : value;
                }
            };

            ensureParam('booking_id', responseData.booking_id);
            ensureParam('value', responseData.value, (val) => parseFloat(val) || 0);
            ensureParam('currency', responseData.currency || 'EUR');
            ensureParam('meal_type', responseData.meal_type || responseData.meal || '');
            ensureParam('people_count', responseData.people_count || responseData.people, (val) => parseInt(val, 10) || 1);
            ensureParam('traffic_source', responseData.traffic_source || responseData.bucket || 'organic');

            if (typeof eventParams.funnel_step === 'undefined') {
                eventParams.funnel_step = 7;
            }

            if (typeof eventParams.step_name === 'undefined') {
                eventParams.step_name = 'booking_confirmation';
            }

            if (typeof eventParams.vertical === 'undefined') {
                eventParams.vertical = 'restaurant';
            }

            const options = { serverSide: false };
            if (responseData.event_id) {
                options.eventId = responseData.event_id;
            }

            if (responseData.client_id) {
                options.clientId = responseData.client_id;
            }

            const eventName = responseData.event_name || 'booking_confirmed';

            this.trackEvent(eventName, eventParams, options);
        },
        
        /**
         * Track booking error
         */
        trackBookingError: function(errorMessage, errorType, step) {
            this.trackEvent('booking_error', {
                error_message: errorMessage.substring(0, 100),
                error_type: errorType || 'unknown_error',
                funnel_step: step || 0,
                step_name: 'error'
            }, { allowDuplicate: true }); // Allow duplicate error tracking
        },
        
        /**
         * Initialize funnel tracking
         */
        init: function() {
            this.log('Initializing GA4 Funnel Tracking', {
                sessionId: this.sessionId,
                measurementId: this.measurementId
            });

            // Attempt to retrieve GA4 client ID immediately
            this.requestClientId();

            // Track initial form view
            this.trackFormView();

            // Set up event listeners
            this.setupEventListeners();
        },
        
        /**
         * Set up event listeners for funnel tracking
         */
        setupEventListeners: function() {
            const $form = $('#rbf-form');
            if (!$form.length) return;
            
            // Track meal selection
            $form.on('change', 'input[name="rbf_meal"]', (e) => {
                const mealType = $(e.target).val();
                this.trackMealSelection(mealType);
            });
            
            // Track date selection
            $form.on('change', '#rbf-date', (e) => {
                const selectedDate = $(e.target).val();
                const mealType = $form.find('input[name="rbf_meal"]:checked').val();
                if (selectedDate && mealType) {
                    this.trackDateSelection(selectedDate, mealType);
                }
            });
            
            // Track time selection
            $form.on('change', '#rbf-time', (e) => {
                const selectedTime = $(e.target).val();
                const selectedDate = $form.find('#rbf-date').val();
                const mealType = $form.find('input[name="rbf_meal"]:checked').val();
                if (selectedTime && selectedDate && mealType) {
                    // Extract just the time part if format is "slot|time"
                    const timeOnly = selectedTime.includes('|') ? selectedTime.split('|')[1] : selectedTime;
                    this.trackTimeSelection(timeOnly, selectedDate, mealType);
                }
            });
            
            // Track people selection
            $form.on('input change', '#rbf-people', (e) => {
                const peopleCount = $(e.target).val();
                const mealType = $form.find('input[name="rbf_meal"]:checked').val();
                if (peopleCount && mealType) {
                    this.trackPeopleSelection(peopleCount, mealType);
                }
            });
            
            // Track form submission
            $form.on('submit', (e) => {
                const formData = this.collectFormData($form);
                this.trackFormSubmission(formData);
            });
            
            // Track AJAX errors (if jQuery AJAX is available)
            if (typeof $(document).ajaxError === 'function') {
                $(document).ajaxError((event, xhr, settings, error) => {
                    if (settings.url && settings.url.includes('rbf_get_availability')) {
                        this.trackBookingError('Availability check failed', 'ajax_error', 3);
                    } else if (settings.data && settings.data.includes('rbf_form')) {
                        this.trackBookingError('Form submission failed', 'submission_error', 6);
                    }
                });
            }
            
            // Track validation errors
            $form.on('invalid', 'input, select, textarea', (e) => {
                const field = $(e.target);
                const fieldName = field.attr('name') || field.attr('id') || 'unknown';
                const errorMessage = e.target.validationMessage || 'Validation error';
                this.trackBookingError(`Validation error in ${fieldName}: ${errorMessage}`, 'validation_error', this.getCurrentStep());
            });
        },
        
        /**
         * Collect current form data
         */
        collectFormData: function($form) {
            return {
                meal: $form.find('input[name="rbf_meal"]:checked').val() || '',
                date: $form.find('#rbf-date').val() || '',
                time: $form.find('#rbf-time').val() || '',
                people: $form.find('#rbf-people').val() || '1',
                name: $form.find('#rbf-name').val() || '',
                email: $form.find('#rbf-email').val() || '',
                phone: $form.find('#rbf-tel').val() || '',
                notes: $form.find('#rbf-notes').val() || '',
                marketing: $form.find('#rbf-marketing').is(':checked') ? 'yes' : 'no'
            };
        },
        
        /**
         * Determine current step based on form state
         */
        getCurrentStep: function() {
            const $form = $('#rbf-form');
            if (!$form.length) return 1;
            
            if (!$form.find('input[name="rbf_meal"]:checked').length) return 2;
            if (!$form.find('#rbf-date').val()) return 3;
            if (!$form.find('#rbf-time').val()) return 4;
            if (!$form.find('#rbf-people').val()) return 5;
            return 6;
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        FunnelTracker.init();
    });
    
    // Track booking confirmation on success page
    if (window.location.search.includes('rbf_success=1')) {
        const urlParams = new URLSearchParams(window.location.search);
        const bookingId = urlParams.get('booking_id');
        
        // Check if we have booking data from server (if AJAX is available)
        if (bookingId && typeof $.ajax === 'function') {
            $.ajax({
                url: rbfGA4Funnel.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rbf_get_booking_completion_data',
                    booking_id: bookingId,
                    nonce: rbfGA4Funnel.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        FunnelTracker.trackBookingConfirmation(response.data);
                    }
                }
            });
        }
    }
    
    // Expose FunnelTracker for manual tracking if needed
    window.rbfFunnelTracker = FunnelTracker;
    
})(jQuery);