{include file='header.tpl'}

<div class="ui container">
  <h1 class="ui header">{$GALLERY_TITLE}</h1>

  {if $CAN_UPLOAD}
    <button class="ui primary button" id="agalleryUploadBtn">{$UPLOAD_BUTTON}</button>
  {/if}

  <div class="ui divider"></div>

  {if count($IMAGES)}
    <div class="ui four stackable cards">
      {foreach from=$IMAGES item=img}
        <div class="card">
          <div class="image">
            <img src="/{$img->thumb_path|escape}" loading="lazy" alt="{$img->title|escape}">
          </div>
          <div class="content">
            <div class="header">{$img->title|escape}</div>
            <div class="meta">
              {$img->category_name|escape} · {$img->username|escape}
            </div>
            {if $img->description}
              <div class="description">{$img->description|escape}</div>
            {/if}
          </div>
        </div>
      {/foreach}
    </div>
  {else}
    <div class="ui message">
      {$smarty.const.LANG.no_items}
    </div>
  {/if}

  {if $TOTAL_PAGES > 1}
    <div class="ui pagination menu" style="margin-top: 20px;">
      {section name=i start=1 loop=$TOTAL_PAGES+1}
        {assign var=p value=$smarty.section.i.index}
        <a class="item {if $p == $PAGE}active{/if}" href="{$PAGINATION_BASE}?p={$p}">{$p}</a>
      {/section}
    </div>
  {/if}
</div>

{if $CAN_UPLOAD}
<div class="ui modal" id="agalleryUploadModal">
  <i class="close icon"></i>
  <div class="header">{$UPLOAD_MODAL_TITLE}</div>
  <div class="content">
    <form class="ui form" id="agalleryUploadForm">
      <input type="hidden" name="token" value="{$TOKEN}">
      <div class="field">
        <label>{$FIELD_CATEGORY}</label>
        <select class="ui dropdown" name="category" required>
          <option value="">--</option>
          {foreach from=$UPLOADABLE_CATEGORIES item=c}
            <option value="{$c->id}">{$c->name|escape}</option>
          {/foreach}
        </select>
      </div>
      <div class="field">
        <label>{$FIELD_TITLE}</label>
        <input type="text" name="title" maxlength="128" required>
      </div>
      <div class="field">
        <label>{$FIELD_DESCRIPTION}</label>
        <textarea name="description" maxlength="2000"></textarea>
      </div>
      <div class="field">
        <label>{$FIELD_FILE}</label>
        <input type="file" name="file" accept="image/png,image/jpeg,image/webp,image/gif" required>
      </div>

      <button class="ui primary button" type="submit">{$SUBMIT_FOR_REVIEW}</button>
      <div class="ui hidden message" id="agalleryUploadMsg"></div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('agalleryUploadBtn');
  const modal = $('#agalleryUploadModal');
  const form = document.getElementById('agalleryUploadForm');
  const msg = document.getElementById('agalleryUploadMsg');

  if (btn) btn.addEventListener('click', () => modal.modal('show'));

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    msg.className = 'ui hidden message';
    const fd = new FormData(form);

    try {
      const res = await fetch('{$UPLOAD_URL}', { method: 'POST', body: fd });
      const data = await res.json();

      if (data.ok) {
        msg.className = 'ui positive message';
        msg.textContent = data.message || 'OK';
        form.reset();
      } else {
        msg.className = 'ui negative message';
        msg.textContent = data.error || 'Error';
      }
    } catch (err) {
      msg.className = 'ui negative message';
      msg.textContent = 'Network/Server error';
    }
  });
});
</script>
{/if}

{include file='footer.tpl'}