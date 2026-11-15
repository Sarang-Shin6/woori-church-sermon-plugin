<?php
if (!defined('ABSPATH')) exit;

/** Shared Elementor template support for sermon posts. */
add_action('admin_menu', function () {
    add_submenu_page(
        'woori-weekly-sermons',
        'Sermon Template Settings',
        'Template Settings',
        'manage_options',
        'woori-sermon-template',
        'woori_sermons_template_settings_page'
    );
});

function woori_sermons_template_settings_page() {
    if (!current_user_can('manage_options')) wp_die('You do not have permission to manage sermon templates.');
    if (isset($_POST['woori_sermon_template_save'])) {
        check_admin_referer('woori_sermon_template_settings', 'woori_sermon_template_nonce');
        update_option('woori_sermons_elementor_template_id', absint($_POST['woori_sermons_elementor_template_id'] ?? 0));
        echo '<div class="notice notice-success is-dismissible"><p>Template settings saved.</p></div>';
    }
    $selected = absint(get_option('woori_sermons_elementor_template_id', 0));
    $templates = get_posts(array(
        'post_type' => 'elementor_library',
        'post_status' => array('publish', 'draft', 'private'),
        'posts_per_page' => 200,
        'orderby' => 'title',
        'order' => 'ASC',
    ));
    ?>
    <div class="wrap">
      <h1>Sermon Template Settings</h1>
      <p>Choose a saved Elementor template to display on Sermons-category posts. The sermon title shortcode uses the WordPress post title, the date shortcode uses the saved sermon date, the WordPress excerpt is synced to that same formatted date, and the featured image is synced to Order of Service Page 1. Other WordPress posts and their existing layouts are not changed.</p>
      <?php if (!did_action('elementor/loaded')): ?><div class="notice notice-warning"><p>Elementor does not appear to be active. Activate Elementor before using this feature.</p></div><?php endif; ?>
      <form method="post">
        <?php wp_nonce_field('woori_sermon_template_settings', 'woori_sermon_template_nonce'); ?>
        <table class="form-table"><tr><th scope="row"><label for="woori_sermons_elementor_template_id">Shared sermon template</label></th><td>
          <select name="woori_sermons_elementor_template_id" id="woori_sermons_elementor_template_id" class="regular-text">
            <option value="0">— Disabled (use normal post layout) —</option>
            <?php foreach ($templates as $template): ?>
              <option value="<?php echo esc_attr($template->ID); ?>" <?php selected($selected, $template->ID); ?>><?php echo esc_html($template->post_title . ' (ID: ' . $template->ID . ')'); ?></option>
            <?php endforeach; ?>
          </select>
          <p class="description">Create this first in Templates → Saved Templates in Elementor. Select the template here after saving it.</p>
        </td></tr></table>
        <?php submit_button('Save Template Settings', 'primary', 'woori_sermon_template_save'); ?>
      </form>
      <hr><h2>Shortcodes for the shared template</h2>
      <p>Use Elementor’s Shortcode widget and insert the relevant shortcode:</p>
      <ul>
        <li><code>[current_sermon_title]</code></li><li><code>[current_sermon_date]</code></li>
        <li><code>[current_sermon_speaker]</code></li><li><code>[current_sermon_verse]</code></li>
        <li><code>[current_sermon_video]</code></li><li><code>[current_sermon_order_1]</code></li>
        <li><code>[current_sermon_order_2]</code></li><li><code>[current_sermon_order_carousel]</code> (both images in one carousel)</li>
      </ul>
    </div>
    <?php
}

add_filter('template_include', function ($template) {
    if (!is_singular('post') || !woori_sermons_is_sermon(get_queried_object_id())) return $template;
    $elementor_template_id = absint(get_option('woori_sermons_elementor_template_id', 0));
    if (!$elementor_template_id || !did_action('elementor/loaded')) return $template;
    $elementor_template = get_post($elementor_template_id);
    if (!$elementor_template || $elementor_template->post_type !== 'elementor_library') return $template;
    return WOORI_SERMONS_DIR . 'templates/single-sermon.php';
}, 99);
