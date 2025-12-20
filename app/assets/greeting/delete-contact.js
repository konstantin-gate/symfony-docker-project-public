import $ from 'jquery';
import { Modal } from 'bootstrap';

let deleteModal; // Singleton for the modal instance

// Handle Delete Confirmation Button Click (Global for the page)
// This runs once when the module is imported
$(function() {
    $('#btn-confirm-delete').on('click', function() {
        const id = $(this).data('id');
        console.log('Confirmed deletion for contact ID:', id);
        // TODO: Add AJAX call to delete the contact here
        
        if (deleteModal) {
            deleteModal.hide();
        }
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
        
        const wrapper = $(this).siblings('.form-check');
        const checkbox = wrapper.find('input[type="checkbox"]');
        const email = wrapper.find('label').text().trim();
        const id = checkbox.val();

        $('#deleteContactEmail').text(email);
        $('#btn-confirm-delete').data('id', id);

        const modalEl = document.getElementById('deleteContactModal');
        if (modalEl) {
            if (!deleteModal) {
                deleteModal = new Modal(modalEl);
            }
            deleteModal.show();
        }
    });
}
