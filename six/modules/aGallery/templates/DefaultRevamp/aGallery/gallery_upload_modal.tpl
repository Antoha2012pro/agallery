<div class="ui small modal agallery-upload">
    <div class="header">
        {$AGALLERY_UPLOAD_LABEL}
    </div>
    <div class="content">
        <form class="ui form" action="{url path='/gallery'}" method="post" enctype="multipart/form-data">
            <div class="field">
                <label>{$smarty.const.LANG_AGALLERY_CATEGORY}</label>
                <select name="category_id" class="ui dropdown">
                    {foreach from=$AGALLERY_CATEGORIES item=cat}
                        <option value="{$cat->id}">{$cat->name|escape}</option>
                    {/foreach}
                </select>
            </div>
            <div class="field">
                <label>{$smarty.const.LANG_AGALLERY_TITLE}</label>
                <input type="text" name="title" maxlength="255" required>
            </div>
            <div class="field">
                <label>{$smarty.const.LANG_AGALLERY_DESCRIPTION}</label>
                <textarea name="description" maxlength="1000"></textarea>
            </div>
            <div class="field">
                <label>{$smarty.const.LANG_AGALLERY_FILE}</label>
                <input type="file" name="image" accept="image/*" required>
            </div>
            {$AGALLERY_TOKEN_INPUT|default:'' nofilter}
            <input type="hidden" name="agallery_upload" value="1">
            <button type="submit" class="ui primary button">
                {$smarty.const.LANG_AGALLERY_SUBMIT}
            </button>
        </form>
    </div>
</div>
