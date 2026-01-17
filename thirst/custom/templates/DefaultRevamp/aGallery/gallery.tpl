{* aGallery - DefaultRevamp *}

<div class="ui container">
  <h1 class="ui header">{$AGALLERY_TITLE|escape}</h1>

  {if $AGALLERY_SUCCESS}
    <div class="ui positive message">{$AGALLERY_SUCCESS|escape}</div>
  {/if}
  {if $AGALLERY_ERROR}
    <div class="ui negative message">{$AGALLERY_ERROR|escape}</div>
  {/if}

  {if $AGALLERY_CAN_UPLOAD}
    <button class="ui primary button" id="agallery-upload-btn">
      <i class="upload icon"></i> {lang('aGallery', 'upload', 'general')}
    </button>

    <div class="ui modal" id="agallery-upload-modal">
      <i class="close icon"></i>
      <div class="header">{lang('aGallery', 'upload', 'general')}</div>
      <div class="content">
        <form class="ui form" method="post" enctype="multipart/form-data" action="{url path="/gallery"}">
          <input type="hidden" name="agallery_upload" value="1">
          {$AGALLERY_TOKEN_FIELD nofilter}

          <div class="field">
            <label>{lang('aGallery', 'category', 'general')}</label>
            <select class="ui dropdown" name="category" required>
              <option value="">{lang('aGallery', 'select_category', 'general')}</option>
              {foreach from=$AGALLERY_UPLOAD_CATEGORIES item=c}
                <option value="{$c.id}">{$c.name|escape}</option>
              {/foreach}
            </select>
          </div>

          <div class="field">
            <label>{lang('aGallery', 'title', 'general')}</label>
            <input type="text" name="title" maxlength="80" required>
          </div>

          <div class="field">
            <label>{lang('aGallery', 'description', 'general')}</label>
            <textarea name="description" maxlength="600"></textarea>
          </div>

          <div class="field">
            <label>{lang('aGallery', 'file', 'general')} (max {$AGALLERY_MAX_MB}MB)</label>
            <input type="file" name="file" accept="image/png,image/jpeg,image/webp,image/gif" required>
          </div>

          <button class="ui green button" type="submit">
            <i class="send icon"></i> {lang('aGallery', 'submit_for_review', 'general')}
          </button>
        </form>
      </div>
    </div>
  {/if}

  <div class="ui hidden divider"></div>

  {if count($AGALLERY_IMAGES) == 0}
    <div class="ui message">{lang('aGallery', 'no_images', 'general')}</div>
  {else}
    <div class="ui four stackable cards" id="agallery-grid">
      {foreach from=$AGALLERY_IMAGES item=img}
        <div class="card">
          <div class="image">
            <img
              loading="lazy"
              data-full="{$img.file_url|escape}"
              src="{$img.thumb_url|escape}"
              alt="{$img.title|escape}"
              class="agallery-thumb"
            >
          </div>
          <div class="content">
            <div class="header">{$img.title|escape}</div>
            <div class="meta">{$img.category|escape}</div>
          </div>
        </div>
      {/foreach}
    </div>

    <div class="ui modal" id="agallery-view-modal">
      <i class="close icon"></i>
      <div class="header">{lang('aGallery', 'view_image', 'general')}</div>
      <div class="content" style="text-align:center">
        <img id="agallery-view-img" src="" style="max-width:100%;height:auto" alt="">
      </div>
    </div>

    {if count($AGALLERY_PAGES) > 1}
      <div class="ui pagination menu" style="margin-top:16px">
        {foreach from=$AGALLERY_PAGES item=p}
          <a class="item {if $p.active}active{/if}" href="{$p.url|escape}">{$p.n}</a>
        {/foreach}
      </div>
    {/if}
  {/if}
</div>

<script>
(function(){
  // Fomantic modal open
  var btn = document.getElementById('agallery-upload-btn');
  if(btn){
    btn.addEventListener('click', function(){
      if(window.$ && $('#agallery-upload-modal').modal){
        $('#agallery-upload-modal').modal('show');
      }
    });
  }

  // Click to view full image
  function bindThumbs(){
    var thumbs = document.querySelectorAll('.agallery-thumb');
    thumbs.forEach(function(t){
      t.addEventListener('click', function(){
        var full = t.getAttribute('data-full');
        var img = document.getElementById('agallery-view-img');
        if(img) img.src = full;
        if(window.$ && $('#agallery-view-modal').modal){
          $('#agallery-view-modal').modal('show');
        } else {
          window.open(full, '_blank');
        }
      });
    });
  }
  bindThumbs();

  // Optional IntersectionObserver (extra lazy safety)
  if('IntersectionObserver' in window){
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if(e.isIntersecting){
          var el = e.target;
          // already has src; keep for compatibility
          io.unobserve(el);
        }
      });
    }, {rootMargin: '200px'});
    document.querySelectorAll('img[loading="lazy"]').forEach(function(img){ io.observe(img); });
  }
})();
</script>
