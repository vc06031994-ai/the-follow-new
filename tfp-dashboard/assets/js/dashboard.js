(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('[data-tfp-dashboard]');
        var toggles = document.querySelectorAll('[data-tfp-sidebar-toggle]');
        var overlay = document.querySelector('[data-tfp-sidebar-overlay]');
        if (!root || !toggles.length) return;

        var STORAGE_KEY = 'tfp_dashboard_sidebar_collapsed';
        var isMobile = function () {
            return window.matchMedia('(max-width: 991px)').matches;
        };

        // Restore collapsed state on desktop only (mobile always starts closed).
        if (!isMobile() && localStorage.getItem(STORAGE_KEY) === '1') {
            root.classList.add('is-sidebar-collapsed');
        }

        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                if (isMobile()) {
                    root.classList.toggle('is-sidebar-open');
                } else {
                    root.classList.toggle('is-sidebar-collapsed');
                    localStorage.setItem(STORAGE_KEY, root.classList.contains('is-sidebar-collapsed') ? '1' : '0');
                }
            });
        });

        if (overlay) {
            overlay.addEventListener('click', function () {
                root.classList.remove('is-sidebar-open');
            });
        }

        // Closing the offcanvas sidebar if the viewport grows back to desktop.
        window.addEventListener('resize', function () {
            if (!isMobile()) {
                root.classList.remove('is-sidebar-open');
            }
        });
    });
})();
