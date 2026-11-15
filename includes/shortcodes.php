<?php
if (!defined('ABSPATH')) exit;

function woori_sermons_current_post_id() {
    $id = get_the_ID();
    return $id ? (int) $id : 0;
}

function woori_sermons_latest_post_id() {
    $cat_id = woori_sermons_category_id();
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'no_found_rows' => true,
    );
    if ($cat_id) $args['cat'] = $cat_id;
    $q = new WP_Query($args);
    $id = $q->have_posts() ? (int) $q->posts[0]->ID : 0;
    wp_reset_postdata();
    return $id;
}

function woori_sermons_resolve_id($atts) {
    $id = isset($atts['id']) ? absint($atts['id']) : 0;
    return $id ?: woori_sermons_current_post_id();
}

function woori_sermons_shortcode_field($atts, $content, $tag) {
    $atts = shortcode_atts(array('id' => 0), $atts, $tag);
    $id = woori_sermons_resolve_id($atts);
    if (!$id) return '';
    $key_map = array(
        'sermon_title' => 'title',
        'sermon_date' => WOORI_SERMON_META_DATE,
        'sermon_speaker' => WOORI_SERMON_META_SPEAKER,
        'sermon_verse' => WOORI_SERMON_META_VERSE,
        'sermon_youtube' => WOORI_SERMON_META_YOUTUBE,
    );
    $field = $key_map[$tag] ?? '';
    if ($field === 'title') return esc_html(get_the_title($id));
    if (!$field) return '';
    $value = woori_sermons_get($id, $field);
    if ($tag === 'sermon_date') return esc_html(woori_sermons_format_date($value));
    if ($tag === 'sermon_youtube') return esc_url($value);
    return esc_html($value);
}

add_shortcode('sermon_date_short', function($atts) {
    $atts = shortcode_atts(array('id' => 0), $atts);
    $id = woori_sermons_resolve_id($atts);
    if (!$id) return '';
    $date = woori_sermons_get($id, WOORI_SERMON_META_DATE);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $date, $parts)) return '';
    return esc_html($parts[1] . '년 ' . $parts[2] . '월 ' . $parts[3] . '일');
});

foreach (array('sermon_title','sermon_date','sermon_speaker','sermon_verse','sermon_youtube') as $tag) {
    add_shortcode($tag, 'woori_sermons_shortcode_field');
}

function woori_sermons_latest_shortcode_field($atts, $content, $tag) {
    $atts = shortcode_atts(array(), $atts, $tag);
    $id = woori_sermons_latest_post_id();
    if (!$id) return '';
    $map = array(
        'latest_sermon_title' => '[sermon_title id="' . $id . '"]',
        'latest_sermon_date' => '[sermon_date_short id="' . $id . '"]',
        'latest_sermon_speaker' => '[sermon_speaker id="' . $id . '"]',
        'latest_sermon_verse' => '[sermon_verse id="' . $id . '"]',
        'latest_sermon_youtube' => '[sermon_youtube id="' . $id . '"]',
    );
    return do_shortcode($map[$tag] ?? '');
}

foreach (array('latest_sermon_title','latest_sermon_date','latest_sermon_speaker','latest_sermon_verse','latest_sermon_youtube') as $tag) {
    add_shortcode($tag, 'woori_sermons_latest_shortcode_field');
}

add_shortcode('sermon_permalink', function($atts) {
    $atts = shortcode_atts(array('id' => 0), $atts);
    $id = woori_sermons_resolve_id($atts);
    return $id ? esc_url(get_permalink($id)) : '';
});

add_shortcode('latest_sermon_permalink', function() {
    $id = woori_sermons_latest_post_id();
    return $id ? esc_url(get_permalink($id)) : '';
});

function woori_sermons_image_shortcode($atts, $content, $tag) {
    $atts = shortcode_atts(array('id' => 0, 'size' => 'large', 'link' => 'yes'), $atts, $tag);
    $id = woori_sermons_resolve_id($atts);
    if (!$id) return '';
    $meta = $tag === 'sermon_order_1' ? WOORI_SERMON_META_ORDER_1 : WOORI_SERMON_META_ORDER_2;
    $attachment = absint(woori_sermons_get($id, $meta));
    if (!$attachment) return '';
    $html = wp_get_attachment_image($attachment, sanitize_key($atts['size']), false, array('class' => 'woori-sermon-order-image'));
    if (!$html) return '';
    if ($atts['link'] === 'yes') $html = '<a href="' . esc_url(wp_get_attachment_url($attachment)) . '" target="_blank" rel="noopener" class="woori-sermon-order-link">' . $html . '</a>';
    return $html;
}
add_shortcode('sermon_order_1', 'woori_sermons_image_shortcode');
add_shortcode('sermon_order_2', 'woori_sermons_image_shortcode');

/** Render both order-of-service images as one accessible, lightweight carousel. */
function woori_sermons_order_carousel_shortcode($atts = array(), $content = '', $tag = 'sermon_order_carousel') {
    $atts = shortcode_atts(array('id' => 0, 'size' => 'large', 'link' => 'yes'), $atts, $tag);
    $id = woori_sermons_resolve_id($atts);
    if (!$id) return '';

    $image_ids = array(
        absint(woori_sermons_get($id, WOORI_SERMON_META_ORDER_1)),
        absint(woori_sermons_get($id, WOORI_SERMON_META_ORDER_2)),
    );
    $image_ids = array_values(array_filter($image_ids));
    if (!$image_ids) return '';

    $carousel_id = function_exists('wp_unique_id') ? wp_unique_id('woori-order-carousel-') : 'woori-order-carousel-' . $id . '-' . wp_rand(1000, 999999);
    $slides = '';
    foreach ($image_ids as $index => $image_id) {
        $image = wp_get_attachment_image($image_id, sanitize_key($atts['size']), false, array(
            'class' => 'woori-order-carousel__image',
            'alt' => sprintf('Order of Service — Page %d', $index + 1),
        ));
        if (!$image) continue;
        if ($atts['link'] === 'yes') {
            $image = '<a class="woori-order-carousel__link" href="' . esc_url(wp_get_attachment_url($image_id)) . '" target="_blank" rel="noopener">' . $image . '</a>';
        }
        // Keep every slide rendered in the flex track. Visibility is controlled only by the track transform.
        // A native `hidden` attribute sets display:none and leaves a blank second position when translated.
        $slides .= '<div class="woori-order-carousel__slide" role="group" aria-roledescription="slide" aria-label="' . esc_attr(($index + 1) . ' of ' . count($image_ids)) . '" aria-hidden="' . ($index === 0 ? 'false' : 'true') . '">' . $image . '</div>';
    }
    if (!$slides) return '';

    $controls = count($image_ids) > 1
        ? '<div class="woori-order-carousel__controls"><button type="button" class="woori-order-carousel__prev" aria-label="Previous image">&#x2039;</button><div class="woori-order-carousel__dots">' . implode('', array_map(function($i) { return '<button type="button" class="woori-order-carousel__dot' . ($i === 0 ? ' is-active' : '') . '" aria-label="Show image ' . ($i + 1) . '" aria-current="' . ($i === 0 ? 'true' : 'false') . '" data-slide="' . $i . '"></button>'; }, array_keys($image_ids))) . '</div><button type="button" class="woori-order-carousel__next" aria-label="Next image">&#x203A;</button></div>'
        : '';

    $html = '<div id="' . esc_attr($carousel_id) . '" class="woori-order-carousel" data-woori-carousel aria-roledescription="carousel" aria-label="Order of Service">'
        . '<style>.woori-order-carousel{position:relative;width:100%;max-width:100%;margin:0 auto}.woori-order-carousel__slides{width:100%;overflow:hidden;position:relative}.woori-order-carousel__track{display:flex;width:100%;transform:translateX(0);transition:transform .45s cubic-bezier(.22,.61,.36,1);will-change:transform}.woori-order-carousel__slide{text-align:center;flex:0 0 100%;width:100%;min-width:100%;line-height:0}.woori-order-carousel__image{display:block;width:100%;max-width:100%;height:auto;object-fit:contain;margin:0 auto;border:0;border-radius:0;box-shadow:none}.woori-order-carousel__link{display:block;width:100%;line-height:0}.woori-order-carousel__controls{position:absolute;left:0;right:0;top:0;bottom:0;display:flex;align-items:center;justify-content:space-between;gap:0;margin:0;padding:0;pointer-events:none}.woori-order-carousel__controls button{cursor:pointer;pointer-events:auto}.woori-order-carousel__prev,.woori-order-carousel__next{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:0;width:42px;height:52px;padding:0;line-height:1;background:transparent;color:#000;font-size:36px;font-weight:300;opacity:.78}.woori-order-carousel__dots{position:absolute;left:50%;bottom:-22px;transform:translateX(-50%);display:flex;gap:9px;align-items:center;justify-content:center}.woori-order-carousel__dot{width:5px;height:5px;min-width:5px;padding:0;border:0;border-radius:50%;background:#132923;transition:background-color .2s ease}.woori-order-carousel__dot.is-active{width:5px;height:5px;min-width:5px;background:#C4D600;outline:0;outline-offset:0}.woori-order-carousel__controls button:focus-visible{outline:2px solid #c4d600;outline-offset:3px}.woori-order-carousel{margin-bottom:28px}@media(max-width:600px){.woori-order-carousel__prev,.woori-order-carousel__next{width:34px;height:42px;font-size:30px}.woori-order-carousel__dots{bottom:-19px;gap:8px}}@media(prefers-reduced-motion:reduce){.woori-order-carousel__track{transition:none}}</style>'
        . '<div class="woori-order-carousel__slides"><div class="woori-order-carousel__track">' . $slides . '</div></div>' . $controls
        . '<script>(function(){var root=document.getElementById(' . wp_json_encode($carousel_id) . ');if(!root||root.dataset.initialized)return;root.dataset.initialized="1";var slides=Array.prototype.slice.call(root.querySelectorAll(".woori-order-carousel__slide"));var track=root.querySelector(".woori-order-carousel__track");var dots=Array.prototype.slice.call(root.querySelectorAll(".woori-order-carousel__dot"));var count=slides.length,index=0,position=1,busy=false; if(!track||!count)return;var firstClone=slides[0].cloneNode(true),lastClone=slides[count-1].cloneNode(true);firstClone.setAttribute("aria-hidden","true");lastClone.setAttribute("aria-hidden","true");firstClone.setAttribute("data-clone","true");lastClone.setAttribute("data-clone","true");track.insertBefore(lastClone,track.firstChild);track.appendChild(firstClone);track.style.transition="none";track.style.transform="translateX(-100%)";void track.offsetWidth;track.style.transition="";function indicators(){slides.forEach(function(slide,i){slide.setAttribute("aria-hidden",i===index?"false":"true");});dots.forEach(function(dot,i){dot.classList.toggle("is-active",i===index);dot.setAttribute("aria-current",i===index?"true":"false");});}function move(direction){if(busy||count<2)return;busy=true;index=(index+direction+count)%count;position+=direction;track.style.transform="translateX(-"+(position*100)+"%)";indicators();if(window.matchMedia&&window.matchMedia("(prefers-reduced-motion: reduce)").matches){if(position===count+1||position===0){position=position===0?count:1;track.style.transform="translateX(-"+(position*100)+"%)";}busy=false;}}function jumpTo(i){if(busy||i===index)return;move(i>index?1:-1);}track.addEventListener("transitionend",function(e){if(e.target!==track||e.propertyName!=="transform")return;if(position===count+1||position===0){position=position===0?count:1;track.style.transition="none";track.style.transform="translateX(-"+(position*100)+"%)";void track.offsetWidth;track.style.transition="";}busy=false;});var prev=root.querySelector(".woori-order-carousel__prev"),next=root.querySelector(".woori-order-carousel__next");if(prev)prev.addEventListener("click",function(){move(-1);});if(next)next.addEventListener("click",function(){move(1);});dots.forEach(function(dot){dot.addEventListener("click",function(){var target=parseInt(dot.getAttribute("data-slide"),10)||0;if(target!==index)move(target>index?1:-1);});});var startX=null;root.addEventListener("touchstart",function(e){if(e.changedTouches.length)startX=e.changedTouches[0].clientX;},{passive:true});root.addEventListener("touchend",function(e){if(startX===null||!e.changedTouches.length)return;var delta=e.changedTouches[0].clientX-startX;if(Math.abs(delta)>40)move(delta<0?1:-1);startX=null;},{passive:true});if(window.matchMedia&&window.matchMedia("(prefers-reduced-motion: reduce)").matches){track.addEventListener("transitionrun",function(){busy=false;});}indicators();})();</script>' 
        . '</div>';
    return $html;
}
add_shortcode('sermon_order_carousel', 'woori_sermons_order_carousel_shortcode');

function woori_sermons_latest_image_shortcode($atts, $content, $tag) {
    $id = woori_sermons_latest_post_id();
    if (!$id) return '';
    $image_tag = $tag === 'latest_sermon_order_1' ? 'sermon_order_1' : 'sermon_order_2';
    $atts = shortcode_atts(array('size' => 'large', 'link' => 'yes'), $atts, $tag);
    return do_shortcode('[' . $image_tag . ' id="' . $id . '" size="' . esc_attr($atts['size']) . '" link="' . esc_attr($atts['link']) . '"]');
}
add_shortcode('latest_sermon_order_1', 'woori_sermons_latest_image_shortcode');
add_shortcode('latest_sermon_order_2', 'woori_sermons_latest_image_shortcode');

function woori_sermons_youtube_shortcode($atts, $content = '', $tag = '') {
    $atts = shortcode_atts(array('id' => 0, 'width' => '100%', 'height' => '500'), $atts, $tag);
    $id = woori_sermons_resolve_id($atts);
    if (!$id) return '';
    $url = woori_sermons_get($id, WOORI_SERMON_META_YOUTUBE);
    if (!$url) return '';
    $embed = wp_oembed_get($url, array('width' => absint($atts['width']), 'height' => absint($atts['height'])));
    if ($embed) {
        // Responsive landscape container; the iframe fills it without a fixed pixel height.
        // The video itself retains YouTube's original aspect ratio inside the player.
        $style = 'width:100%;max-width:100%;aspect-ratio:16 / 9;position:relative;overflow:hidden;border-radius:24px;';
        $embed = preg_replace('/<iframe\b/i', '<iframe style="position:absolute;inset:0;width:100%;height:100%;border:0;"', $embed, 1);
        return '<div class="woori-sermon-video" style="' . esc_attr($style) . '">' . $embed . '</div>';
    }
    return '';
}
add_shortcode('sermon_video', 'woori_sermons_youtube_shortcode');
add_shortcode('latest_sermon_video', function($atts) {
    $id = woori_sermons_latest_post_id();
    if (!$id) return '';
    $atts = shortcode_atts(array('width' => '100%', 'height' => '500'), $atts, 'latest_sermon_video');
    return do_shortcode('[sermon_video id="' . $id . '" width="' . esc_attr($atts['width']) . '" height="' . esc_attr($atts['height']) . '"]');
});

// Explicit current-post aliases for use inside the shared Elementor sermon template.
foreach (array(
    'current_sermon_title' => 'sermon_title',
    'current_sermon_date' => 'sermon_date',
    'current_sermon_speaker' => 'sermon_speaker',
    'current_sermon_verse' => 'sermon_verse',
    'current_sermon_video' => 'sermon_video',
    'current_sermon_order_1' => 'sermon_order_1',
    'current_sermon_order_2' => 'sermon_order_2',
    'current_sermon_youtube' => 'sermon_youtube',
) as $alias => $original) {
    add_shortcode($alias, function($atts = array(), $content = '', $tag = '') use ($original) {
        $atts = shortcode_atts(array('size' => 'large', 'link' => 'yes', 'width' => '100%', 'height' => '500'), $atts, $tag);
        $args = array();
        foreach (array('size', 'link', 'width', 'height') as $key) {
            if (isset($atts[$key])) $args[] = $key . '="' . esc_attr($atts[$key]) . '"';
        }
        return do_shortcode('[' . $original . ' ' . implode(' ', $args) . ']');
    });
}

add_shortcode('current_sermon_order_carousel', function($atts = array()) {
    $atts = shortcode_atts(array('size' => 'large', 'link' => 'yes'), $atts, 'current_sermon_order_carousel');
    return do_shortcode('[sermon_order_carousel size="' . esc_attr($atts['size']) . '" link="' . esc_attr($atts['link']) . '"]');
});
