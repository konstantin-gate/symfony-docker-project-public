import $ from 'jquery';
import 'datatables.net-bs5';
import 'datatables.net-select-bs5';

$(function() {
    // Fix for DataTables inside Bootstrap Tabs: Recalculate columns when tab is shown
    // Using native event listener for better reliability with Bootstrap 5
    const tabEl = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabEl.forEach(el => {
        el.addEventListener('shown.bs.tab', event => {
             $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        });
    });

    // Initialize DataTables on all tables with class 'greeting-table'
    $('.greeting-table').each(function() {
        const table = $(this);
        
        // Skip if already initialized
        if ($.fn.DataTable.isDataTable(table)) {
            return;
        }

        const dt = table.DataTable({
            paging: true,
            lengthMenu: [30, 60, 120, 240, 500],
            pageLength: 30,
            ordering: false,
            searching: false,
            info: false, // "Showing x of y" info
            autoWidth: false, // Sometimes helps with Bootstrap responsiveness
            select: {
                style: 'os',
                items: 'cell',
                selector: 'td:not(.empty-cell)' // Prevent selecting empty placeholder cells if any
            },
            // Layout: Table (t), then Row with Length (Left) and Paging (Right)
            dom: 't<"d-flex justify-content-between align-items-center mt-3"lp>',
            language: {
                lengthMenu: "_MENU_", // Show just the dropdown
            }
        });

        // Sync selection with checkboxes
        dt.on('select', function(e, dt, type, indexes) {
            if (type === 'cell') {
                const cells = dt.cells(indexes).nodes().to$();
                cells.find('input[type="checkbox"]').prop('checked', true);
            }
        });

        dt.on('deselect', function(e, dt, type, indexes) {
            if (type === 'cell') {
                const cells = dt.cells(indexes).nodes().to$();
                cells.find('input[type="checkbox"]').prop('checked', false);
            }
        });

        // "Select All" button
        // Find buttons within the same tab-pane
        const pane = table.closest('.tab-pane');
        if (pane.length > 0) {
            pane.find('.btn-select-all').on('click', function() {
                dt.cells().select();
            });

            pane.find('.btn-deselect-all').on('click', function() {
                dt.cells().deselect();
            });
        }
    });
});