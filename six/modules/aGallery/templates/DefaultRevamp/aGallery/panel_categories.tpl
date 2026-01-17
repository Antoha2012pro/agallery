{include file='header.tpl'}

<div class="ui container">
    <h2 class="ui header">{$PANEL_TITLE}</h2>

    {if Session::exists('agallery_categories')}
        <div class="ui positive message">
            {Session::flash('agallery_categories')}
        </div>
    {/if}

    <h3 class="ui dividing header">{$smarty.const.LANG_AGALLERY_CATEGORIES}</h3>

    <table class="ui celled table">
        <thead>
            <tr>
                <th>ID</th>
                <th>{$smarty.const.LANG_AGALLERY_NAME}</th>
                <th>{$smarty.const.LANG_AGALLERY_DESCRIPTION}</th>
                <th>{$smarty.const.LANG_AGALLERY_SORT_ORDER}</th>
                <th>{$smarty.const.LANG_AGALLERY_ACTIONS}</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$AGALLERY_CATEGORIES item=cat}
                <tr>
                    <td>{$cat->id}</td>
                    <td>{$cat->name|escape}</td>
                    <td>{$cat->description|escape}</td>
                    <td>{$cat->sort_order}</td>
                    <td>
                        <!-- Edit/Delete forms simplified -->
                        <form class="ui form" method="post" style="display:inline;">
                            <input type="hidden" name="id" value="{$cat->id}">
                            <button name="delete" value="1" class="ui red mini button" onclick="return confirm('Delete?');">
                                {$smarty.const.LANG_AGALLERY_DELETE}
                            </button>
                            {$AGALLERY_TOKEN_INPUT nofilter}
                        </form>
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>

    <h3 class="ui dividing header">{$smarty.const.LANG_AGALLERY_CREATE_CATEGORY}</h3>

    <form class="ui form" method="post">
        <div class="field">
            <label>{$smarty.const.LANG_AGALLERY_NAME}</label>
            <input type="text" name="name" required>
        </div>
        <div class="field">
            <label>{$smarty.const.LANG_AGALLERY_DESCRIPTION}</label>
            <textarea name="description"></textarea>
        </div>
        <div class="field">
            <label>{$smarty.const.LANG_AGALLERY_SORT_ORDER}</label>
            <input type="number" name="sort_order" value="0">
        </div>
        <button class="ui primary button" type="submit" name="create" value="1">
            {$smarty.const.LANG_AGALLERY_SAVE}
        </button>
        {$AGALLERY_TOKEN_INPUT nofilter}
    </form>
</div>

{include file='footer.tpl'}
