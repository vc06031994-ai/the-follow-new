<?php
/**
 * Template Name: TFP Checkout Wizard
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<div class="tfp-checkout-template-shell">
    <?php tfp_checkout_render_wizard(); ?>
</div>
<?php
get_footer();
