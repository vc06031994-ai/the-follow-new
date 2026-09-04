<?php
/**
 * Cohort read/query API.
 *
 * Seats are NEVER stored as a counter (which would drift as orders are
 * refunded/cancelled). Instead "seats taken" is computed live from the
 * students who actually enrolled into the cohort — a paid customer whose
 * `tfp_cohort_choice` user meta points at the cohort. The result is cached
 * for 60s to keep the Home card and the Browse-Cohorts modal snappy.
 */

if (!defined('ABSPATH')) exit;

/**
 * Cohort meta keys, in one place so the meta box, the query API and the
 * checkout all agree.
 */
function tfp_cohort_meta_keys()
{
    return [
        'course'     => '_related_course',   // LearnDash course id
        'product'    => '_related_product',  // WC product id (optional override)
        'start_date' => '_start_date',       // YYYY-MM-DD
        'schedule'   => '_schedule_text',    // e.g. "Thursdays 6:00 PM PT"
        'facilitator'=> '_facilitator',      // instructor name
        'seats_total'=> '_seats_total',      // integer capacity
        'price'      => '_price',            // optional per-cohort price override
    ];
}

/**
 * Published cohorts linked to a LearnDash course, ordered by start date.
 *
 * @return WP_Post[]
 */
function tfp_cohorts_for_course($course_id)
{
    $course_id = absint($course_id);
    if (!$course_id) {
        return [];
    }

    return get_posts([
        'post_type'      => 'tfp_cohort',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_key'       => '_start_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'   => '_related_course',
                'value' => $course_id,
            ],
        ],
    ]);
}

/**
 * The WooCommerce product a cohort sells. Uses the cohort's own
 * `_related_product` override when set, otherwise resolves the product linked
 * to the cohort's LearnDash course (the same relationship billing/helpers.php
 * uses everywhere else).
 */
function tfp_cohort_product_id($cohort_id)
{
    $cohort_id = absint($cohort_id);
    if (!$cohort_id) {
        return 0;
    }

    $product_id = (int) get_post_meta($cohort_id, '_related_product', true);
    if ($product_id) {
        return $product_id;
    }

    $course_id = (int) get_post_meta($cohort_id, '_related_course', true);
    if ($course_id && function_exists('tfp_billing_get_product_id_for_course')) {
        return (int) tfp_billing_get_product_id_for_course($course_id);
    }

    return 0;
}

/**
 * Price for a cohort: the `_price` override if present, otherwise the linked
 * WooCommerce product's price. Returned as a float.
 */
function tfp_cohort_price($cohort_id)
{
    $cohort_id = absint($cohort_id);
    $override  = get_post_meta($cohort_id, '_price', true);

    if ($override !== '' && $override !== null && is_numeric($override)) {
        return (float) $override;
    }

    if (function_exists('wc_get_product')) {
        $product_id = tfp_cohort_product_id($cohort_id);
        $product    = $product_id ? wc_get_product($product_id) : null;
        if ($product) {
            return (float) $product->get_price('edit');
        }
    }

    return 0.0;
}

/**
 * Does this cohort have an explicit per-cohort price override?
 */
function tfp_cohort_has_price_override($cohort_id)
{
    $override = get_post_meta(absint($cohort_id), '_price', true);
    return ($override !== '' && $override !== null && is_numeric($override));
}

/**
 * Seats taken — computed live from enrolled, paid students. Cached 60s.
 */
function tfp_cohort_seats_taken($cohort_id)
{
    $cohort_id = absint($cohort_id);
    if (!$cohort_id) {
        return 0;
    }

    $cache_key = 'tfp_cohort_taken_' . $cohort_id;
    $cached    = wp_cache_get($cache_key, 'tfp_cohorts');
    if ($cached !== false) {
        return (int) $cached;
    }

    $user_ids = get_users([
        'meta_key'   => 'tfp_cohort_choice',
        'meta_value' => $cohort_id,
        'fields'     => 'ID',
    ]);

    $count = 0;
    foreach ($user_ids as $uid) {
        // Cross-check the student has actually paid, so a stale choice on an
        // unpaid account never consumes a seat.
        if (!function_exists('tfp_billing_user_has_paid') || tfp_billing_user_has_paid($uid)) {
            $count++;
        }
    }

    wp_cache_set($cache_key, $count, 'tfp_cohorts', 60);
    return $count;
}

/**
 * Total seats configured on the cohort (0 = unlimited / not configured).
 */
function tfp_cohort_seats_total($cohort_id)
{
    return max(0, (int) get_post_meta(absint($cohort_id), '_seats_total', true));
}

/**
 * Seats still available. Returns PHP_INT_MAX when the cohort has no capacity
 * set (treated as unlimited).
 */
function tfp_cohort_seats_remaining($cohort_id)
{
    $total = tfp_cohort_seats_total($cohort_id);
    if ($total <= 0) {
        return PHP_INT_MAX;
    }
    return max(0, $total - tfp_cohort_seats_taken($cohort_id));
}

/**
 * Is the cohort full? Cohorts with no capacity set are never full.
 */
function tfp_cohort_is_full($cohort_id)
{
    $total = tfp_cohort_seats_total($cohort_id);
    if ($total <= 0) {
        return false;
    }
    return tfp_cohort_seats_taken($cohort_id) >= $total;
}

/**
 * Normalized cohort array for card rendering. Returns null if the id is not a
 * published cohort.
 */
function tfp_cohort_get($cohort_id)
{
    $cohort_id = absint($cohort_id);
    $post      = $cohort_id ? get_post($cohort_id) : null;

    if (!$post || $post->post_type !== 'tfp_cohort') {
        return null;
    }

    $start_raw = get_post_meta($cohort_id, '_start_date', true);
    $start_ts  = $start_raw ? strtotime($start_raw) : 0;
    $total     = tfp_cohort_seats_total($cohort_id);
    $taken     = tfp_cohort_seats_taken($cohort_id);

    return [
        'id'              => $cohort_id,
        // Decode any HTML entities in the stored title (e.g. a cohort saved with
        // a literal "&#8211;" instead of a real en-dash) so the raw name we hand
        // to the JSON/JS layer is a clean string. The JS esc() re-escapes it for
        // safe insertion, so decoding here never opens an XSS hole. No-op when
        // the title already holds real characters.
        'name'            => html_entity_decode(get_the_title($cohort_id), ENT_QUOTES, 'UTF-8'),
        'course_id'       => (int) get_post_meta($cohort_id, '_related_course', true),
        'product_id'      => tfp_cohort_product_id($cohort_id),
        'start_date'      => $start_raw,
        'start_label'     => $start_ts ? date_i18n(get_option('date_format'), $start_ts) : '',
        'schedule'        => (string) get_post_meta($cohort_id, '_schedule_text', true),
        'facilitator'     => (string) get_post_meta($cohort_id, '_facilitator', true),
        'seats_total'     => $total,
        'seats_taken'     => $taken,
        'seats_remaining' => $total > 0 ? max(0, $total - $taken) : PHP_INT_MAX,
        'is_full'         => $total > 0 ? ($taken >= $total) : false,
        'price'           => tfp_cohort_price($cohort_id),
        'price_html'      => function_exists('wc_price') ? wc_price(tfp_cohort_price($cohort_id)) : number_format_i18n(tfp_cohort_price($cohort_id)),
    ];
}

/**
 * Clear the cached seat count for a cohort (called after an enrollment lands).
 */
function tfp_cohort_flush_seats_cache($cohort_id)
{
    wp_cache_delete('tfp_cohort_taken_' . absint($cohort_id), 'tfp_cohorts');
}
