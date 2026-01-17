{include file='header.tpl'}

<div class="ui container">
    <h2 class="ui header">{$PANEL_TITLE}</h2>

    {if Session::exists('agallery_settings')}
        <div class="ui positive message">
            {Session::flash('agallery_settings')}
        </div>
    {/if}

    <form class="ui form" method="post">
        <div class="fields">
            <div class="eight wide field">
                <label>Max upload (MB)</label>
                <input type="number" name="max_upload_mb" value="{$AGALLERY_SETTINGS.max_upload_mb}">
            </div>
            <div class="four wide field">
                <label>Max width</label>
                <input type="number" name="max_width" value="{$AGALLERY_SETTINGS.max_width}">
            </div>
            <div class="four wide field">
                <label>Max height</label>
                <input type="number" name="max_height" value="{$AGALLERY_SETTINGS.max_height}">
            </div>
        </div>
        <div class="field">
            <label>Allowed extensions (comma separated)</label>
            <input type="text" name="allowed_extensions" value="{$AGALLERY_SETTINGS.allowed_extensions|escape}">
        </div>
        <div class="fields">
            <div class="four wide field">
                <label>JPEG quality</label>
                <input type="number" name="image_quality_jpeg" value="{$AGALLERY_SETTINGS.image_quality_jpeg}">
            </div>
            <div class="four wide field">
                <label>WEBP quality</label>
                <input type="number" name="image_quality_webp" value="{$AGALLERY_SETTINGS.image_quality_webp}">
            </div>
            <div class="four wide field">
                <label>Thumb width</label>
                <input type="number" name="thumb_width" value="{$AGALLERY_SETTINGS.thumb_width}">
            </div>
            <div class="four wide field">
                <div class="ui checkbox">
                    <input type="checkbox" name="allow_convert" {if $AGALLERY_SETTINGS.allow_convert}checked{/if}>
                    <label>Allow convert</label>
                </div>
            </div>
        </div>
        {$AGALLERY_TOKEN_INPUT nofilter}
        <button class="ui primary button" type="submit">Save</button>
    </form>

    <h3 class="ui dividing header">Health Check</h3>

    <table class="ui celled table">
        <thead>
            <tr>
                <th>Path</th>
                <th>Exists</th>
                <th>Writable</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$AGALLERY_PATHS key=key item=item}
                <tr>
                    <td>{$item.path}</td>
                    <td>{if $item.exists}Yes{else}No{/if}</td>
                    <td>{if $item.writable}Yes{else}<span style="color:red;">No</span>{/if}</td>
                </tr>
            {/foreach}
        </tbody>
    </table>

    <h4 class="ui header">PHP limits</h4>
    <ul class="ui list">
        <li>upload_max_filesize: {$AGALLERY_LIMITS.upload_max_filesize}</li>
        <li>post_max_size: {$AGALLERY_LIMITS.post_max_size}</li>
        <li>memory_limit: {$AGALLERY_LIMITS.memory_limit}</li>
    </ul>

    {if $AGALLERY_LIMIT_COMPARE.warnings|@count}
        <div class="ui warning message">
            <div class="header">Limits too low</div>
            <p>Following limits are below module max_upload_mb ({$AGALLERY_LIMIT_COMPARE.module_limit} MB): {implode(", ", $AGALLERY_LIMIT_COMPARE.warnings)}</p>
        </div>
    {/if}
</div>

{include file='footer.tpl'}
