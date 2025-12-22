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
    const $emailInput = $('#import_emails');
    const $emailFeedback = $('#email-validation-feedback');

    const $submitBtn = $form.find('button[type="submit"]');

    const msgInvalidExt = $form.data('msg-invalid-ext');
    const msgTooLarge = $form.data('msg-file-too-large');
    const msgImporting = $form.data('msg-importing');
    
    // Store validation state
    let isFileValid = true;
    let isEmailListValid = true;

    function updateSubmitButton() {
        // Disabled if:
        // 1. File is invalid (checked via isFileValid flag which tracks file input state)
        // 2. Email list is invalid (checked via isEmailListValid flag)
        // 3. Both inputs are empty (optional, but usually at least one is required)
        //    For now, let's stick to "don't block if empty, unless validation failed"
        
        if (!isFileValid || !isEmailListValid) {
            $submitBtn.prop('disabled', true);
        } else {
            $submitBtn.prop('disabled', false);
        }
    }

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
            .split(/[\n\r,;]+/) // Split by newline, comma, semicolon
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

    const validateEmailList = debounce(function() {
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
        // We keep original casing of the first occurrence for display/logic if needed,
        // but for checking duplicates we use lowercase.
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

    }, 500); // 500ms debounce

    $emailInput.on('input', validateEmailList);


    // --- Form Submit ---

    $form.on('submit', function (e) {
        // Final check before submit
        if ($fileInput.hasClass('is-invalid') || $emailInput.hasClass('is-invalid')) {
            e.preventDefault();
            return;
        }

        // 3. Prevent double submit & show spinner
        $submitBtn.prop('disabled', true);
        $submitBtn.html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ${msgImporting}`);
    });
}
