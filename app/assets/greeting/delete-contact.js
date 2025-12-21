import $ from 'jquery';
import { Modal } from 'bootstrap';

let deleteModal; // Singleton for the modal instance

// Handle Delete Confirmation Button Click (Global for the page)
$(function() {
    $('#btn-confirm-delete').on('click', function() {
        const $btn = $(this);
        const url = $btn.data('url');

        if (!url) return;

        // Disable button to prevent double clicks
        $btn.prop('disabled', true);

        $.ajax({
            url: url,
            method: 'DELETE',
            success: function() {
                // Reload the page to refresh the table layout and show Flash messages from server
                window.location.reload();
            },
            error: function() {
                // Even on error (e.g. 404 or 400), we reload to show the flash message set by the backend
                // and to sync the UI with the current state.
                window.location.reload();
            },
            complete: function() {
                // If reload happens, this might not even be seen, but good practice.
                $btn.prop('disabled', false);

                if (deleteModal) {
                    deleteModal.hide();
                }
            }
        });
    });
});

/**
 * Initializes delete functionality for a specific DataTables table instance.
 * @param {jQuery} table The jQuery object representing the table
 */
export function initDeleteContact(table) {
    // Prevent checkbox toggle when clicking the delete icon
    table.on('click', '.delete-contact-icon', function (e) {
        e.stopPropagation();
        const $icon = $(this);
        const url = $icon.data('url');
        const email = $icon.data('email');

        $('#deleteContactEmail').text(email);
        $('#btn-confirm-delete').data('url', url);
        const modalEl = document.getElementById('deleteContactModal');

        if (modalEl) {
            if (!deleteModal) {
                deleteModal = new Modal(modalEl);
            }
            deleteModal.show();
        }
    });
}
