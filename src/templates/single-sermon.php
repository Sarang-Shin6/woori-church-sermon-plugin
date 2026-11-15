<?php
/** Shared single-sermon renderer. Loaded only for Posts in the Sermons category. */
if (!defined('ABSPATH')) exit;
$template_id = absint(get_option('woori_sermons_elementor_template_id', 0));
if (!$template_id || !did_action('elementor/loaded')) {
    include get_query_template('single') ?: get_index_template();
    return;
}
get_header();
if (function_exists('elementor_theme_do_location') && elementor_theme_do_location('single')) {
    // An Elementor Pro Theme Builder single template takes precedence if one exists.
} else {
    $content = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($template_id, true);
    if ($content !== '') {
        echo '<main id="primary" class="site-main woori-shared-sermon-template">';
        echo $content; // Elementor renders the saved template and its widgets.
        echo '</main>';
    } else {
        while (have_posts()) { the_post(); the_content(); }
    }
}
get_footer();
