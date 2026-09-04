(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof tfpProfileSettings === 'undefined') return;

        // --- Edit / Cancel toggles for each profile section -----------------
        document.querySelectorAll('[data-tfp-section]').forEach(function (section) {
            var editBtn = section.querySelector('[data-tfp-edit-toggle]');
            var cancelBtn = section.querySelector('[data-tfp-edit-cancel]');
            var viewEl = section.querySelector('.tfp-profile-section__view');
            var formEl = section.querySelector('.tfp-profile-section__edit-form');

            if (!editBtn || !viewEl || !formEl) return;

            editBtn.addEventListener('click', function () {
                viewEl.hidden = true;
                formEl.hidden = false;
                editBtn.hidden = true;
            });

            if (cancelBtn) {
                cancelBtn.addEventListener('click', function () {
                    formEl.hidden = true;
                    viewEl.hidden = false;
                    editBtn.hidden = false;
                });
            }
        });

        // --- Section-scoped save via AJAX ------------------------------------
        document.querySelectorAll('[data-tfp-profile-form]').forEach(function (form) {
            var statusEl = form.querySelector('[data-tfp-profile-status]');
            var section = form.closest('[data-tfp-section]');
            var viewEl = section ? section.querySelector('.tfp-profile-section__view') : null;
            var editBtn = section ? section.querySelector('[data-tfp-edit-toggle]') : null;

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var submitBtn = form.querySelector('button[type="submit"]');
                var body = new URLSearchParams(new FormData(form));
                body.append('action', 'tfp_profile_update');
                body.append('tfp_profile_nonce', tfpProfileSettings.nonce);

                if (submitBtn) submitBtn.disabled = true;
                if (statusEl) {
                    statusEl.textContent = '';
                    statusEl.removeAttribute('data-state');
                }

                fetch(tfpProfileSettings.ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                })
                    .then(function (res) { return res.json(); })
                    .then(function (res) {
                        if (submitBtn) submitBtn.disabled = false;

                        if (statusEl) {
                            statusEl.textContent = res.message || (res.success ? 'Saved.' : 'Something went wrong.');
                            statusEl.setAttribute('data-state', res.success ? 'success' : 'error');
                        }

                        if (!res.success) return;

                        // Update the read-only view in place, then switch back to it.
                        if (res.values && viewEl) {
                            Object.keys(res.values).forEach(function (key) {
                                var target = viewEl.querySelector('[data-field="' + key + '"]');
                                if (!target) return;

                                var value = res.values[key];
                                if (Array.isArray(value)) {
                                    target.textContent = value.length ? value.join(', ') : '—';
                                } else {
                                    target.textContent = value || '—';
                                }
                            });
                        }

                        setTimeout(function () {
                            if (viewEl) {
                                form.hidden = true;
                                viewEl.hidden = false;
                                if (editBtn) editBtn.hidden = false;
                            }
                        }, 600);
                    })
                    .catch(function () {
                        if (submitBtn) submitBtn.disabled = false;
                        if (statusEl) {
                            statusEl.textContent = 'Could not reach the server. Please try again.';
                            statusEl.setAttribute('data-state', 'error');
                        }
                    });
            });
        });

        // --- Avatar upload ----------------------------------------------------
        var avatarInput = document.querySelector('[data-tfp-avatar-input]');
        var avatarPreview = document.querySelector('[data-tfp-avatar-preview]');

        if (avatarInput) {
            avatarInput.addEventListener('change', function () {
                if (!avatarInput.files || !avatarInput.files[0]) return;

                var body = new FormData();
                body.append('action', 'tfp_profile_upload_avatar');
                body.append('tfp_profile_nonce', tfpProfileSettings.nonce);
                body.append('avatar', avatarInput.files[0]);

                fetch(tfpProfileSettings.ajaxUrl, {
                    method: 'POST',
                    body: body,
                })
                    .then(function (res) { return res.json(); })
                    .then(function (res) {
                        if (!res.success) {
                            window.alert(res.message || 'Could not upload photo.');
                            return;
                        }
                        if (avatarPreview && res.avatar_url) {
                            avatarPreview.innerHTML = '<img src="' + res.avatar_url + '" alt="">';
                        }
                    })
                    .catch(function () {
                        window.alert('Could not reach the server. Please try again.');
                    });
            });
        }
    });
})();
