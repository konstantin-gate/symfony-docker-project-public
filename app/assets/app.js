/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// 1. Setup jQuery globally FIRST
import './setup-jquery';

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';
import './styles/index.css';
import './styles/greeting.css';

// 2. Import Bootstrap JS (now it sees global jQuery)
import 'bootstrap/dist/js/bootstrap.bundle';
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';
import 'datatables.net-select-bs5/css/select.bootstrap5.min.css';

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});

// 3. Import DataTables and its Select extension
import 'datatables.net-bs5';
import 'datatables.net-select-bs5';
import './dashboard';


