/**
 * RIG Accessible Forms - Frontend JavaScript
 * Provides progressive enhancement for forms with full accessibility support
 */
(function() {
    'use strict';

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        const forms = document.querySelectorAll('form[action*="admin-post.php"]');
        forms.forEach(function(form) {
            if (form.querySelector('input[name="action"][value="rigaf_submit"]')) {
                initializeForm(form);
            }
        });

        // Focus error summary if present on page load
        focusErrorSummaryOnLoad();
    }

    /**
     * Initialize a single form with all enhancements
     */
    function initializeForm(form) {
        // Prevent duplicate submissions
        preventDuplicateSubmission(form);

        // Add client-side validation
        addClientValidation(form);

        // Enhance file inputs
        enhanceFileInputs(form);

        // Add ARIA live region for dynamic announcements
        addLiveRegion(form);
    }

    /**
     * Focus error summary on page load if errors are present
     */
    function focusErrorSummaryOnLoad() {
        const errorSummary = document.querySelector('.rigaf-error-summary');
        if (errorSummary) {
            // Small delay to ensure screen readers are ready
            setTimeout(function() {
                errorSummary.focus();
                // Announce to screen readers
                announceToScreenReader('There are errors in the form. Please review and correct them.');
            }, 100);
        }
    }

    /**
     * Prevent duplicate form submissions
     */
    function preventDuplicateSubmission(form) {
        let isSubmitting = false;

        form.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                announceToScreenReader('Form is being submitted. Please wait.');
                return false;
            }

            // Check if form is valid before marking as submitting
            if (form.checkValidity && !form.checkValidity()) {
                // Let validation run
                return;
            }

            isSubmitting = true;

            // Disable submit button and show feedback
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                const originalText = submitBtn.textContent || submitBtn.value;
                if (submitBtn.tagName === 'BUTTON') {
                    submitBtn.textContent = rigafI18n && rigafI18n.submitting ? rigafI18n.submitting : 'Submitting...';
                }
                submitBtn.setAttribute('aria-busy', 'true');

                // Re-enable after timeout as fallback
                setTimeout(function() {
                    isSubmitting = false;
                    submitBtn.disabled = false;
                    submitBtn.removeAttribute('aria-busy');
                    if (submitBtn.tagName === 'BUTTON') {
                        submitBtn.textContent = originalText;
                    }
                }, 30000); // 30 second timeout
            }
        });
    }

    /**
     * Add client-side validation with accessible error messages
     */
    function addClientValidation(form) {
        // Don't override native validation entirely - enhance it
        form.setAttribute('novalidate', 'novalidate'); // We'll handle it

        form.addEventListener('submit', function(e) {
            const errors = validateForm(form);

            if (errors.length > 0) {
                e.preventDefault();
                displayErrors(form, errors);
                return false;
            }
        });

        // Real-time validation on blur for better UX
        const fields = form.querySelectorAll('input, textarea, select');
        fields.forEach(function(field) {
            field.addEventListener('blur', function() {
                validateField(field);
            });

            // Clear error on input
            field.addEventListener('input', function() {
                clearFieldError(field);
            });
        });
    }

    /**
     * Validate entire form
     */
    function validateForm(form) {
        const errors = [];
        const fields = form.querySelectorAll('[required], input[type="email"], input[type="tel"], input[type="date"], input[type="file"]');

        fields.forEach(function(field) {
            const error = validateField(field, true);
            if (error) {
                errors.push(error);
            }
        });

        return errors;
    }

    /**
     * Validate a single field
     */
    function validateField(field, silent) {
        silent = silent || false;

        // Skip hidden fields
        if (field.offsetParent === null && field.type !== 'hidden') {
            return null;
        }

        const fieldName = field.name || field.id;
        const fieldLabel = getFieldLabel(field);
        let errorMessage = null;

        // Required field check
        if (field.hasAttribute('required') || field.hasAttribute('aria-required')) {
            if (field.type === 'checkbox' && !field.checked) {
                errorMessage = fieldLabel + ' is required.';
            } else if (field.type === 'radio') {
                const radioGroup = document.querySelectorAll('input[name="' + field.name + '"]');
                let isChecked = false;
                radioGroup.forEach(function(radio) {
                    if (radio.checked) isChecked = true;
                });
                if (!isChecked) {
                    errorMessage = fieldLabel + ' is required.';
                }
            } else if (!field.value.trim()) {
                errorMessage = fieldLabel + ' is required.';
            }
        }

        // Type-specific validation
        if (!errorMessage && field.value.trim()) {
            if (field.type === 'email') {
                if (!isValidEmail(field.value)) {
                    errorMessage = 'Please enter a valid email address.';
                }
            } else if (field.type === 'tel') {
                if (!isValidPhone(field.value)) {
                    errorMessage = 'Please enter a valid phone number.';
                }
            } else if (field.type === 'date') {
                if (!isValidDate(field.value)) {
                    errorMessage = 'Please enter a valid date.';
                }
            } else if (field.type === 'file') {
                const fileError = validateFileInput(field);
                if (fileError) {
                    errorMessage = fileError;
                }
            }
        }

        if (!silent) {
            if (errorMessage) {
                showFieldError(field, errorMessage);
            } else {
                clearFieldError(field);
            }
        }

        return errorMessage ? { field: field, message: errorMessage, label: fieldLabel } : null;
    }

    /**
     * Validate file input
     */
    function validateFileInput(fileInput) {
        if (!fileInput.files || fileInput.files.length === 0) {
            return null;
        }

        const file = fileInput.files[0];
        const accept = fileInput.getAttribute('accept');

        // Validate file type if accept attribute is present
        if (accept) {
            const allowedExtensions = accept.split(',').map(function(ext) {
                return ext.trim().toLowerCase().replace('.', '');
            });

            const fileName = file.name.toLowerCase();
            const fileExt = fileName.substring(fileName.lastIndexOf('.') + 1);

            if (!allowedExtensions.includes(fileExt)) {
                return 'This file type is not allowed. Allowed types: ' + accept;
            }
        }

        // Check file size (5MB default, can be adjusted)
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            return 'File size exceeds the maximum allowed size of 5 MB.';
        }

        return null;
    }

    /**
     * Display validation errors
     */
    function displayErrors(form, errors) {
        // Remove existing error summary
        const existingSummary = form.querySelector('.rigaf-error-summary');
        if (existingSummary) {
            existingSummary.remove();
        }

        // Create error summary
        const summary = document.createElement('div');
        summary.className = 'rigaf-error-summary';
        summary.setAttribute('role', 'alert');
        summary.setAttribute('aria-live', 'assertive');
        summary.setAttribute('tabindex', '-1');

        const heading = document.createElement('p');
        const strong = document.createElement('strong');
        strong.textContent = rigafI18n && rigafI18n.errorSummaryTitle ? rigafI18n.errorSummaryTitle : 'There is a problem';
        heading.appendChild(strong);
        heading.appendChild(document.createTextNode(' ' + (rigafI18n && rigafI18n.errorSummaryInstruction ? rigafI18n.errorSummaryInstruction : 'Fix the following and resubmit.')));
        summary.appendChild(heading);

        const errorList = document.createElement('ul');
        errors.forEach(function(error) {
            const li = document.createElement('li');
            const link = document.createElement('a');
            link.href = '#' + (error.field.id || error.field.name);
            link.textContent = error.message;
            link.addEventListener('click', function(e) {
                e.preventDefault();
                error.field.focus();
            });
            li.appendChild(link);
            errorList.appendChild(li);
        });
        summary.appendChild(errorList);

        // Insert error summary at the top of the form
        form.insertBefore(summary, form.firstChild);

        // Focus the error summary
        setTimeout(function() {
            summary.focus();
            announceToScreenReader(errors.length + ' error' + (errors.length !== 1 ? 's' : '') + ' found. Please review and correct.');
        }, 100);

        // Show individual field errors
        errors.forEach(function(error) {
            showFieldError(error.field, error.message);
        });
    }

    /**
     * Show error for a specific field
     */
    function showFieldError(field, message) {
        const fieldContainer = field.closest('.rigaf-field') || field.parentElement;

        // Mark field as having error
        if (fieldContainer) {
            fieldContainer.classList.add('rigaf-has-error');
        }

        // Add aria-invalid
        field.setAttribute('aria-invalid', 'true');

        // Create or update error message
        const errorId = (field.id || field.name) + '_error';
        let errorDiv = document.getElementById(errorId);

        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = errorId;
            errorDiv.className = 'rigaf-error';
            errorDiv.setAttribute('role', 'alert');

            // Insert after field or after label
            if (field.type === 'checkbox' || field.type === 'radio') {
                const label = field.nextElementSibling;
                if (label && label.tagName === 'LABEL') {
                    label.parentNode.insertBefore(errorDiv, label.nextSibling);
                } else {
                    field.parentNode.insertBefore(errorDiv, field.nextSibling);
                }
            } else {
                field.parentNode.insertBefore(errorDiv, field.nextSibling);
            }
        }

        errorDiv.textContent = message;

        // Link error to field with aria-describedby
        const describedby = field.getAttribute('aria-describedby') || '';
        if (!describedby.includes(errorId)) {
            field.setAttribute('aria-describedby', (describedby + ' ' + errorId).trim());
        }
    }

    /**
     * Clear error from a specific field
     */
    function clearFieldError(field) {
        const fieldContainer = field.closest('.rigaf-field') || field.parentElement;

        // Remove error class
        if (fieldContainer) {
            fieldContainer.classList.remove('rigaf-has-error');
        }

        // Remove aria-invalid
        field.removeAttribute('aria-invalid');

        // Remove error message
        const errorId = (field.id || field.name) + '_error';
        const errorDiv = document.getElementById(errorId);
        if (errorDiv && !errorDiv.hasAttribute('data-server-error')) {
            errorDiv.remove();

            // Clean up aria-describedby
            const describedby = field.getAttribute('aria-describedby') || '';
            const newDescribedby = describedby.replace(errorId, '').trim();
            if (newDescribedby) {
                field.setAttribute('aria-describedby', newDescribedby);
            } else {
                field.removeAttribute('aria-describedby');
            }
        }
    }

    /**
     * Get field label text
     */
    function getFieldLabel(field) {
        // Try label element first
        const labelElement = document.querySelector('label[for="' + field.id + '"]');
        if (labelElement) {
            return labelElement.textContent.replace(/\s*\(required\)\s*/i, '').trim();
        }

        // Try aria-label
        if (field.getAttribute('aria-label')) {
            return field.getAttribute('aria-label');
        }

        // Try legend for fieldsets
        const fieldset = field.closest('fieldset');
        if (fieldset) {
            const legend = fieldset.querySelector('legend');
            if (legend) {
                return legend.textContent.replace(/\s*\(required\)\s*/i, '').trim();
            }
        }

        // Fallback to field name
        return field.name || 'This field';
    }

    /**
     * Enhance file inputs with accessibility feedback
     */
    function enhanceFileInputs(form) {
        const fileInputs = form.querySelectorAll('input[type="file"]');

        fileInputs.forEach(function(fileInput) {
            // Create status div for file name announcement
            const statusDiv = document.createElement('div');
            statusDiv.className = 'rigaf-file-status';
            statusDiv.setAttribute('role', 'status');
            statusDiv.setAttribute('aria-live', 'polite');
            statusDiv.style.marginTop = '0.5rem';
            fileInput.parentNode.insertBefore(statusDiv, fileInput.nextSibling);

            fileInput.addEventListener('change', function() {
                if (fileInput.files && fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
                    const message = 'Selected file: ' + file.name + ' (' + sizeInMB + ' MB)';
                    statusDiv.textContent = message;
                    announceToScreenReader(message);

                    // Validate file immediately
                    setTimeout(function() {
                        validateField(fileInput);
                    }, 100);
                } else {
                    statusDiv.textContent = '';
                }
            });
        });
    }

    /**
     * Add ARIA live region for announcements
     */
    function addLiveRegion(form) {
        if (document.getElementById('rigaf-live-region')) {
            return; // Already exists
        }

        const liveRegion = document.createElement('div');
        liveRegion.id = 'rigaf-live-region';
        liveRegion.setAttribute('role', 'status');
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        liveRegion.style.position = 'absolute';
        liveRegion.style.left = '-10000px';
        liveRegion.style.width = '1px';
        liveRegion.style.height = '1px';
        liveRegion.style.overflow = 'hidden';

        form.appendChild(liveRegion);
    }

    /**
     * Announce message to screen readers
     */
    function announceToScreenReader(message) {
        const liveRegion = document.getElementById('rigaf-live-region') || createGlobalLiveRegion();

        // Clear and set new message
        liveRegion.textContent = '';
        setTimeout(function() {
            liveRegion.textContent = message;
        }, 100);
    }

    /**
     * Create global live region if none exists
     */
    function createGlobalLiveRegion() {
        const liveRegion = document.createElement('div');
        liveRegion.id = 'rigaf-live-region';
        liveRegion.setAttribute('role', 'status');
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        liveRegion.style.position = 'absolute';
        liveRegion.style.left = '-10000px';
        liveRegion.style.width = '1px';
        liveRegion.style.height = '1px';
        liveRegion.style.overflow = 'hidden';

        document.body.appendChild(liveRegion);
        return liveRegion;
    }

    /**
     * Validation helper functions
     */
    function isValidEmail(email) {
        // Basic email validation
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function isValidPhone(phone) {
        // Allow numbers, spaces, hyphens, parentheses, plus
        const re = /^[0-9\s\-\+\(\)]+$/;
        return re.test(phone) && phone.replace(/\D/g, '').length >= 10;
    }

    function isValidDate(date) {
        // Check YYYY-MM-DD format
        const re = /^\d{4}-\d{2}-\d{2}$/;
        if (!re.test(date)) return false;

        // Check if it's a valid date
        const d = new Date(date);
        return d instanceof Date && !isNaN(d);
    }

})();
