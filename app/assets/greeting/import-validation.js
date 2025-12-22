import $ from 'jquery';
import debounce from 'lodash/debounce';

export function initImportValidation() {
    const $form = $('#import-form');

    if ($form.length === 0) return;

    const $fileInput = $('#xml-file-input');
    const $fileErrorContainer = $('#xml-file-error');

        // Email text area validation
        // Note: ID was added in template: 'import_emails' (which corresponds to form widget id usually 'greeting_import_emails',
        // but we explicitly set id='import_emails' in widget attrs in previous step)
        // Actually, symfony generates ID based on form name. Let's select by the ID we added manually or standard one if generic.
        // In the template change, I added 'id': 'import_emails'.
        let $emailInput = $('#import_emails');
        if ($emailInput.length === 0) {
            // Fallback: find the first textarea in the form
            $emailInput = $form.find('textarea').first();
        }
        
        const $emailFeedback = $('#email-validation-feedback');
    
        const $submitBtn = $form.find('button[type="submit"]');    
        const msgInvalidExt = $form.data('msg-invalid-ext');
        const msgTooLarge = $form.data('msg-file-too-large');
        const msgImporting = $form.data('msg-importing');
        
        // Store validation state
        let isFileValid = true;
        let isEmailListValid = true; // Initially true because empty is allowed (technically)
    
        function updateSubmitButton() {
            // Disabled if:
            // 1. File is invalid
            // 2. Email list is explicitly invalid (e.g. contains garbage)
            // Note: If both are empty, form submit might fail on server side or just do nothing, 
            // but strictly speaking, we shouldn't block unless we know it's bad.
            // HOWEVER: The user issue is that "invalid-email" text causes reload.
            // This happens because our validation logic WAS NOT blocking submit effectively enough 
            // or race condition persisted.
            
            // Let's be strict:
            if (!isFileValid || !isEmailListValid) {
                $submitBtn.prop('disabled', true);
            } else {
                $submitBtn.prop('disabled', false);
            }
        }
    
        // Initialize button state - verify initial state of inputs
        // (Assuming clean form on load)
        updateSubmitButton();
        // --- File Input Validation ---

    $fileInput.on('change', function () {
        const file = this.files[0];

        if (!file) {
            clearFileError();
            isFileValid = true;
            updateSubmitButton();
            return;
        }

        // 1. Check extension
        const fileName = file.name;
        const extension = fileName.split('.').pop().toLowerCase();

        if (extension !== 'xml') {
            showFileError(msgInvalidExt);
            isFileValid = false;
        } else {
            // 2. Check size (2MB = 2 * 1024 * 1024 bytes)
            const maxSize = 2 * 1024 * 1024;

            if (file.size > maxSize) {
                showFileError(msgTooLarge);
                isFileValid = false;
            } else {
                clearFileError();
                isFileValid = true;
            }
        }
        updateSubmitButton();
    });

    function showFileError(msg) {
        $fileInput.addClass('is-invalid');
        $fileErrorContainer.text(msg).show();
    }

    function clearFileError() {
        $fileInput.removeClass('is-invalid');
        $fileErrorContainer.hide().text('');
    }

    // --- Email List Validation ---

    /**
     * Parses raw text into an array of cleaned email strings.
     * @param {string} rawText
     * @returns {string[]}
     */
    function parseEmails(rawText) {
        if (!rawText) return [];

        return rawText
            .split(/[\s,;]+/) // Split by whitespace (incl. newlines), comma, semicolon
            .map(s => s.trim()) // Trim whitespace
            .filter(s => s !== ''); // Remove empty strings
    }

    /**
     * Validates a single email string.
     * @param {string} email
     * @returns {boolean}
     */
    function isValidEmail(email) {
        // Simple but robust regex for basic structure: non-whitespace @ non-whitespace . non-whitespace
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    /**
     * Core validation logic (Synchronous)
     */
    function performEmailValidation() {
        const rawText = $emailInput.val();

        // If empty, reset validation state
        if (!rawText || rawText.trim() === '') {
            $emailInput.removeClass('is-invalid is-valid');
            $emailFeedback.hide().text('').removeClass('invalid-feedback text-success');
            isEmailListValid = true;
            updateSubmitButton();
            return;
        }

        const emails = parseEmails(rawText);

        if (emails.length === 0) {
            // Text contained only separators
            $emailInput.removeClass('is-invalid is-valid');
            $emailFeedback.hide();
            isEmailListValid = true;
            updateSubmitButton();
            return;
        }

        // Deduplication (Case Insensitive)
        const uniqueEmails = [];
        const seen = new Set();

        emails.forEach(email => {
            const lower = email.toLowerCase();
            if (!seen.has(lower)) {
                seen.add(lower);
                uniqueEmails.push(email);
            }
        });

        const invalidEmails = [];
        uniqueEmails.forEach(email => {
            if (!isValidEmail(email)) {
                invalidEmails.push(email);
            }
        });

        if (invalidEmails.length > 0) {
            // Scenario: Error
            isEmailListValid = false;
            $emailInput.removeClass('is-valid').addClass('is-invalid');
            $emailFeedback.removeClass('text-success').addClass('invalid-feedback');

            // Show first few invalid emails
            const shownInvalid = invalidEmails.slice(0, 3).join(', ');
            const moreCount = invalidEmails.length - 3;
            let msg = `Found errors: '${shownInvalid}'`;
            if (moreCount > 0) {
                msg += ` and ${moreCount} more...`;
            } else {
                msg += ' is not a valid email.';
            }

            $emailFeedback.text(msg).show();
        } else {
            // Scenario: Success
            isEmailListValid = true;
            $emailInput.removeClass('is-invalid').addClass('is-valid');
            $emailFeedback.removeClass('invalid-feedback').addClass('text-success'); // Bootstrap utility for green text

            const count = uniqueEmails.length;
            const duplicateCount = emails.length - uniqueEmails.length;

            let msg = `Ready to import: ${count} valid address(es).`;
            if (duplicateCount > 0) {
                msg += ` (${duplicateCount} duplicate(s) ignored)`;
            }

            $emailFeedback.text(msg).show();
        }

        updateSubmitButton();
    }

    const validateEmailListDebounced = debounce(performEmailValidation, 500);

    $emailInput.on('input', validateEmailListDebounced);


    // --- Form Submit ---
    $form.on('submit', function (e) {
        // Cancel any pending debounced validation
        validateEmailListDebounced.cancel();

        // Force synchronous validation immediately
        performEmailValidation();

        // Check if validation failed
        // We check flags OR classes because performEmailValidation updates both.
        // Checking flags is safer as they reflect the latest logic run.
        if (!isFileValid || !isEmailListValid) {
            e.preventDefault();
            // Optional: shake effect or focus first invalid input
            return;
        }

        // Check for server-side validation errors
        const serverErrors = $form.find('.invalid-feedback.d-block');
        if (serverErrors.length > 0) {
            e.preventDefault();
            return;
        }

        // 3. Prevent double submit & show spinner
        $submitBtn.prop('disabled', true);
        $submitBtn.html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ${msgImporting}`);
    });
}
