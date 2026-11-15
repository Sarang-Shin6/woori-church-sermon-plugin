jQuery(function($){
  function openMedia(target){
    var frame = wp.media({
      title: 'Select Order of Service Image',
      button: { text: 'Use this image' },
      multiple: false,
      library: { type: 'image' }
    });
    frame.on('select', function(){
      var attachment = frame.state().get('selection').first().toJSON();
      $('#' + target).val(attachment.id);
      var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
      $('.woori-sermon-image-preview[data-preview-for="'+target+'"]').html('<img class="woori-sermon-preview" src="'+url+'" alt="">');
      $('.woori-remove-image[data-target="'+target+'"]').prop('disabled', false);
    });
    frame.open();
  }
  $(document).on('click', '.woori-upload-image', function(e){ e.preventDefault(); openMedia($(this).data('target')); });
  $(document).on('click', '.woori-remove-image', function(e){
    e.preventDefault();
    var target = $(this).data('target');
    $('#'+target).val('');
    $('.woori-sermon-image-preview[data-preview-for="'+target+'"]').html('<span>No image selected</span>');
    $(this).prop('disabled', true);
  });

  // On the regular post editor, expose a convenient checkbox to mark the post as a sermon.
  if ($('#woori_sermon_details').length && $('#categorychecklist').length) {
    var box = $('<p class="woori-sermon-mark"><label><input type="checkbox" name="woori_make_sermon" value="1"> Save this post in the <strong>Sermons</strong> category</label></p>');
    $('#woori_sermon_details .inside').prepend(box);
  }
});

// The latest-video lookup runs when the Add Weekly Sermon form opens and on refresh.
(function(){
  'use strict';
  var field = document.getElementById('woori_sermon_youtube');
  var preview = document.getElementById('woori-youtube-preview');
  var refresh = document.getElementById('woori-youtube-refresh');
  var status = document.getElementById('woori-youtube-status');
  var dateField = document.getElementById('woori_sermon_date');
  if (!field || !preview || !refresh) return;
  if (!window.wooriYouTubeSettings || !window.wooriYouTubeSettings.ajaxUrl || !window.wooriYouTubeSettings.nonce) {
    if (status) status.textContent = 'YouTube lookup configuration is missing. Please refresh this page or contact the site administrator.';
    return;
  }
  function videoId(url){
    try {
      var u = new URL(url), host = u.hostname.toLowerCase().replace(/^www\./, '');
      var id = '';
      if (host === 'youtu.be') id = u.pathname.split('/')[1];
      else if (host === 'youtube.com' || host === 'm.youtube.com' || host === 'youtube-nocookie.com') {
        if (u.pathname === '/watch') id = u.searchParams.get('v');
        else if (/^\/(live|embed|shorts)\//.test(u.pathname)) id = u.pathname.split('/')[2];
      }
      return /^[A-Za-z0-9_-]{11}$/.test(id || '') ? id : null;
    } catch(e){return null;}
  }
  function updatePreview(){
    var id = videoId(field.value.trim());
    preview.replaceChildren();
    if (!id) {
      var p = document.createElement('p'); p.className = 'description';
      p.textContent = field.value.trim() ? 'Please enter a valid YouTube video or livestream URL.' : 'Enter or fetch a YouTube URL to preview it here.';
      preview.appendChild(p); return;
    }
    var iframe = document.createElement('iframe');
    iframe.src = 'https://www.youtube-nocookie.com/embed/' + id;
    iframe.title = 'YouTube sermon preview'; iframe.allowFullscreen = true;
    iframe.setAttribute('allow','accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
    iframe.setAttribute('referrerpolicy','strict-origin-when-cross-origin');
    preview.appendChild(iframe);
  }
  var debounce;
  field.addEventListener('input', function(){clearTimeout(debounce);debounce=setTimeout(updatePreview,350);});
  field.addEventListener('change', updatePreview);
  function fetchLatest(){
    refresh.disabled = true; status.textContent = 'Checking YouTube Data API…';
    var form = new FormData(); form.append('action','woori_latest_youtube');form.append('nonce',window.wooriYouTubeSettings.nonce);if(dateField)form.append('service_date',dateField.value);
    fetch(window.wooriYouTubeSettings.ajaxUrl,{method:'POST',body:form,credentials:'same-origin'})
      .then(function(r){return r.text().then(function(body){try {return JSON.parse(body);} catch(e) {throw new Error('YouTube lookup returned an invalid server response (HTTP ' + r.status + ').');}});})
      .then(function(result){
        if (!result.success) throw new Error(result.data && result.data.message || 'Unable to retrieve the latest video.');
        // Do not overwrite a URL the editor has already entered or modified.
        if (field.value.trim() && field.value.trim() !== initialValue && !manualRefresh) {
          status.textContent = 'Video found, but your existing URL was kept.'; return;
        }
        if (!manualRefresh && field.value.trim()) { status.textContent = 'Existing YouTube URL kept.'; return; }
        field.value = result.data.url;
        updatePreview();
        status.textContent = (result.data.matched_date ? 'Matched service date ' + result.data.requested_date + ': ' : 'No dated match (or service is today); latest available: ') + result.data.title + '. Verify the video preview before publishing.';
      })
      .catch(function(e){status.textContent = e.message;})
      .finally(function(){refresh.disabled=false;manualRefresh=false;});
  }
  var initialValue = field.value.trim(), manualRefresh = false;
  refresh.addEventListener('click',function(){manualRefresh=true;fetchLatest();});
  if(dateField)dateField.addEventListener('change',function(){if(!field.value.trim())fetchLatest();});
  updatePreview();
  if (!initialValue) fetchLatest();
})();
