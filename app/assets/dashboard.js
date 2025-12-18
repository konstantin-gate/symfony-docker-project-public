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
            lengthMenu: [
                [5, 10, 20, 40, 80, 167],
                [15, 30, 60, 120, 240, 500]
            ],
            pageLength: 5,
            ordering: false,
            searching: false,
            info: false, // "Showing x of y" info
            autoWidth: false, // Sometimes helps with Bootstrap responsiveness
            columnDefs: [
                { width: "33.33%", targets: "_all" }
            ],
            select: {
                style: 'os',
                items: 'cell',
                selector: 'td:not(.empty-cell)' // Prevent selecting empty placeholder cells if any
            },
            // Layout: Length (l) - we will move it, Table (t), Paging (p)
            dom: 'lt<"d-flex justify-content-end mt-3"p>',
            language: {
                lengthMenu: "_MENU_", // Show just the dropdown
            },
            pagingType: "simple_numbers",
            initComplete: function() {
                const api = this.api();
                const container = $(api.table().container());
                
                // Force small pagination
                container.find('.pagination').addClass('pagination-sm');
                
                // Move Length Menu to custom placeholder
                const lengthMenu = container.find('.dataTables_length, .dt-length');
                const placeholder = $(this).closest('.tab-pane').find('.dt-length-placeholder');
                lengthMenu.detach().appendTo(placeholder);
            },
            drawCallback: function() {
                $(this.api().table().container()).find('.pagination').addClass('pagination-sm');
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