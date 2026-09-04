(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof tfpFinancialAidSettings === 'undefined') return;

        var form  = document.querySelector('[data-tfp-financial-aid-form]');
        var modal = document.querySelector('[data-tfp-aid-success-modal]');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var submitBtn = form.querySelector('.tfp-dash-form__submit');
            var formData = new FormData(form);
            formData.append('action', 'tfp_financial_aid_submit');
            formData.append('tfp_financial_aid_nonce', tfpFinancialAidSettings.nonce);

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting…';
            }

            fetch(tfpFinancialAidSettings.ajaxUrl, {
                method: 'POST',
                body: formData,
            })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Submit Application';
                    }

                    if (!res.success) {
                        window.alert(res.message || 'Something went wrong. Please try again.');
                        return;
                    }

                    if (modal) {
                        modal.hidden = false;
                    }
                });
        });
    });
})();
