/**
 * TFP Account Menu — dropdown interaction only.
 *
 * This file NEVER decides whether to show the account menu or the
 * Login/Register buttons — that is rendered by PHP based on WordPress
 * login state ([tfp_guest_only] / [tfp_account_menu]). This script only
 * handles opening/closing the dropdown and keyboard accessibility.
 *
 * Vanilla JS, no jQuery dependency, works for multiple instances on the
 * same page (desktop header, mobile menu, sticky header, popups, etc.).
 */
(function () {
    'use strict';

    function closeMenu(root) {
        var trigger = root.querySelector('.tfp-account__trigger');
        var dropdown = root.querySelector('.tfp-account__dropdown');
        if (!trigger || !dropdown) return;

        root.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
        dropdown.setAttribute('aria-hidden', 'true');
        dropdown.querySelectorAll('.tfp-account__link').forEach(function (link) {
            link.setAttribute('tabindex', '-1');
        });
    }

    function openMenu(root) {
        var trigger = root.querySelector('.tfp-account__trigger');
        var dropdown = root.querySelector('.tfp-account__dropdown');
        if (!trigger || !dropdown) return;

        // Close any other open menus first (multiple instances on page).
        document.querySelectorAll('.tfp-account.is-open').forEach(function (openRoot) {
            if (openRoot !== root) closeMenu(openRoot);
        });

        root.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        dropdown.setAttribute('aria-hidden', 'false');
        dropdown.querySelectorAll('.tfp-account__link').forEach(function (link) {
            link.setAttribute('tabindex', '0');
        });
    }

    function toggleMenu(root) {
        if (root.classList.contains('is-open')) {
            closeMenu(root);
        } else {
            openMenu(root);
        }
    }

    function getFocusableItems(root) {
        return Array.prototype.slice.call(root.querySelectorAll('.tfp-account__link'));
    }

    function initAccountMenu(root) {
        var trigger = root.querySelector('.tfp-account__trigger');
        var dropdown = root.querySelector('.tfp-account__dropdown');
        if (!trigger || !dropdown) return;

        // Ensure menu items start non-focusable (closed state).
        dropdown.querySelectorAll('.tfp-account__link').forEach(function (link) {
            link.setAttribute('tabindex', '-1');
        });

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleMenu(root);
        });

        trigger.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'Down') {
                e.preventDefault();
                openMenu(root);
                var items = getFocusableItems(root);
                if (items.length) items[0].focus();
            }
        });

        dropdown.addEventListener('keydown', function (e) {
            var items = getFocusableItems(root);
            var currentIndex = items.indexOf(document.activeElement);

            if (e.key === 'Escape') {
                e.preventDefault();
                closeMenu(root);
                trigger.focus();
                return;
            }

            if (e.key === 'ArrowDown' || e.key === 'Down') {
                e.preventDefault();
                var next = items[currentIndex + 1] || items[0];
                if (next) next.focus();
                return;
            }

            if (e.key === 'ArrowUp' || e.key === 'Up') {
                e.preventDefault();
                var prev = items[currentIndex - 1] || items[items.length - 1];
                if (prev) prev.focus();
                return;
            }

            if (e.key === 'Tab' && !e.shiftKey && currentIndex === items.length - 1) {
                closeMenu(root);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var menus = document.querySelectorAll('.tfp-account');
        menus.forEach(initAccountMenu);

        // Close on outside click.
        document.addEventListener('click', function (e) {
            document.querySelectorAll('.tfp-account.is-open').forEach(function (root) {
                if (!root.contains(e.target)) {
                    closeMenu(root);
                }
            });
        });

        // Global Escape key support.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.tfp-account.is-open').forEach(closeMenu);
            }
        });
    });
})();
