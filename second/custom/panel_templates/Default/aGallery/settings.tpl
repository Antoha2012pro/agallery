<div class="content-wrapper">
  <section class="content-header">
    <h1>{$TITLE}</h1>
  </section>

  <section class="content">
    {include file=$TABS_TEMPLATE}

    {if count($ERRORS)}
      <div class="ui negative message"><ul>{foreach from=$ERRORS item=e}<li>{$e|escape}</li>{/foreach}</ul></div>
    {/if}
    {if $SUCCESS}
      <div class="ui positive message">{$SUCCESS|escape}</div>
    {/if}

    <div class="ui segment">
      <form class="ui form" method="post">
        <input type="hidden" name="token" value="{$TOKEN}">

        <div class="field">
          <label>{$smarty.const.LANG.agallery_max_upload_mb}</label>
          <input type="number" name="max_upload_mb" value="{$DATA.max_upload_mb}">
        </div>

        <div class="two fields">
          <div class="field">
            <label>{$smarty.const.LANG.agallery_max_width}</label>
            <input type="number" name="max_width" value="{$DATA.max_width}">
          </div>
          <div class="field">
            <label>{$smarty.const.LANG.agallery_max_height}</label>
            <input type="number" name="max_height" value="{$DATA.max_height}">
          </div>
        </div>

        <div class="field">
          <label>{$smarty.const.LANG.agallery_allowed_extensions}</label>
          <input type="text" name="allowed_extensions" value="{implode(',', $DATA.allowed_extensions)}">
          <small>{$smarty.const.LANG.agallery_allowed_extensions_note}</small>
        </div>

        <div class="two fields">
          <div class="field">
            <label>{$smarty.const.LANG.agallery_quality_jpeg}</label>
            <input type="number" name="image_quality_jpeg" value="{$DATA.image_quality_jpeg}">
          </div>
          <div class="field">
            <label>{$smarty.const.LANG.agallery_quality_webp}</label>
            <input type="number" name="image_quality_webp" value="{$DATA.image_quality_webp}">
          </div>
        </div>

        <div class="field">
          <label>{$smarty.const.LANG.agallery_thumb_width}</label>
          <input type="number" name="thumb_width" value="{$DATA.thumb_width}">
        </div>

        <div class="field">
          <div class="ui checkbox">
            <input type="checkbox" name="allow_conversion" {if $DATA.allow_conversion==1}checked{/if}>
            <label>{$smarty.const.LANG.agallery_allow_conversion}</label>
          </div>
        </div>

        <div class="field">
          <label>{$smarty.const.LANG.agallery_convert_to}</label>
          <select name="convert_to" class="ui dropdown">
            <option value="jpg" {if $DATA.convert_to=='jpg'}selected{/if}>JPG</option>
            <option value="webp" {if $DATA.convert_to=='webp'}selected{/if}>WebP</option>
          </select>
        </div>

        <button class="ui primary button" type="submit">{$smarty.const.LANG.agallery_save}</button>
      </form>
    </div>
  </section>
</div>