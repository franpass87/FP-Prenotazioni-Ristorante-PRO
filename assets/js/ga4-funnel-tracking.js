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
        
        /**
         * Log debug messages if debug mode is enabled
         */
        log: function(message, data = null) {
            if (this.debug && window.console) {
                console.log('RBF GA4 Funnel:', message, data || '');
            }
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
            const eventId = this.generateEventId(eventName);
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

            // Then track via gtag if available
            if (typeof gtag === 'function') {
                gtag('event', eventName, enhancedParams);
                this.log(`Event sent to gtag: ${eventName}`);
            }
            
            // Also send to server for Measurement Protocol (if configured)
            if (options.serverSide !== false) {
                this.sendToServer(eventName, enhancedParams, eventId);
            }
        },
        
        /**
         * Send event to server for Measurement Protocol tracking
         */
        sendToServer: function(eventName, params, eventId) {
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
        trackBookingConfirmation: function(bookingData) {
            this.trackEvent('booking_confirmed', {
                booking_id: bookingData.booking_id || '',
                value: parseFloat(bookingData.value) || 0,
                currency: bookingData.currency || 'EUR',
                meal_type: bookingData.meal_type || '',
                people_count: parseInt(bookingData.people_count) || 1,
                traffic_source: bookingData.traffic_source || 'organic',
                funnel_step: 7,
                step_name: 'booking_confirmation'
            });
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