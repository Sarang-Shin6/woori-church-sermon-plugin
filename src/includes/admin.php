<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_menu_page(
        'Weekly Sermons',
        'Weekly Sermons',
        'edit_posts',
        'woori-weekly-sermons',
        'woori_sermons_admin_page',
        'dashicons-microphone',
        25
    );
    add_submenu_page('woori-weekly-sermons', 'All Sermons', 'All Sermons', 'edit_posts', 'woori-weekly-sermons', 'woori_sermons_admin_page');
    add_submenu_page('woori-weekly-sermons', 'Add Weekly Sermon', 'Add Weekly Sermon', 'edit_posts', 'woori-add-sermon', 'woori_sermons_add_page');
    add_submenu_page('woori-weekly-sermons', 'YouTube API Settings', 'YouTube API Settings', 'manage_options', 'woori-youtube-settings', 'woori_sermons_youtube_settings_page');
});

add_action('admin_enqueue_scripts', function ($hook) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $is_plugin_page = in_array($hook, array('toplevel_page_woori-weekly-sermons', 'weekly-sermons_page_woori-add-sermon'), true);
    $is_post_edit = $screen && $screen->post_type === 'post' && in_array($screen->base, array('post', 'post-new'), true);
    if (!$is_plugin_page && !$is_post_edit) return;

    wp_enqueue_media();
    wp_enqueue_script('woori-sermons-admin', WOORI_SERMONS_URL . 'assets/admin.js', array('jquery'), WOORI_SERMONS_VERSION, true);
    // The script must be registered/enqueued BEFORE attaching inline settings.
    // Otherwise wp_add_inline_script silently fails and YouTube lookup never runs.
    if ($hook === 'weekly-sermons_page_woori-add-sermon') {
        wp_add_inline_script('woori-sermons-admin', 'window.wooriYouTubeSettings = ' . wp_json_encode(array('ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('woori_youtube_feed'))) . ';', 'before');
    }
    wp_enqueue_style('woori-sermons-admin', WOORI_SERMONS_URL . 'assets/admin.css', array(), WOORI_SERMONS_VERSION);
});

// Trash sermons using WordPress' native recoverable Trash (not permanent deletion).
add_action('admin_post_woori_trash_sermon', function () {
    $id = absint($_POST['sermon_id'] ?? 0);
    if (!$id || !current_user_can('delete_post', $id)) wp_die('You cannot trash this sermon.');
    check_admin_referer('woori_trash_sermon_' . $id);
    $post = get_post($id);
    $cat = woori_sermons_category_id();
    if (!$post || $post->post_type !== 'post' || !$cat || !has_category($cat, $id)) wp_die('Invalid sermon.');
    $trashed = wp_trash_post($id);
    $url = admin_url('admin.php?page=woori-weekly-sermons');
    wp_safe_redirect(add_query_arg('woori_trashed', $trashed ? '1' : '0', $url));
    exit;
});

function woori_sermons_admin_page() {
    if (!current_user_can('edit_posts')) wp_die('You do not have permission to manage sermons.');
    $cat_id = woori_sermons_category_id();
    $paged = max(1, absint($_GET['paged'] ?? 1));
    $q = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
        'posts_per_page' => 20,
        'paged' => $paged,
        'cat' => $cat_id,
        'orderby' => 'date',
        'order' => 'DESC',
    ));
    ?>
    <div class="wrap woori-sermons-admin-wrap">
        <div class="woori-sermons-header">
            <div>
                <h1>Weekly Sermons</h1>
                <p>Create and manage your weekly sermon posts from one simple form.</p>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=woori-add-sermon')); ?>" class="button button-primary button-hero">+ Add Weekly Sermon</a>
        </div>
        <?php if (isset($_GET['woori_trashed'])): ?>
            <div class="notice <?php echo $_GET['woori_trashed'] === '1' ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo $_GET['woori_trashed'] === '1' ? 'Sermon moved to Trash. You can restore it from Posts → Trash.' : 'Could not move sermon to Trash.'; ?></p></div>
        <?php endif; ?>
        <?php if (!$cat_id): ?>
            <div class="notice notice-error"><p>The Service 예배 category could not be found. Deactivate/reactivate the plugin to recreate it.</p></div>
        <?php endif; ?>
        <table class="wp-list-table widefat fixed striped woori-sermon-table">
            <thead><tr><th style="width:90px">Image</th><th>Title</th><th style="width:130px">Sermon Date</th><th style="width:180px">Speaker</th><th style="width:120px">Status</th><th style="width:180px">Actions</th></tr></thead>
            <tbody>
            <?php if ($q->have_posts()): while ($q->have_posts()): $q->the_post(); $id = get_the_ID(); ?>
                <tr>
                    <td><?php echo get_the_post_thumbnail($id, array(70, 70), array('class' => 'woori-admin-thumb')); ?></td>
                    <td><strong><?php echo esc_html(get_the_title()); ?></strong></td>
                    <td><?php echo esc_html(woori_sermons_format_date(woori_sermons_get($id, WOORI_SERMON_META_DATE, get_the_date('Y-m-d', $id)))); ?></td>
                    <td><?php echo esc_html(woori_sermons_get($id, WOORI_SERMON_META_SPEAKER)); ?></td>
                    <td><?php echo esc_html(ucfirst(get_post_status($id))); ?></td>
                    <td><a href="<?php echo esc_url(add_query_arg(array('page'=>'woori-add-sermon','sermon_id'=>$id), admin_url('admin.php'))); ?>">Edit Sermon</a> &nbsp; | &nbsp; <a href="<?php echo esc_url(get_permalink($id)); ?>" target="_blank" rel="noopener">View</a>
                        <?php if (current_user_can('delete_post', $id)): ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline" onsubmit="return confirm('Move this sermon to Trash? You can restore it from Posts → Trash.');">
                            <input type="hidden" name="action" value="woori_trash_sermon">
                            <input type="hidden" name="sermon_id" value="<?php echo esc_attr($id); ?>">
                            <?php wp_nonce_field('woori_trash_sermon_' . $id); ?>
                            &nbsp; | &nbsp; <button type="submit" class="button-link-delete" style="border:0;background:none;padding:0;cursor:pointer;color:#b32d2e;">Trash</button>
                        </form>
                        <?php endif; ?></td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="6">No sermon posts found yet.</td></tr>
            <?php endif; wp_reset_postdata(); ?>
            </tbody>
        </table>
        <?php
        $big = 999999999;
        echo '<div class="tablenav bottom"><div class="tablenav-pages">' . wp_kses_post(paginate_links(array(
            'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
            'format' => '?paged=%#%', 'current' => $paged, 'total' => max(1, (int) $q->max_num_pages), 'type' => 'plain',
        ))) . '</div></div>';
        ?>
    </div>
    <?php
}

/** Normalise comma-separated verse segments, e.g. 1, 9-12. */
function woori_sermons_normalize_verse_segments($input) {
    $input = trim((string) $input);
    $input = str_replace(array('–', '—', '〜', '～', '~', '，', '、'), array('-', '-', '-', '-', '-', ',', ','), $input);
    $input = preg_replace('/\s*[-]\s*/u', '-', $input);
    $input = preg_replace('/\s*,\s*/u', ', ', $input);
    return trim($input);
}

function woori_sermons_valid_verse_segments($input) {
    return (bool) preg_match('/^\d{1,3}(?:-\d{1,3})?(?:,\s*\d{1,3}(?:-\d{1,3})?)*$/u', $input);
}

/** Add 절 to each segment, not just to the end of the whole reference. */
function woori_sermons_format_verse_segments($input) {
    $input = woori_sermons_normalize_verse_segments($input);
    if (!woori_sermons_valid_verse_segments($input)) return '';
    return implode(', ', array_map(function ($part) { return trim($part) . '절'; }, explode(',', $input)));
}

function woori_sermons_verse_parts($verse) {
    $verse = trim((string) $verse);
    $parts = array('book'=>'', 'chapter'=>'', 'verses'=>'', 'chapter_suffix'=>'장', 'verse_suffix'=>'절');
    if (preg_match('/^(.+?)\s*(\d+)\s*(장|편)\s*(.+?)\s*(절)(?:\s*주일\s*예배)?$/u', $verse, $m)) {
        $parts = array('book'=>trim($m[1]), 'chapter'=>$m[2], 'verses'=>woori_sermons_normalize_verse_segments(preg_replace('/절/u', '', $m[4])), 'chapter_suffix'=>$m[3], 'verse_suffix'=>$m[5]);
    }
    return $parts;
}

function woori_sermons_add_page() {
    if (!current_user_can('edit_posts')) wp_die('You do not have permission to add sermons.');

    $editing_id = absint($_GET['sermon_id'] ?? $_POST['woori_edit_sermon_id'] ?? 0);
    $editing_post = $editing_id ? get_post($editing_id) : null;
    if ($editing_id && (!$editing_post || $editing_post->post_type !== 'post' || !woori_sermons_is_sermon($editing_id) || !current_user_can('edit_post', $editing_id))) wp_die('You cannot edit this sermon.');
    $values = array(
        'title' => '',
        'sermon_date' => current_time('Y-m-d'),
        'speaker' => '김경천 목사',
        'verse' => '',
        'youtube' => '',
        'order_1' => 0,
        'order_2' => 0,
    );
    if ($editing_post) {
        $values = array(
            'title' => $editing_post->post_title,
            'sermon_date' => woori_sermons_get($editing_id, WOORI_SERMON_META_DATE, get_the_date('Y-m-d', $editing_id)),
            'speaker' => woori_sermons_get($editing_id, WOORI_SERMON_META_SPEAKER),
            'verse' => woori_sermons_get($editing_id, WOORI_SERMON_META_VERSE),
            'youtube' => woori_sermons_get($editing_id, WOORI_SERMON_META_YOUTUBE),
            'order_1' => absint(woori_sermons_get($editing_id, WOORI_SERMON_META_ORDER_1)),
            'order_2' => absint(woori_sermons_get($editing_id, WOORI_SERMON_META_ORDER_2)),
        );
    }
    $errors = array();

    if (isset($_POST['woori_add_sermon_submit'])) {
        check_admin_referer('woori_add_sermon', 'woori_add_sermon_nonce');
        $values['title'] = sanitize_text_field(wp_unslash($_POST['woori_sermon_title'] ?? ''));
        $values['sermon_date'] = sanitize_text_field(wp_unslash($_POST['woori_sermon_date'] ?? ''));
        $values['speaker'] = sanitize_text_field(wp_unslash($_POST['woori_sermon_speaker'] ?? ''));
        $book = sanitize_text_field(wp_unslash($_POST['woori_verse_book'] ?? ''));
        $chapter = absint($_POST['woori_verse_chapter'] ?? 0);
        $verses = sanitize_text_field(wp_unslash($_POST['woori_verse_verses'] ?? ''));
        $verses = woori_sermons_normalize_verse_segments($verses);
        $chapter_suffix = sanitize_text_field(wp_unslash($_POST['woori_verse_chapter_suffix'] ?? '장'));
        $verse_suffix = '절';
        if ($chapter_suffix === '') $chapter_suffix = ($book === '시편' ? '편' : '장');
        $values['verse'] = ($book && $chapter && woori_sermons_valid_verse_segments($verses)) ? $book . ' ' . $chapter . $chapter_suffix . ' ' . woori_sermons_format_verse_segments($verses) . '  주일 예배' : '';
        $values['youtube'] = esc_url_raw(wp_unslash($_POST['woori_sermon_youtube'] ?? ''));
        $values['order_1'] = absint($_POST['woori_sermon_order_1'] ?? 0);
        $values['order_2'] = absint($_POST['woori_sermon_order_2'] ?? 0);
        $publish = isset($_POST['woori_publish']) && $_POST['woori_publish'] === '1';

        if ($values['title'] === '') $errors[] = 'Sermon title is required.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['sermon_date'])) $errors[] = 'Please enter a valid sermon date.';
        if ($values['speaker'] === '') $errors[] = 'Speaker is required.';
        if (($book || $chapter || $verses) && !$values['verse']) $errors[] = 'Enter a valid Bible book, chapter and verse range (e.g. 1, 9-12).';
        $has_pdf = !empty($_FILES['woori_sermon_pdf']['name']);
        if (!$has_pdf && !$values['order_1']) $errors[] = 'Please upload/select Order of Service Page 1.';
        if (!$has_pdf && !$values['order_2']) $errors[] = 'Please upload/select Order of Service Page 2.';
        $converted_ids = array();
        if (!$errors && $has_pdf) {
            $conversion = woori_sermons_convert_pdf($_FILES['woori_sermon_pdf']);
            if (is_wp_error($conversion)) $errors[] = $conversion->get_error_message();
            else {
                $converted_ids = $conversion;
                $values['order_1'] = $conversion[0];
                $values['order_2'] = $conversion[1];
            }
        }
        if ($values['youtube'] && !woori_sermons_is_youtube_url($values['youtube'])) $errors[] = 'Please enter a valid YouTube URL.';

        if (!$errors) {
            // Publish at the current WordPress-site local time, not sermon-date noon.
            $post_date_local = current_time('mysql');
            $post_date_gmt = get_gmt_from_date($post_date_local);
            $postarr = array(
                'post_title' => $values['title'],
                // Keep the native WordPress excerpt/description identical to the formatted sermon-date shortcode.
                'post_excerpt' => woori_sermons_format_date($values['sermon_date']),
                'post_status' => $publish ? 'publish' : 'draft',
                'post_type' => 'post',
                'post_author' => get_current_user_id(),
                'post_date' => $post_date_local,
                'post_date_gmt' => $post_date_gmt,
            );
            if ($editing_id) {
                $postarr['ID'] = $editing_id;
                $postarr['post_status'] = $publish ? 'publish' : 'draft';
                // Preserve the existing publication timestamp when editing.
                unset($postarr['post_date'], $postarr['post_date_gmt'], $postarr['post_author']);
                $post_id = wp_update_post(wp_slash($postarr), true);
            } else {
                $post_id = wp_insert_post(wp_slash($postarr), true);
            }
            if (is_wp_error($post_id)) {
                $errors[] = 'The sermon could not be created: ' . $post_id->get_error_message();
                foreach ($converted_ids as $attachment_id) wp_delete_attachment($attachment_id, true);
            } else {
                $cat_id = woori_sermons_category_id();
                if ($cat_id) wp_set_post_categories($post_id, array($cat_id), false);
                woori_sermons_save_meta($post_id, $values);
                $redirect = add_query_arg(array('page' => 'woori-weekly-sermons', 'woori_created' => 1), admin_url('admin.php'));
                wp_safe_redirect($redirect);
                exit;
            }
        }
    }
    ?>
    <div class="wrap woori-sermons-admin-wrap">
        <div class="woori-sermons-header">
            <div><h1><?php echo $editing_id ? 'Edit Weekly Sermon' : 'Add Weekly Sermon'; ?></h1><p>Enter the week's information once. The plugin creates a normal WordPress Post compatible with your existing Services page and Ultimate Post Kit.</p></div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=woori-weekly-sermons')); ?>" class="button">← All Sermons</a>
        </div>
        <?php foreach ($errors as $error): ?><div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div><?php endforeach; ?>
        <form method="post" enctype="multipart/form-data" class="woori-sermon-form">
            <?php wp_nonce_field('woori_add_sermon', 'woori_add_sermon_nonce'); ?>
            <?php if ($editing_id): ?><input type="hidden" name="woori_edit_sermon_id" value="<?php echo esc_attr($editing_id); ?>"><?php endif; ?>
            <div class="woori-sermon-panel woori-pdf-first">
                <h2>Upload Order of Service PDF</h2>
                <p class="description">Start here: upload your two-page bulletin, then read its sermon details and preview both pages before publishing.</p>
                <p><label for="woori_sermon_pdf"><strong>Order of Service PDF (2 pages)</strong></label><br>
                    <input type="file" id="woori_sermon_pdf" name="woori_sermon_pdf" accept="application/pdf,.pdf"></p>
                <p><button type="button" id="woori-prefill-pdf" class="button button-secondary">Read PDF &amp; Prefill Fields</button> <span id="woori-prefill-status" role="status" aria-live="polite"></span></p>
                <p class="description">Review the extracted details before publishing. This does not upload or publish the sermon. Uses the built-in PHP text extractor (no pdftotext needed). PDF-to-PNG conversion remains separate.</p>
                <p class="description">Optional: Upload one 2-page PDF to automatically create both PNG images. This takes priority over the individual image fields below. Requires Imagick and PDF rendering support on your server.</p>
                <div id="woori-pdf-previews" class="woori-pdf-previews" hidden aria-live="polite">
                    <div><strong>Page 1 — Featured Image</strong><div class="woori-pdf-preview-image" id="woori-pdf-preview-1"></div></div>
                    <div><strong>Page 2</strong><div class="woori-pdf-preview-image" id="woori-pdf-preview-2"></div></div>
                </div>
                <p id="woori-pdf-preview-status" class="description" role="status" aria-live="polite"></p>
                <p class="description">These are temporary previews. The final full-resolution PNGs are created and added to the Media Library when you publish or save the sermon.</p>
            </div>
            <div class="woori-sermon-panel">
                <h2>Sermon Information</h2>
                <p><label for="woori_sermon_title"><strong>Sermon Title *</strong></label><input required type="text" id="woori_sermon_title" name="woori_sermon_title" value="<?php echo esc_attr($values['title']); ?>" class="large-text" placeholder="e.g. 사역의 성공보다 귀한 것"></p>
                <div class="woori-sermon-field-grid">
                    <p><label for="woori_sermon_date"><strong>Sermon Date *</strong></label><input required type="date" id="woori_sermon_date" name="woori_sermon_date" value="<?php echo esc_attr($values['sermon_date']); ?>"></p>
                    <p><label for="woori_sermon_speaker"><strong>Speaker *</strong></label><input required type="text" id="woori_sermon_speaker" name="woori_sermon_speaker" value="<?php echo esc_attr($values['speaker']); ?>" placeholder="e.g. 김○○ 목사"></p>
                </div>
            </div>
            <div class="woori-sermon-panel">
                <h2>Bible Passage</h2>
                <?php $verse_parts = woori_sermons_verse_parts($values['verse']); ?>
                <div class="woori-sermon-bible-fields">
                    <p><label for="woori_verse_book"><strong>Book</strong></label><input type="text" id="woori_verse_book" name="woori_verse_book" value="<?php echo esc_attr($verse_parts['book']); ?>" placeholder="여호수아"></p>
                    <div><label for="woori_verse_chapter"><strong>Chapter</strong></label><div style="display:flex;align-items:center;gap:8px;"><input style="min-width:0;flex:1;" type="number" min="1" id="woori_verse_chapter" name="woori_verse_chapter" value="<?php echo esc_attr($verse_parts['chapter']); ?>" placeholder="24"><input style="width:78px;flex:none;" type="text" id="woori_verse_chapter_suffix" name="woori_verse_chapter_suffix" aria-label="Chapter suffix" value="<?php echo esc_attr($verse_parts['chapter_suffix']); ?>" maxlength="8"></div></div>
                    <div><label for="woori_verse_verses"><strong>Verse(s)</strong></label><div style="display:flex;align-items:center;gap:8px;"><input style="min-width:0;flex:1;" type="text" id="woori_verse_verses" name="woori_verse_verses" value="<?php echo esc_attr($verse_parts['verses']); ?>" placeholder="1, 9-12"></div></div>
                </div>
                <p><strong>Generated Bible Passage</strong></p>
                <p id="woori-verse-preview" aria-live="polite" style="padding:12px 14px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:5px;font-size:15px;"></p>
            </div>
            <div class="woori-sermon-panel">
                <h2>YouTube Video</h2>
                <p><label for="woori_sermon_youtube"><strong>YouTube URL</strong></label><input type="url" id="woori_sermon_youtube" name="woori_sermon_youtube" value="<?php echo esc_attr($values['youtube']); ?>" class="large-text" placeholder="https://www.youtube.com/watch?v=..."></p>
                <p><button type="button" class="button button-secondary" id="woori-youtube-refresh">Find Video for Service Date</button> <span id="woori-youtube-status" role="status" aria-live="polite"></span></p>
                <div id="woori-youtube-preview" class="woori-youtube-preview" aria-label="YouTube video preview"><p class="description">Enter or fetch a YouTube URL to preview it here.</p></div>
                <p class="description">For past services, the plugin checks recent feed entries for the service date (using the WordPress site timezone); for today or no match, it uses the latest video. The public feed may lag behind an active livestream. If it is missing, paste the livestream URL manually; the preview will update.</p>
            </div>
            <details class="woori-sermon-panel woori-sermon-optional-details">
                <summary class="woori-sermon-optional-summary"><span class="woori-sermon-optional-title">Order of Service</span> <span class="woori-sermon-optional-note">(Optional — use if PDF-to-PNG conversion fails)</span></summary>
                <p class="description">Upload the two sides/pages of the printed order of service. Both images are added to the WordPress Media Library automatically. Page 1 will also become the post's Featured Image for your existing Ultimate Post Kit cards.</p>
                <p class="description">Alternatively, select existing page images from the Media Library.</p>
                <div class="woori-sermon-images">
                    <?php woori_sermons_image_field('woori_sermon_order_1', 'Order of Service — Page 1', $values['order_1']); ?>
                    <?php woori_sermons_image_field('woori_sermon_order_2', 'Order of Service — Page 2', $values['order_2']); ?>
                </div>
            </details>
            <div class="woori-sermon-actions">
                <button type="submit" name="woori_add_sermon_submit" value="1" class="button button-primary button-hero"><span class="dashicons dashicons-yes"></span> <?php echo $editing_id ? 'Update & Publish' : 'Publish Sermon'; ?></button>
                <button type="submit" name="woori_add_sermon_submit" value="1" class="button button-secondary button-hero" onclick="this.form.insertAdjacentHTML('beforeend','<input type=\'hidden\' name=\'woori_publish\' value=\'0\'>')"><?php echo $editing_id ? 'Update as Draft' : 'Save Draft'; ?></button>
                <input type="hidden" name="woori_publish" value="1">
            </div>
        </form>
        <script>
        (function(){
          const button=document.getElementById('woori-prefill-pdf');
          const status=document.getElementById('woori-prefill-status');
          const input=document.getElementById('woori_sermon_pdf');
          const previewBox=document.getElementById('woori-pdf-previews');
          const previewStatus=document.getElementById('woori-pdf-preview-status');
          let previewRequest=0;
          async function previewPages(){
            const file=input.files[0];
            const seq=++previewRequest;
            previewBox.hidden=true;
            document.getElementById('woori-pdf-preview-1').replaceChildren();
            document.getElementById('woori-pdf-preview-2').replaceChildren();
            if(!file){previewStatus.textContent='';return;}
            previewStatus.textContent='Generating page previews…';
            const data=new FormData();
            data.append('action','woori_preview_pdf_pages');
            data.append('nonce',<?php echo wp_json_encode(wp_create_nonce('woori_preview_pdf_pages')); ?>);
            data.append('pdf',file);
            try{
              const response=await fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,{method:'POST',body:data,credentials:'same-origin'});
              const result=await response.json();
              if(seq!==previewRequest)return;
              if(!result.success)throw new Error(result.data?.message || 'Could not generate PDF previews.');
              result.data.pages.forEach((src,i)=>{
                const img=document.createElement('img');img.src=src;img.alt='Order of service page '+(i+1);
                document.getElementById('woori-pdf-preview-'+(i+1)).appendChild(img);
              });
              previewBox.hidden=false;
              previewStatus.textContent='Both pages are ready for review.';
            }catch(e){if(seq===previewRequest)previewStatus.textContent=e.message;}
          }
          input.addEventListener('change',previewPages);
          let prefillRequest=0;
          async function readAndPrefill(){
            const seq=++prefillRequest;
            const file=document.getElementById('woori_sermon_pdf').files[0];
            if(!file){status.textContent='Please select a PDF first.';return;}
            button.disabled=true;status.textContent='Reading PDF…';
            const data=new FormData();data.append('action','woori_prefill_pdf');data.append('nonce',<?php echo wp_json_encode(wp_create_nonce('woori_prefill_pdf')); ?>);data.append('pdf',file);
            try{
              const response=await fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,{method:'POST',body:data,credentials:'same-origin'});
              const result=await response.json();
              if(seq!==prefillRequest)return;
              if(!result.success)throw new Error(result.data?.message || 'Could not read PDF.');
              const map={title:'woori_sermon_title',sermon_date:'woori_sermon_date',speaker:'woori_sermon_speaker'};
              let count=0;
              Object.keys(map).forEach(key=>{if(result.data[key]){document.getElementById(map[key]).value=result.data[key];count++;}});
              if(result.data.sermon_date){document.getElementById('woori_sermon_date').dispatchEvent(new Event('change'));}
              if(result.data.verse){
                const v=result.data.verse.match(/^(.+?)\s*(\d+)\s*(장|편)\s*(.+?)\s*(절)/u) || result.data.verse.match(/^(.+?)\s*(\d+)\s*[:：]\s*(\d+(?:\s*[-~–]\s*\d+)?(?:\s*[,，]\s*\d+(?:\s*[-~–]\s*\d+)?)*)$/u);
                if(v){document.getElementById('woori_verse_book').value=v[1].trim();document.getElementById('woori_verse_chapter').value=v[2];document.getElementById('woori_verse_chapter_suffix').value=(v[3]==='장'||v[3]==='편')?v[3]:(v[1].trim()==='시편'?'편':'장');document.getElementById('woori_verse_verses').value=(v[4]||v[3]).trim();count++;document.getElementById('woori_verse_book').dispatchEvent(new Event('input'));}
              }
              status.textContent='Filled '+count+' fields. Please check them before publishing.';
            }catch(e){if(seq===prefillRequest)status.textContent=e.message;}finally{if(seq===prefillRequest)button.disabled=false;}
          }
          // Choose the conventional Korean chapter unit when the book changes.
          // Do not override the saved suffix when an existing sermon first loads.
          document.getElementById('woori_verse_book').addEventListener('input',function(){
            const book=this.value.replace(/\s+/gu,'').trim();
            document.getElementById('woori_verse_chapter_suffix').value=book==='시편'?'편':'장';
            showVerse();
          });
          function showVerse(){const b=document.getElementById('woori_verse_book').value.trim(),c=document.getElementById('woori_verse_chapter').value.trim(),v=document.getElementById('woori_verse_verses').value.trim(),cs=document.getElementById('woori_verse_chapter_suffix').value.trim()||'장',vs='절',segments=v.replace(/[–—~～]/g,'-').split(/[,，、]/).map(x=>x.trim()).filter(Boolean),formatted=segments.map(x=>x.replace(/절$/,'')+vs).join(', ');document.getElementById('woori-verse-preview').textContent=b&&c&&v?b+' '+c+cs+' '+formatted+'  주일 예배':'Enter a book, chapter and verse(s) to preview.';}
          ['woori_verse_book','woori_verse_chapter','woori_verse_verses','woori_verse_chapter_suffix'].forEach(id=>document.getElementById(id).addEventListener('input',showVerse));showVerse();
          button.addEventListener('click',readAndPrefill);
          input.addEventListener('change',function(){
            ++prefillRequest;
            button.disabled=false;
            status.textContent='';
            if(input.files.length)readAndPrefill();
          });
        })();
        </script>
    </div>
    <?php
}

function woori_sermons_format_date($date) {
    if (!$date) return '';
    $ts = strtotime($date);
    if (!$ts) return $date;
    return wp_date('Y년 m월 d일', $ts) . ' 주일 예배';
}

function woori_sermons_is_youtube_url($url) {
    $host = wp_parse_url($url, PHP_URL_HOST);
    if (!$host) return false;
    $host = strtolower(preg_replace('/^www\./', '', $host));
    return in_array($host, array('youtube.com', 'youtu.be', 'm.youtube.com'), true);
}

/** Convert a two-page PDF into two WordPress image attachments. */
function woori_sermons_convert_pdf($file) {
    if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return new WP_Error('pdf_upload', 'PDF upload failed. Please try again.');
    }
    if ((int) $file['size'] > 20 * 1024 * 1024) return new WP_Error('pdf_size', 'The PDF must be 20 MB or smaller.');
    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') return new WP_Error('pdf_type', 'Please upload a PDF file.');
    $mime = function_exists('finfo_open') ? (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : '';
    if ($mime && !in_array($mime, array('application/pdf', 'application/x-pdf'), true)) return new WP_Error('pdf_type', 'The uploaded file is not a valid PDF.');
    if (!class_exists('Imagick')) return new WP_Error('pdf_support', 'PDF conversion is unavailable: your server needs the PHP Imagick extension and PDF/Ghostscript support. You can still upload two images manually.');
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attachments = array();
    $paths = array();
    try {
        $pdf = new Imagick();
        $pdf->pingImage($file['tmp_name']);
        $count = $pdf->getNumberImages();
        $pdf->clear(); $pdf->destroy();
        if ($count !== 2) return new WP_Error('pdf_pages', 'The order of service PDF must contain exactly 2 pages.');
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) return new WP_Error('pdf_storage', $uploads['error']);
        for ($i = 0; $i < 2; $i++) {
            $image = new Imagick();
            $image->setResolution(160, 160);
            $image->readImage($file['tmp_name'] . '[' . $i . ']');
            $image->setImageBackgroundColor('white');
            $flattened = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $flattened->setImageFormat('png');
            $name = wp_unique_filename($uploads['path'], sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)) . '-page-' . ($i + 1) . '.png');
            $path = trailingslashit($uploads['path']) . $name;
            if (!$flattened->writeImage($path)) throw new Exception('Could not write the converted PNG.');
            $paths[] = $path;
            $flattened->clear(); $flattened->destroy(); $image->clear(); $image->destroy();
            $attachment_id = wp_insert_attachment(array('post_mime_type' => 'image/png', 'post_title' => sanitize_text_field(pathinfo($name, PATHINFO_FILENAME)), 'post_status' => 'inherit'), $path, 0, true);
            if (is_wp_error($attachment_id)) throw new Exception($attachment_id->get_error_message());
            $attachments[] = $attachment_id;
            $metadata = wp_generate_attachment_metadata($attachment_id, $path);
            if (empty($metadata)) throw new Exception('Could not generate image metadata.');
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
        return $attachments;
    } catch (Throwable $e) {
        foreach ($attachments as $id) wp_delete_attachment($id, true);
        foreach ($paths as $path) if (file_exists($path)) wp_delete_file($path);
        return new WP_Error('pdf_conversion', 'PDF conversion failed: ' . $e->getMessage() . ' Check Imagick/Ghostscript PDF permissions or upload two images manually.');
    }
}

// Render temporary page previews in memory; nothing is saved to the Media Library until submission.
add_action('wp_ajax_woori_preview_pdf_pages', 'woori_sermons_preview_pdf_pages_ajax');
function woori_sermons_preview_pdf_pages_ajax() {
    if (!current_user_can('edit_posts') || !check_ajax_referer('woori_preview_pdf_pages', 'nonce', false)) wp_send_json_error(array('message' => 'Permission denied.'), 403);
    $file = $_FILES['pdf'] ?? null;
    if (!$file || (int)($file['error'] ?? -1) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) wp_send_json_error(array('message' => 'PDF upload failed.'), 400);
    if ((int)$file['size'] > 20 * 1024 * 1024 || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') wp_send_json_error(array('message' => 'Please select a PDF smaller than 20 MB.'), 400);
    $handle = fopen($file['tmp_name'], 'rb');
    $signature = $handle ? fread($handle, 5) : '';
    if ($handle) fclose($handle);
    if ($signature !== '%PDF-') wp_send_json_error(array('message' => 'Invalid PDF.'), 400);
    if (!class_exists('Imagick')) wp_send_json_error(array('message' => 'Page preview requires Imagick and PDF rendering support on this server. Text prefilling can still work.'), 422);
    $pages = array();
    try {
        $pdf = new Imagick();
        $pdf->pingImage($file['tmp_name']);
        $count = $pdf->getNumberImages();
        $pdf->clear(); $pdf->destroy();
        if ($count !== 2) wp_send_json_error(array('message' => 'The PDF must contain exactly two pages.'), 422);
        for ($i = 0; $i < 2; $i++) {
            $image = new Imagick();
            $image->setResolution(85, 85);
            $image->readImage($file['tmp_name'] . '[' . $i . ']');
            $image->setImageBackgroundColor('white');
            $flattened = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $flattened->setImageFormat('png');
            $flattened->setImageCompressionQuality(75);
            $blob = $flattened->getImageBlob();
            $pages[] = 'data:image/png;base64,' . base64_encode($blob);
            $flattened->clear(); $flattened->destroy(); $image->clear(); $image->destroy();
        }
    } catch (Throwable $e) {
        wp_send_json_error(array('message' => 'Could not render PDF pages. Check server Imagick/Ghostscript PDF support.'), 422);
    }
    wp_send_json_success(array('pages' => $pages));
}

// Preview extraction without publishing or retaining the uploaded PDF.
add_action('wp_ajax_woori_prefill_pdf', 'woori_sermons_prefill_pdf_ajax');
function woori_sermons_prefill_pdf_ajax() {
    if (!current_user_can('edit_posts') || !check_ajax_referer('woori_prefill_pdf', 'nonce', false)) wp_send_json_error(array('message'=>'Permission denied.'),403);
    $file=$_FILES['pdf'] ?? null;
    if (!$file || (int)($file['error'] ?? -1)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) wp_send_json_error(array('message'=>'Upload failed.'),400);
    if ((int)$file['size'] > 20*1024*1024 || strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))!=='pdf') wp_send_json_error(array('message'=>'Please use a PDF under 20 MB.'),400);
    $handle=fopen($file['tmp_name'],'rb');$signature=$handle ? fread($handle,5) : '';if($handle)fclose($handle);
    if($signature!=='%PDF-') wp_send_json_error(array('message'=>'Invalid PDF.'),400);
    $text=woori_pdf_extract_text($file['tmp_name']);
    if (!trim($text)) wp_send_json_error(array('message'=>'No selectable text could be extracted. This PDF format may not be supported by the built-in PHP extractor.'),422);
    wp_send_json_success(woori_sermons_parse_bulletin($text));
}
function woori_sermons_parse_bulletin($text) {
    $result=array('title'=>'','sermon_date'=>'','speaker'=>'','verse'=>'');
    if(preg_match('/\b(\d{1,2})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+(20\d{2})\b/i',$text,$m)){
        $months=array('jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12);
        $result['sermon_date']=sprintf('%04d-%02d-%02d',(int)$m[3],$months[strtolower($m[2])],(int)$m[1]);
    }
    // The primary sermon line follows the scripture reading. Ignore children's sermons.
    $lines=preg_split('/\R/u',$text);
    $main_line=-1;
    foreach($lines as $i=>$line){if(preg_match('/^\s*설교\s*\(Sermon\)/u',$line)){$main_line=$i;break;}}
    if($main_line>=0){
        $block=implode("\n",array_slice($lines,$main_line,5));
        if(preg_match('/설교\s*\(Sermon\)\s*([가-힣\s]{2,18}\s*(?:목사|전도사))/u',$lines[$main_line],$m))$result['speaker']=preg_replace('/\s+/u',' ',trim($m[1]));
        if(preg_match('/[『「]\s*(.+?)\s*[』」]/u',$block,$m))$result['title']=trim($m[1]);
    }
    // Today's scripture reference appears above the primary sermon section.
    $scripture=$main_line>=0 ? implode("\n",array_slice($lines,max(0,$main_line-20),20)) : $text;
    // Prefer the labelled reading and allow multiple verse segments, such as
    // 오늘의 말씀 : 전도서 4:1, 9-12 or 전도서 4장 1절, 9-12절.
    foreach ($lines as $scripture_line) {
        if (strpos($scripture_line, '오늘의 말씀') === false) continue;
        if (preg_match('/오늘의\s*말씀\s*[:：]?\s*([가-힣]{2,12})\s*(\d{1,3})\s*(?:[:：]|장|편)\s*(\d{1,3}(?:\s*[-~–—]\s*\d{1,3})?(?:\s*절?\s*[,，、]\s*\d{1,3}(?:\s*[-~–—]\s*\d{1,3})?)*)(?:\s*절)?/u', $scripture_line, $ref)) {
            $segments = woori_sermons_normalize_verse_segments(preg_replace('/절/u', '', $ref[3]));
            if (woori_sermons_valid_verse_segments($segments)) {
                $book=$ref[1];
                $result['verse']=$book.' '.$ref[2].($book==='시편'?'편 ':'장 ').woori_sermons_format_verse_segments($segments);
                break;
            }
        }
    }
    // The church PDF positions text glyphs visually rather than in reading order.
    // E.g. 25 Oct extracts as ": 24:19-25 오늘의 말씀 여호수아".
    // Match this layout *only* on the labelled scripture line, not on the
    // announcement page or the daily reading table.
    if (!$result['verse']) foreach ($lines as $scripture_line) {
        if (strpos($scripture_line, '오늘의 말씀') === false) continue;
        if (preg_match('/[:：]\s*(\d{1,3})\s*[:：]\s*(\d{1,3})\s*[-~–—]\s*(\d{1,3})\s*오늘의\s*말씀\s*([가-힣]{2,12})/u', $scripture_line, $reordered)) {
            $book = $reordered[4];
            $result['verse'] = $book.' '.$reordered[1].($book === '시편' ? '편 ' : '장 ').$reordered[2].'-'.$reordered[3].'절';
            break;
        }
    }
    // Accept references like '오늘의 말씀 : 여호수아 24:19-25' (also full-width colon).
    // Require the '오늘의 말씀' label to avoid matching unrelated Bible references.
    if (!$result['verse'] && preg_match('/오늘의\s*말씀\s*[:：]?\s*([가-힣]+)\s*(\d{1,3})\s*[:：]\s*(\d{1,3})\s*[-~–—]\s*(\d{1,3})/u', $text, $colon)) {
        $result['verse'] = $colon[1].' '.$colon[2].($colon[1]==='시편'?'편 ':'장 ').$colon[3].'-'.$colon[4].'절';
    }
    // Newer bulletins may print e.g. 여호수아 24:19-25 without the
    // "오늘의 말씀" label, and PDF glyph extraction may insert spaces.
    if (!$result['verse']) {
        $page2 = strpos($text, "\f") !== false ? substr($text, strpos($text, "\f") + 1) : $text;
        if (preg_match('/([가-힣]{2,12})\s*(\d{1,3})\s*[:：]\s*(\d{1,3})\s*[-~–—]\s*(\d{1,3})(?!\d)/u', $page2, $ref)) {
            $book=$ref[1];
            // Ignore announcement fragments: only scripture-looking book names.
            $books='창세기 출애굽기 레위기 민수기 신명기 여호수아 사사기 룻기 사무엘상 사무엘하 열왕기상 열왕기하 역대상 역대하 에스라 느헤미야 에스더 욥기 시편 잠언 전도서 아가 이사야 예레미야 예레미야애가 에스겔 다니엘 호세아 요엘 아모스 오바댜 요나 미가 나훔 하박국 스바냐 학개 스가랴 말라기 마태복음 마가복음 누가복음 요한복음 사도행전 로마서 고린도전서 고린도후서 갈라디아서 에베소서 빌립보서 골로새서 데살로니가전서 데살로니가후서 디모데전서 디모데후서 디도서 빌레몬서 히브리서 야고보서 베드로전서 베드로후서 요한일서 요한이서 요한삼서 유다서 요한계시록';
            if (in_array($book, explode(' ', $books), true)) {
                $result['verse']=$book.' '.$ref[2].($book==='시편'?'편 ':'장 ').$ref[3].'-'.$ref[4].'절';
            }
        }
    }
    if (!$result['verse'] && preg_match('/오늘의\s*말씀\s*[:：]\s*([가-힣]+)\s*(\d+)\s*(?:장|편)\s*(\d+)\s*[-~–]\s*(\d+)\s*절/u',$scripture,$m)){
        $result['verse']=$m[1].' '.$m[2].( $m[1]==='시편' ? '편 ' : '장 ' ).$m[3].'-'.$m[4].'절';
    }
    // Our lightweight PDF extractor groups glyphs by visual position. On these
    // bulletins the verse's numbers can appear before the book name.
    if (!$result['speaker'] && preg_match('/(?:^|\n)\s*설교\s*\(Sermon\)\s*([가-힣\s]{2,18}?(?:목사|전도사))/u', $text, $m)) {
        $result['speaker']=preg_replace('/\s+/u',' ',trim($m[1]));
    }
    if (!$result['title'] && $main_line>=0) {
        foreach(array_slice($lines,$main_line,8) as $line) {
            if(preg_match('/[『」』「]\s*[『」』「]?\s*([가-힣][가-힣\s!?.,]+?)(?:\s{2,}|$)/u',trim($line),$m) && !preg_match('/설교\s*\(Sermon\)/u',$line)) {
                $result['title']=trim($m[1]);break;
            }
        }
    }
    if (!$result['verse']) {
        foreach($lines as $line) {
            if(strpos($line,'오늘의 말씀')===false) continue;
            if(preg_match('/오늘의\s*말씀\s*[:：]?\s*([가-힣]+)\s*(?:장|편)?\s*(\d+)\s*(?:장|편)?\s*(\d+)\s*[-~–]\s*(\d+)/u',$line,$m)) {
                $result['verse']=$m[1].' '.$m[2].($m[1]==='시편'?'편 ':'장 ').$m[3].'-'.$m[4].'절';break;
            }
            if(preg_match('/[:：]\s*(\d{1,3})(\d{1,3})-(\d{1,3})\s*오늘의\s*말씀\s*([가-힣]+)\s*(?:장|편)/u',$line,$m)) {
                $digits=$m[1].$m[2];
                // Page 2 text glyphs sometimes merge chapter and starting verse.
                // Prefer a one-digit chapter for non-Psalms in this bulletin layout.
                if($m[4]!=='시편' && strlen($digits)>=3) {$chapter=substr($digits,0,1);$first=substr($digits,1);}
                else {$chapter=$m[1];$first=$m[2];}
                $result['verse']=$m[4].' '.$chapter.($m[4]==='시편'?'편 ':'장 ').$first.'-'.$m[3].'절';break;
            }
        }
    }
    return $result;
}

/** Keep the API key server-side. Never enqueue it in JavaScript. */
function woori_sermons_youtube_api_key() {
    if (defined('WOORI_YOUTUBE_API_KEY') && WOORI_YOUTUBE_API_KEY) return trim((string) WOORI_YOUTUBE_API_KEY);
    return trim((string) get_option('woori_sermons_youtube_api_key', ''));
}

function woori_sermons_youtube_settings_page() {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    if (isset($_POST['woori_save_youtube_settings'])) {
        check_admin_referer('woori_youtube_settings');
        $key = trim(sanitize_text_field(wp_unslash($_POST['woori_youtube_api_key'] ?? '')));
        if ($key !== '') {
            update_option('woori_sermons_youtube_api_key', $key, false);
            delete_transient('woori_yt_uploads_playlist_v159');
        } elseif (!empty($_POST['woori_clear_youtube_key'])) {
            delete_option('woori_sermons_youtube_api_key');
        }
        echo '<div class="notice notice-success"><p>YouTube API settings saved.</p></div>';
    }
    $configured = woori_sermons_youtube_api_key() !== '';
    echo '<div class="wrap"><h1>YouTube API Settings</h1><p>Enable YouTube Data API v3 in Google Cloud, then create an API key restricted to that API. This key stays on the WordPress server.</p>';
    if (defined('WOORI_YOUTUBE_API_KEY') && WOORI_YOUTUBE_API_KEY) echo '<p><strong>API key configured through wp-config.php.</strong> That setting takes precedence.</p>';
    echo '<p>Current status: <strong>' . ($configured ? 'Configured' : 'Not configured') . '</strong></p>';
    echo '<form method="post">'; wp_nonce_field('woori_youtube_settings');
    echo '<table class="form-table"><tr><th><label for="woori_youtube_api_key">YouTube API key</label></th><td><input id="woori_youtube_api_key" name="woori_youtube_api_key" type="password" class="regular-text" autocomplete="new-password" placeholder="Leave blank to keep existing key"><p class="description">The existing key is never displayed. Paste a new key to replace it.</p></td></tr></table>';
    echo '<p><label><input type="checkbox" name="woori_clear_youtube_key" value="1"> Remove stored key (only if new key is blank)</label></p>';
    submit_button('Save YouTube Settings', 'primary', 'woori_save_youtube_settings');
    echo '</form><p>Optional: define <code>WOORI_YOUTUBE_API_KEY</code> in wp-config.php instead. API keys restricted by HTTP referrer generally do not work for server-side requests; use API restrictions and, where practical, server IP restrictions.</p></div>';
}

function woori_sermons_youtube_api_request($endpoint, $params) {
    $params['key'] = woori_sermons_youtube_api_key();
    $url = add_query_arg($params, 'https://www.googleapis.com/youtube/v3/' . $endpoint);
    $response = wp_remote_get($url, array('timeout'=>15, 'redirection'=>2));
    if (is_wp_error($response)) return new WP_Error('yt_network', $response->get_error_message());
    $code = wp_remote_retrieve_response_code($response);
    $json = json_decode(wp_remote_retrieve_body($response), true);
    if ($code !== 200 || !is_array($json)) {
        $msg = isset($json['error']['message']) ? sanitize_text_field($json['error']['message']) : 'HTTP ' . $code;
        return new WP_Error('yt_api', 'YouTube API: ' . $msg);
    }
    return $json;
}

function woori_sermons_youtube_uploads_playlist() {
    $key_hash = substr(hash('sha256', woori_sermons_youtube_api_key()), 0, 12);
    $cache = get_transient('woori_yt_uploads_playlist_v159');
    if (is_array($cache) && ($cache['key_hash'] ?? '') === $key_hash) return $cache['id'];
    // Channel ID belongs to https://www.youtube.com/@sydneywoorichurch9247.
    $data = woori_sermons_youtube_api_request('channels', array('part'=>'contentDetails', 'id'=>'UC4ihCPJMTkxpQUSzE8B7zbQ'));
    if (is_wp_error($data)) return $data;
    $id = $data['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? '';
    if (!$id) return new WP_Error('yt_channel', 'YouTube channel uploads playlist was not found. Check the configured channel ID.');
    set_transient('woori_yt_uploads_playlist_v159', array('id'=>$id, 'key_hash'=>$key_hash), DAY_IN_SECONDS);
    return $id;
}

/** Fetch the most recent uploads, with paging for older service dates. */
function woori_sermons_youtube_find_video($date) {
    if (!woori_sermons_youtube_api_key()) return new WP_Error('yt_no_key', 'Set a YouTube API key in Weekly Sermons → YouTube API Settings.');
    $playlist = woori_sermons_youtube_uploads_playlist();
    if (is_wp_error($playlist)) return $playlist;
    $today = current_datetime()->format('Y-m-d');
    $search_date = $date && $date !== $today ? $date : '';
    $items = array(); $token = ''; $pages = 0;
    do {
        $params = array('part'=>'snippet,contentDetails', 'playlistId'=>$playlist, 'maxResults'=>50);
        if ($token) $params['pageToken'] = $token;
        $data = woori_sermons_youtube_api_request('playlistItems', $params);
        if (is_wp_error($data)) return $data;
        foreach (($data['items'] ?? array()) as $item) {
            $id = $item['contentDetails']['videoId'] ?? '';
            if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) continue;
            $published = $item['contentDetails']['videoPublishedAt'] ?? ($item['snippet']['publishedAt'] ?? '');
            if (!$published) continue;
            try { $local = (new DateTimeImmutable($published))->setTimezone(wp_timezone())->format('Y-m-d'); }
            catch (Exception $e) { continue; }
            $items[] = array('id'=>$id, 'url'=>'https://www.youtube.com/watch?v='.$id, 'title'=>sanitize_text_field($item['snippet']['title'] ?? 'YouTube video'), 'published'=>$published, 'local_date'=>$local, 'source'=>'YouTube Data API');
        }
        $token = $data['nextPageToken'] ?? '';
        ++$pages;
        // Limit history requests to 10 pages (500 uploads), to avoid runaway API calls.
        if (!$search_date || !$token || $pages >= 10) break;
        // Continue paging until we've reached the requested date in the upload timeline.
        $last = end($items);
        if ($last && $last['local_date'] < $search_date) break;
    } while ($token);
    if (!$items) return new WP_Error('yt_empty', 'No public uploads found on the church channel.');
    usort($items, function($a,$b){return strcmp($b['published'], $a['published']);});
    $latest = $items[0]; $selected = $latest; $matched = false;
    if ($search_date) {
        foreach ($items as $item) if ($item['local_date'] === $search_date) { $selected = $item; $matched = true; break; }
    }
    // For livestreams, actualStartTime can differ from videoPublishedAt. Inspect
    // video metadata for nearby uploads before falling back to latest.
    if ($search_date && !$matched) {
        $nearby = array();
        foreach ($items as $item) {
            $distance = abs(strtotime($item['local_date']) - strtotime($search_date));
            if ($distance <= 7 * DAY_IN_SECONDS) $nearby[] = $item['id'];
            if (count($nearby) >= 50) break;
        }
        if ($nearby) {
            $details = woori_sermons_youtube_api_request('videos', array('part'=>'liveStreamingDetails,snippet', 'id'=>implode(',', $nearby), 'maxResults'=>50));
            if (!is_wp_error($details)) foreach (($details['items'] ?? array()) as $video) {
                $start = $video['liveStreamingDetails']['actualStartTime'] ?? ($video['liveStreamingDetails']['scheduledStartTime'] ?? '');
                if (!$start) continue;
                try { $local = (new DateTimeImmutable($start))->setTimezone(wp_timezone())->format('Y-m-d'); }
                catch (Exception $e) { continue; }
                if ($local === $search_date) {
                    $id = $video['id'];
                    $selected = array('id'=>$id, 'url'=>'https://www.youtube.com/watch?v='.$id, 'title'=>sanitize_text_field($video['snippet']['title'] ?? 'YouTube livestream'), 'published'=>$start, 'local_date'=>$local, 'source'=>'YouTube Data API (livestream start)');
                    $matched = true; break;
                }
            }
        }
    }
    unset($selected['id']);
    $selected['matched_date'] = $matched;
    $selected['requested_date'] = $date;
    $selected['selection'] = $matched ? 'service_date' : ($date === $today ? 'today_latest' : 'latest_fallback');
    return $selected;
}

add_action('wp_ajax_woori_latest_youtube', function () {
    if (!current_user_can('edit_posts')) wp_send_json_error(array('message'=>'Permission denied.'),403);
    check_ajax_referer('woori_youtube_feed','nonce');
    $date = sanitize_text_field(wp_unslash($_POST['service_date'] ?? ''));
    if ($date && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4)))) {
        wp_send_json_error(array('message'=>'Invalid service date.'),400);
    }
    $video = woori_sermons_youtube_find_video($date);
    if (is_wp_error($video)) wp_send_json_error(array('message'=>$video->get_error_message()), 400);
    wp_send_json_success($video);
});
