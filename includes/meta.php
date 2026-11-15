<?php
if (!defined('ABSPATH')) exit;

const WOORI_SERMON_META_DATE = '_woori_sermon_date';
const WOORI_SERMON_META_SPEAKER = '_woori_sermon_speaker';
const WOORI_SERMON_META_VERSE = '_woori_sermon_verse';
const WOORI_SERMON_META_YOUTUBE = '_woori_sermon_youtube';
const WOORI_SERMON_META_ORDER_1 = '_woori_sermon_order_1';
const WOORI_SERMON_META_ORDER_2 = '_woori_sermon_order_2';

function woori_sermons_category_id() {
    // Prefer the existing category by its exact display name, preserving its slug.
    $term = get_term_by('name', 'Service 예배', 'category');
    if (!$term) $term = get_term_by('slug', 'service', 'category');
    return $term ? (int) $term->term_id : 0;
}

function woori_sermons_is_sermon($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'post') return false;
    $cat_id = woori_sermons_category_id();
    return $cat_id ? has_category($cat_id, $post_id) : false;
}

function woori_sermons_get($post_id, $key, $default = '') {
    $value = get_post_meta($post_id, $key, true);
    return $value !== '' ? $value : $default;
}

function woori_sermons_save_meta($post_id, $data) {
    $date = isset($data['sermon_date']) ? sanitize_text_field($data['sermon_date']) : '';
    $speaker = isset($data['speaker']) ? sanitize_text_field($data['speaker']) : '';
    $verse = isset($data['verse']) ? sanitize_text_field($data['verse']) : '';
    $youtube = isset($data['youtube']) ? esc_url_raw(trim($data['youtube'])) : '';
    $order1 = isset($data['order_1']) ? absint($data['order_1']) : 0;
    $order2 = isset($data['order_2']) ? absint($data['order_2']) : 0;

    if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        update_post_meta($post_id, WOORI_SERMON_META_DATE, $date);
        // Keep the native WordPress excerpt/description in sync with the date shortcode output.
        $formatted_date = function_exists('woori_sermons_format_date') ? woori_sermons_format_date($date) : $date;
        if (get_post_field('post_excerpt', $post_id) !== $formatted_date) {
            wp_update_post(wp_slash(array('ID' => $post_id, 'post_excerpt' => $formatted_date)));
        }
    } else {
        delete_post_meta($post_id, WOORI_SERMON_META_DATE);
    }

    update_post_meta($post_id, WOORI_SERMON_META_SPEAKER, $speaker);
    update_post_meta($post_id, WOORI_SERMON_META_VERSE, $verse);
    update_post_meta($post_id, WOORI_SERMON_META_YOUTUBE, $youtube);

    if ($order1) update_post_meta($post_id, WOORI_SERMON_META_ORDER_1, $order1);
    else delete_post_meta($post_id, WOORI_SERMON_META_ORDER_1);

    if ($order2) update_post_meta($post_id, WOORI_SERMON_META_ORDER_2, $order2);
    else delete_post_meta($post_id, WOORI_SERMON_META_ORDER_2);

    // Page 1 of the printed order of service is the card/featured image.
    if ($order1 && wp_attachment_is_image($order1)) {
        set_post_thumbnail($post_id, $order1);
    }
}

add_action('add_meta_boxes', function () {
    add_meta_box(
        'woori_sermon_details',
        'Sermon Details',
        'woori_sermons_metabox_html',
        'post',
        'normal',
        'high'
    );
});

function woori_sermons_metabox_html($post) {
    wp_nonce_field('woori_sermon_meta_save', 'woori_sermon_meta_nonce');
    $date = woori_sermons_get($post->ID, WOORI_SERMON_META_DATE, get_the_date('Y-m-d', $post));
    $speaker = woori_sermons_get($post->ID, WOORI_SERMON_META_SPEAKER);
    $verse = woori_sermons_get($post->ID, WOORI_SERMON_META_VERSE);
    $youtube = woori_sermons_get($post->ID, WOORI_SERMON_META_YOUTUBE);
    $order1 = absint(woori_sermons_get($post->ID, WOORI_SERMON_META_ORDER_1));
    $order2 = absint(woori_sermons_get($post->ID, WOORI_SERMON_META_ORDER_2));
    woori_sermons_render_fields($date, $speaker, $verse, $youtube, $order1, $order2, 'metabox');
}

function woori_sermons_render_fields($date, $speaker, $verse, $youtube, $order1, $order2, $context = 'admin') {
    ?>
    <div class="woori-sermon-fields">
        <div class="woori-sermon-field-grid">
            <p>
                <label for="woori_sermon_date"><strong>Sermon Date</strong></label>
                <input type="date" id="woori_sermon_date" name="woori_sermon_date" value="<?php echo esc_attr($date); ?>" class="regular-text">
            </p>
            <p>
                <label for="woori_sermon_speaker"><strong>Speaker</strong></label>
                <input type="text" id="woori_sermon_speaker" name="woori_sermon_speaker" value="<?php echo esc_attr($speaker); ?>" class="regular-text" placeholder="e.g. 김○○ 목사">
            </p>
        </div>
        <p>
            <label for="woori_sermon_verse"><strong>Bible Verse</strong></label>
            <input type="text" id="woori_sermon_verse" name="woori_sermon_verse" value="<?php echo esc_attr($verse); ?>" class="large-text" placeholder="e.g. 시편 23:1">
        </p>
        <p>
            <label for="woori_sermon_youtube"><strong>YouTube URL</strong></label>
            <input type="url" id="woori_sermon_youtube" name="woori_sermon_youtube" value="<?php echo esc_attr($youtube); ?>" class="large-text" placeholder="https://www.youtube.com/watch?v=...">
        </p>

        <div class="woori-sermon-images">
            <?php woori_sermons_image_field('woori_sermon_order_1', 'Order of Service — Page 1', $order1); ?>
            <?php woori_sermons_image_field('woori_sermon_order_2', 'Order of Service — Page 2', $order2); ?>
        </div>
        <p class="description">Page 1 is automatically used as the WordPress Featured Image, so your existing Ultimate Post Kit card thumbnails can continue working.</p>
    </div>
    <?php
}

function woori_sermons_image_field($field, $label, $attachment_id) {
    $image = $attachment_id ? wp_get_attachment_image($attachment_id, 'medium', false, array('class' => 'woori-sermon-preview')) : '';
    ?>
    <div class="woori-sermon-image-field">
        <strong><?php echo esc_html($label); ?></strong>
        <div class="woori-sermon-image-preview" data-preview-for="<?php echo esc_attr($field); ?>">
            <?php echo $image ?: '<span>No image selected</span>'; ?>
        </div>
        <input type="hidden" name="<?php echo esc_attr($field); ?>" id="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr($attachment_id); ?>">
        <button type="button" class="button woori-upload-image" data-target="<?php echo esc_attr($field); ?>">Select / Upload Image</button>
        <button type="button" class="button-link-delete woori-remove-image" data-target="<?php echo esc_attr($field); ?>" <?php disabled(!$attachment_id); ?>>Remove</button>
    </div>
    <?php
}

add_action('save_post_post', function ($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!isset($_POST['woori_sermon_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woori_sermon_meta_nonce'])), 'woori_sermon_meta_save')) return;

    $data = array(
        'sermon_date' => isset($_POST['woori_sermon_date']) ? wp_unslash($_POST['woori_sermon_date']) : '',
        'speaker' => isset($_POST['woori_sermon_speaker']) ? wp_unslash($_POST['woori_sermon_speaker']) : '',
        'verse' => isset($_POST['woori_sermon_verse']) ? wp_unslash($_POST['woori_sermon_verse']) : '',
        'youtube' => isset($_POST['woori_sermon_youtube']) ? wp_unslash($_POST['woori_sermon_youtube']) : '',
        'order_1' => isset($_POST['woori_sermon_order_1']) ? wp_unslash($_POST['woori_sermon_order_1']) : 0,
        'order_2' => isset($_POST['woori_sermon_order_2']) ? wp_unslash($_POST['woori_sermon_order_2']) : 0,
    );

    woori_sermons_save_meta($post_id, $data);

    // If a Sermon Details box was used on an existing post, make sure it belongs to Sermons.
    if (!wp_is_post_revision($post_id) && isset($_POST['woori_make_sermon']) && $_POST['woori_make_sermon'] === '1') {
        $cat_id = woori_sermons_category_id();
        if ($cat_id) wp_set_post_categories($post_id, array($cat_id), true);
    }
}, 10, 3);
