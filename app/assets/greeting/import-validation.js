import $ from 'jquery';

export function initImportValidation() {
    const $form = $('#import-form');

    if ($form.length === 0) return;

    const $fileInput = $('#xml-file-input');
    const $errorContainer = $('#xml-file-error');
    const $submitBtn = $form.find('button[type="submit"]');

    const msgInvalidExt = $form.data('msg-invalid-ext');
    const msgTooLarge = $form.data('msg-file-too-large');
    const msgImporting = $form.data('msg-importing');

    $fileInput.on('change', function () {
        const file = this.files[0];

        if (!file) {
            clearError();

            return;
        }

        // 1. Check extension
        const fileName = file.name;
        const extension = fileName.split('.').pop().toLowerCase();

        if (extension !== 'xml') {
            showError(msgInvalidExt);

            return;
        }

        // 2. Check size (2MB = 2 * 1024 * 1024 bytes)
        const maxSize = 2 * 1024 * 1024;

        if (file.size > maxSize) {
            showError(msgTooLarge);

            return;
        }

        clearError();
    });

    $form.on('submit', function (e) {
        // Double check validation before allowing to submit
        if ($fileInput.hasClass('is-invalid')) {
            e.preventDefault();

            return;
        }

        // 3. Prevent double submit
        $submitBtn.prop('disabled', true);
        $submitBtn.html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ${msgImporting}`);
    });

    function showError(msg) {
        $fileInput.addClass('is-invalid');
        $errorContainer.text(msg).show();
        $submitBtn.prop('disabled', true);
    }

    function clearError() {
        $fileInput.removeClass('is-invalid');
        $errorContainer.hide().text('');
        $submitBtn.prop('disabled', false);
    }
}
