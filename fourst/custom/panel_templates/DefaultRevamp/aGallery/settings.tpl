<div class="ui segment">
  <h2 class="ui header">{$AGALLERY_TITLE}</h2>

  {if $AGALLERY_SUCCESS}<div class="ui positive message">{$AGALLERY_SUCCESS}</div>{/if}
  {if $AGALLERY_ERRORS && count($AGALLERY_ERRORS)}
    <div class="ui negative message"><ul>{foreach from=$AGALLERY_ERRORS item=e}<li>{$e}</li>{/foreach}</ul></div>
  {/if}

  <form class="ui form" method="post">
    <input type="hidden" name="token" value="{$AGALLERY_TOKEN}">

    <div class="three fields">
      <div class="field">
        <label>max_upload_mb</label>
        <input type="number" name="max_upload_mb" value="{$AGALLERY_SETTINGS.max_upload_mb}">
      </div>
      <div class="field">
        <label>max_width</label>
        <input type="number" name="max_width" value="{$AGALLERY_SETTINGS.max_width}">
      </div>
      <div class="field">
        <label>max_height</label>
        <input type="number" name="max_height" value="{$AGALLERY_SETTINGS.max_height}">
      </div>
    </div>

    <div class="field">
      <label>allowed_extensions</label>
      <input type="text" name="allowed_extensions" value="{$AGALLERY_SETTINGS.allowed_extensions}">
      <small>Реально принимаются только форматы, которые сервер может декодировать/кодировать.</small>
    </div>

    <div class="three fields">
      <div class="field">
        <label>image_quality_jpeg</label>
        <input type="number" name="image_quality_jpeg" value="{$AGALLERY_SETTINGS.image_quality_jpeg}">
      </div>
      <div class="field">
        <label>image_quality_webp</label>
        <input type="number" name="image_quality_webp" value="{$AGALLERY_SETTINGS.image_quality_webp}">
      </div>
      <div class="field">
        <label>thumb_width</label>
        <input type="number" name="thumb_width" value="{$AGALLERY_SETTINGS.thumb_width}">
      </div>
    </div>

    <div class="field">
      <div class="ui checkbox">
        <input type="checkbox" name="allow_convert" {if $AGALLERY_SETTINGS.allow_convert=='1'}checked{/if}>
        <label>allow_convert</label>
      </div>
    </div>

    <button class="ui primary button" type="submit">Save</button>
  </form>

  <div class="ui divider"></div>

  <h3 class="ui header">Health Check</h3>
  <table class="ui celled table">
    <thead>
      <tr><th>Path</th><th>Exists</th><th>Writable</th></tr>
    </thead>
    <tbody>
      {foreach from=$AGALLERY_HEALTH item=h}
        <tr>
          <td><code>{$h.path|escape}</code></td>
          <td>{if $h.exists}yes{else}no{/if}</td>
          <td>{if $h.writable}yes{else}<span class="ui red text">no</span>{/if}</td>
        </tr>
      {/foreach}
    </tbody>
  </table>

  <h4 class="ui header">PHP limits</h4>
  <ul>
    <li>upload_max_filesize: <code>{$AGALLERY_PHP_LIMITS.upload_max_filesize}</code>{if in_array('upload_max_filesize',$AGALLERY_PHP_WARN)} <span class="ui red text">(< max_upload_mb)</span>{/if}</li>
    <li>post_max_size: <code>{$AGALLERY_PHP_LIMITS.post_max_size}</code>{if in_array('post_max_size',$AGALLERY_PHP_WARN)} <span class="ui red text">(< max_upload_mb)</span>{/if}</li>
    <li>memory_limit: <code>{$AGALLERY_PHP_LIMITS.memory_limit}</code></li>
  </ul>
</div>

<script>
if (window.$) $('.ui.checkbox').checkbox();
</script>
