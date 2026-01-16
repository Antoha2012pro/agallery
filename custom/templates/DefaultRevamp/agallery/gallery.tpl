{include file='header.tpl'}

<div class="ui container">
  <h1 class="ui header">{$AGALLERY_TITLE}</h1>

  {if $SUCCESS}
    <div class="ui positive message">{$SUCCESS}</div>
  {/if}
  {if $ERROR}
    <div class="ui negative message">{$ERROR}</div>
  {/if}

  {if $CAN_UPLOAD}
    <button class="ui primary button" id="agalleryUploadBtn">
      <i class="upload icon"></i> {$AGALLERY_UPLOAD}
    </button>
  {/if}

  <div class="ui divider"></div>

  <div class="ui three stackable cards">
    {foreach from=$IMAGES item=img}
      <div class="card">
        <div class="image">
          <img loading="lazy" src="{$img->thumb_path}" data-full="{$img->file_path}" alt="{$img->title|escape}">
        </div>
        <div class="content">
          <div class="header">{$img->title|escape}</div>
          <div class="meta">{$img->category_name|escape}</div>
          {if $img->description}
            <div class="description">{$img->description|escape}</div>
          {/if}
        </div>
      </div>
    {/foreach}
  </div>

  {if $TOTAL_PAGES > 1}
    <div class="ui pagination menu" style="margin-top: 20px;">
      {section name=i start=1 loop=$TOTAL_PAGES+1}
        {assign var=p value=$smarty.section.i.index}
        <a class="{if $p == $PAGE}active {/if}item" href="{url path='/gallery' query="p=`$p`"}">{$p}</a>
      {/section}
    </div>
  {/if}
</div>

{if $CAN_UPLOAD}
<div class="ui modal" id="agalleryUploadModal">
  <i class="close icon"></i>
  <div class="header">{$AGALLERY_UPLOAD}</div>
  <div class="content">
    <form class="ui form" action="{url path='/gallery'}" method="post" enctype="multipart/form-data">
      <input type="hidden" name="token" value="{$TOKEN}">
      <div class="field">
        <label>{#field_category#}</label>
        <select class="ui dropdown" name="category" required>
          {foreach from=$UPLOAD_CATS item=c}
            <option value="{$c->id}">{$c->name|escape}</option>
          {/foreach}
        </select>
      </div>

      <div class="field">
        <label>{#field_title#}</label>
        <input type="text" name="title" maxlength="128" required>
      </div>

      <div class="field">
        <label>{#field_description#}</label>
        <textarea name="description" maxlength="2000"></textarea>
      </div>

      <div class="field">
        <label>{#field_file#}</label>
        <input type="file" name="file" accept="image/png,image/jpeg,image/webp,image/gif" required>
      </div>

      <button class="ui positive button" type="submit">{#send_review#}</button>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const btn = document.getElementById('agalleryUploadBtn');
  if (btn) {
    btn.addEventListener('click', function () {
      $('#agalleryUploadModal').modal('show');
    });
  }
});
</script>
{/if}

{include file='footer.tpl'}
