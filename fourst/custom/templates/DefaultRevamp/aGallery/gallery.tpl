{* Frontend: /gallery *}

<div class="ui container">
  <h1 class="ui header">{$AGALLERY_TITLE}</h1>

  {if $AGALLERY_SUCCESS}
    <div class="ui positive message">{$AGALLERY_SUCCESS}</div>
  {/if}
  {if $AGALLERY_ERROR}
    <div class="ui negative message">{$AGALLERY_ERROR}</div>
  {/if}

  {if $AGALLERY_CAN_UPLOAD}
    <button class="ui primary button" id="agallery-open-upload">
      <i class="upload icon"></i> {$language->get('agallery','upload')}
    </button>

    <div class="ui modal" id="agallery-upload-modal">
      <i class="close icon"></i>
      <div class="header">{$language->get('agallery','upload')}</div>
      <div class="content">
        <form class="ui form" action="{$AGALLERY_UPLOAD_URL}" method="post" enctype="multipart/form-data" id="agallery-upload-form">
          <input type="hidden" name="token" value="{$AGALLERY_TOKEN}">

          <div class="field required">
            <label>{$language->get('agallery','category')}</label>
            <select class="ui dropdown" name="category" required>
              <option value="">--</option>
              {foreach from=$AGALLERY_UPLOAD_CATS item=c}
                <option value="{$c->id}">{$c->name}</option>
              {/foreach}
            </select>
          </div>

          <div class="field required">
            <label>{$language->get('agallery','title')}</label>
            <input type="text" name="title" maxlength="64" required>
          </div>

          <div class="field">
            <label>{$language->get('agallery','description')}</label>
            <textarea name="description" maxlength="500"></textarea>
          </div>

          <div class="field required">
            <label>{$language->get('agallery','file')}</label>
            <input type="file" name="file" accept="image/png,image/jpeg,image/webp,image/gif" required>
          </div>

          <button class="ui green button" type="submit">
            <i class="paper plane icon"></i> {$language->get('agallery','send_for_review')}
          </button>
        </form>
      </div>
    </div>
  {/if}

  <div class="ui divider"></div>

  {if $AGALLERY_TOTAL == 0}
    <div class="ui message">—</div>
  {else}
    <div class="ui three stackable cards">
      {foreach from=$AGALLERY_IMAGES item=img}
        <a class="card" href="{$AGALLERY_VIEW_URL}?id={$img->id}">
          <div class="image">
            <img loading="lazy" data-src="{$img->thumb_path}" alt="{$img->title|escape}" class="agallery-lazy">
          </div>
          <div class="content">
            <div class="header">{$img->title|escape}</div>
            {if $img->description}<div class="description">{$img->description|escape}</div>{/if}
          </div>
        </a>
      {/foreach}
    </div>

    <div class="ui pagination menu" style="margin-top: 20px;">
      {assign var=prev value=$AGALLERY_PAGE-1}
      {assign var=next value=$AGALLERY_PAGE+1}

      <a class="item {if $AGALLERY_PAGE <= 1}disabled{/if}" href="{$AGALLERY_URL_BASE}?p={$prev}">«</a>

      {section name=i start=1 loop=$AGALLERY_PAGES+1}
        <a class="item {if $smarty.section.i.index == $AGALLERY_PAGE}active{/if}" href="{$AGALLERY_URL_BASE}?p={$smarty.section.i.index}">
          {$smarty.section.i.index}
        </a>
      {/section}

      <a class="item {if $AGALLERY_PAGE >= $AGALLERY_PAGES}disabled{/if}" href="{$AGALLERY_URL_BASE}?p={$next}">»</a>
    </div>
  {/if}
</div>

<script>
(function(){
  // Fomantic modal
  var btn = document.getElementById('agallery-open-upload');
  if (btn && window.$) {
    btn.addEventListener('click', function(){
      $('#agallery-upload-modal').modal('show');
    });
    $('.ui.dropdown').dropdown();
  }

  // IntersectionObserver lazy-load
  var imgs = document.querySelectorAll('img.agallery-lazy[data-src]');
  if (!imgs.length) return;

  function load(img){
    img.src = img.getAttribute('data-src');
    img.removeAttribute('data-src');
  }

  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if (e.isIntersecting) {
          load(e.target);
          io.unobserve(e.target);
        }
      });
    }, {rootMargin: '200px'});
    imgs.forEach(function(img){ io.observe(img); });
  } else {
    imgs.forEach(load);
  }
})();
</script>
