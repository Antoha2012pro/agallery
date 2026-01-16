{include file='header.tpl'}

<div class="ui container">
  <h1 class="ui header">{$TITLE}</h1>

  {if $SUCCESS}<div class="ui positive message">{$SUCCESS}</div>{/if}
  {if $ERROR}<div class="ui negative message">{$ERROR}</div>{/if}

  <form class="ui form" method="post" action="{url path='/panel/agallery/settings'}">
    <input type="hidden" name="token" value="{$TOKEN}">

    <div class="field">
      <label>{$L.max_upload_mb}</label>
      <input type="number" name="max_upload_mb" value="{$S.max_upload_mb|escape}">
    </div>

    <div class="two fields">
      <div class="field">
        <label>{$L.max_width}</label>
        <input type="number" name="max_width" value="{$S.max_width|escape}">
      </div>
      <div class="field">
        <label>{$L.max_height}</label>
        <input type="number" name="max_height" value="{$S.max_height|escape}">
      </div>
    </div>

    <div class="field">
      <label>{$L.allowed_extensions}</label>
      <input type="text" name="allowed_extensions" value="{$S.allowed_extensions|escape}">
      <div class="ui tiny message">Принимаются только форматы, которые реально декодируются и пересохраняются на сервере (GD).</div>
    </div>

    <div class="two fields">
      <div class="field">
        <label>{$L.jpeg_q}</label>
        <input type="number" name="image_quality_jpeg" value="{$S.image_quality_jpeg|escape}">
      </div>
      <div class="field">
        <label>{$L.webp_q}</label>
        <input type="number" name="image_quality_webp" value="{$S.image_quality_webp|escape}">
      </div>
    </div>

    <div class="field">
      <label>{$L.thumb_w}</label>
      <input type="number" name="thumb_width" value="{$S.thumb_width|escape}">
    </div>

    <div class="inline field">
      <div class="ui checkbox">
        <input type="checkbox" name="convert_to_jpg" {if $S.convert_to_jpg == '1'}checked{/if}>
        <label>{$L.to_jpg}</label>
      </div>
    </div>

    <div class="inline field">
      <div class="ui checkbox">
        <input type="checkbox" name="convert_to_webp" {if $S.convert_to_webp == '1'}checked{/if}>
        <label>{$L.to_webp}</label>
      </div>
    </div>

    <button class="ui primary button" type="submit">{$L.save}</button>
  </form>
</div>

{include file='footer.tpl'}
