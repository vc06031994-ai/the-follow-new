<?php
if (!defined('ABSPATH')) exit;

/**
 * Register the custom post type for Reading Passages.
 */
add_action('init', function () {
    $labels = [
        'name'               => _x('Reading Passages', 'post type general name', 'tfp-dashboard'),
        'singular_name'      => _x('Reading Passage', 'post type singular name', 'tfp-dashboard'),
        'menu_name'          => _x('Readings', 'admin menu', 'tfp-dashboard'),
        'name_admin_bar'     => _x('Reading Passage', 'add new on admin bar', 'tfp-dashboard'),
        'add_new'            => _x('Add New', 'reading passage', 'tfp-dashboard'),
        'add_new_item'       => __('Add New Reading Passage', 'tfp-dashboard'),
        'new_item'           => __('New Reading Passage', 'tfp-dashboard'),
        'edit_item'          => __('Edit Reading Passage', 'tfp-dashboard'),
        'view_item'          => __('View Reading Passage', 'tfp-dashboard'),
        'all_items'          => __('All Reading Passages', 'tfp-dashboard'),
        'search_items'       => __('Search Reading Passages', 'tfp-dashboard'),
        'not_found'          => __('No reading passages found.', 'tfp-dashboard'),
        'not_found_in_trash' => __('No reading passages found in Trash.', 'tfp-dashboard')
    ];

    $args = [
        'labels'             => $labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => false,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => true, // Allow manual ordering using page attributes
        'menu_position'      => 58,
        'menu_icon'          => 'dashicons-book-alt',
        'supports'           => ['title', 'editor', 'page-attributes'],
        'show_in_rest'       => true,
    ];

    register_post_type('tfp_reading', $args);
});

/**
 * Meta Box for assigning a reading to a LearnDash Lesson (Week).
 */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'tfp_reading_lesson_metabox',
        __('Associated Week (Lesson)', 'tfp-dashboard'),
        'tfp_render_reading_lesson_metabox',
        'tfp_reading',
        'side',
        'high'
    );
});

function tfp_render_reading_lesson_metabox($post)
{
    wp_nonce_field('tfp_reading_lesson_nonce_action', 'tfp_reading_lesson_nonce');
    $selected_lesson = get_post_meta($post->ID, '_tfp_lesson_id', true);

    // Fetch all LearnDash lessons to populate the dropdown
    $lessons = get_posts([
        'post_type'      => 'sfwd-lessons',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC'
    ]);
    ?>
    <p>
        <label for="tfp_lesson_id"><strong><?php _e('Select the week this reading belongs to:', 'tfp-dashboard'); ?></strong></label>
        <br><br>
        <select name="tfp_lesson_id" id="tfp_lesson_id" style="width:100%;">
            <option value=""><?php _e('— Select a Week —', 'tfp-dashboard'); ?></option>
            <?php foreach ($lessons as $lesson) : ?>
                <option value="<?php echo esc_attr($lesson->ID); ?>" <?php selected($selected_lesson, $lesson->ID); ?>>
                    <?php echo esc_html($lesson->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php
}

add_action('save_post_tfp_reading', function ($post_id) {
    if (!isset($_POST['tfp_reading_lesson_nonce']) || !wp_verify_nonce($_POST['tfp_reading_lesson_nonce'], 'tfp_reading_lesson_nonce_action')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['tfp_lesson_id'])) {
        update_post_meta($post_id, '_tfp_lesson_id', absint($_POST['tfp_lesson_id']));
    }
});
