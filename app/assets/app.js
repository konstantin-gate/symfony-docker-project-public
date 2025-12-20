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

// 2. Import Bootstrap JS (now it sees global jQuery)
import * as bootstrap from 'bootstrap';

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function () {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});


