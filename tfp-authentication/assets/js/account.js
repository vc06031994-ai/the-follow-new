jQuery(document).ready(function ($) {
    var $slideOver = $('#tfp-order-slide-over');
    var $backdrop = $('#tfp-order-slide-backdrop');
    var $content = $('#tfp-order-slide-content');
    var $loader = $('#tfp-order-slide-loader');

    function openSlideOver() {
        $slideOver.addClass('tfp-active');
        $backdrop.addClass('tfp-active');
        $('body').css('overflow', 'hidden'); // Prevent background scrolling
    }

    function closeSlideOver() {
        $slideOver.removeClass('tfp-active');
        $backdrop.removeClass('tfp-active');
        $('body').css('overflow', '');
    }

    // Bind click on 'View' buttons
    $('.tfp-view-order-btn').on('click', function (e) {
        e.preventDefault();
        var orderId = $(this).data('order-id');

        if (!orderId || typeof tfpAccountSettings === 'undefined') return;

        // Reset content and show loader
        $content.empty().hide();
        $loader.show();
        openSlideOver();

        $.ajax({
            url: tfpAccountSettings.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tfp_get_order_details',
                nonce: tfpAccountSettings.nonce,
                order_id: orderId
            },
            success: function (response) {
                if (response.success && response.data && response.data.html) {
                    $content.html(response.data.html);
                    $loader.hide();
                    $content.show();
                } else {
                    $loader.hide();
                    $content.html('<div class="tfp-so-error"><p>Could not load order details.</p><button class="tfp-so-close-btn tfp-auth-btn tfp-auth-btn--primary">Close</button></div>').show();
                }
            },
            error: function () {
                $loader.hide();
                $content.html('<div class="tfp-so-error"><p>An error occurred.</p><button class="tfp-so-close-btn tfp-auth-btn tfp-auth-btn--primary">Close</button></div>').show();
            }
        });
    });

    // Close bindings
    $backdrop.on('click', function () {
        closeSlideOver();
    });

    // Delegated click for dynamically loaded close buttons
    $slideOver.on('click', '.tfp-so-close, .tfp-so-close-btn', function (e) {
        e.preventDefault();
        closeSlideOver();
    });

    // Notification Preferences Inline Toggle
    $('.tfp-edit-notifications-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $checkboxes = $('.tfp-notification-checkbox');
        var isEditing = $btn.hasClass('tfp-editing');

        if (isEditing) {
            // "Save" mode clicked - disable checkboxes and change text back to Edit
            $checkboxes.prop('disabled', true);
            $btn.removeClass('tfp-editing');
            $btn.find('span').text('Edit');
            
            // Optionally: Trigger AJAX here to save preferences
            // var orderUpdates = $('input[name="order_updates_email"]').is(':checked');
            // var shippingUpdates = $('input[name="shipping_updates_email"]').is(':checked');
        } else {
            // "Edit" mode clicked - enable checkboxes and change text to Save
            $checkboxes.prop('disabled', false);
            $btn.addClass('tfp-editing');
            $btn.find('span').text('Save');
        }
    });
});
