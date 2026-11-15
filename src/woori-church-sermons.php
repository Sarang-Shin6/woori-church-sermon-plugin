<?php
/**
 * Plugin Name: Woori Church Weekly Sermons
 * Description: Adds a dedicated Weekly Sermon admin workflow while storing sermons as normal WordPress Posts for compatibility with existing post grids and pagination. Includes structured sermon fields, Media Library uploads, automatic featured image handling, and Elementor-friendly shortcodes.
 * Version: 1.5.9
 * Author: Woori Church
 * License: GPL-2.0-or-later
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('WOORI_SERMONS_VERSION', '1.5.9');
define('WOORI_SERMONS_FILE', __FILE__);
define('WOORI_SERMONS_DIR', plugin_dir_path(__FILE__));
define('WOORI_SERMONS_URL', plugin_dir_url(__FILE__));

require_once WOORI_SERMONS_DIR . 'includes/meta.php';
require_once WOORI_SERMONS_DIR . 'includes/pdf-text.php';
require_once WOORI_SERMONS_DIR . 'includes/admin.php';
require_once WOORI_SERMONS_DIR . 'includes/shortcodes.php';
require_once WOORI_SERMONS_DIR . 'includes/template.php';

register_activation_hook(__FILE__, 'woori_sermons_activate');
function woori_sermons_activate() {
    $target = get_term_by('name', 'Service 예배', 'category');
    if (!$target) $target = get_term_by('slug', 'service', 'category');
    if (!$target) {
        $created = wp_insert_term('Service 예배', 'category', array('slug' => 'service'));
        if (!is_wp_error($created)) $target = get_term((int) $created['term_id'], 'category');
    }
    if (!$target || is_wp_error($target)) return;

    // Move existing sermon posts into Service 예배, preserving any unrelated categories.
    $old = get_term_by('slug', 'sermons', 'category');
    if ($old && (int) $old->term_id !== (int) $target->term_id) {
        $ids = get_posts(array('post_type' => 'post', 'post_status' => 'any',
            'posts_per_page' => -1, 'fields' => 'ids',
            'tax_query' => array(array('taxonomy' => 'category', 'field' => 'term_id', 'terms' => (int) $old->term_id))));
        foreach ($ids as $id) {
            wp_remove_object_terms($id, (int) $old->term_id, 'category');
            wp_set_post_terms($id, array((int) $target->term_id), 'category', true);
        }
    }
}

// Quick access from the WordPress admin toolbar, including the public site while logged in.
add_action('admin_bar_menu', function ($admin_bar) {
    if (!is_user_logged_in() || !current_user_can('edit_posts')) return;
    $admin_bar->add_node(array(
        'id'    => 'woori-add-weekly-sermon',
        'title' => 'Add New Weekly Sermon',
        'href'  => admin_url('admin.php?page=woori-add-sermon'),
        'meta'  => array('title' => 'Add New Weekly Sermon'),
    ));
}, 100);
