{include file='header.tpl'}

<div class="ui container">
    <h2 class="ui header">{$PANEL_TITLE}</h2>

    {if Session::exists('agallery_images')}
        <div class="ui positive message">
            {Session::flash('agallery_images')}
        </div>
    {/if}

    <table class="ui celled table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Thumb</th>
                <th>Title</th>
                <th>User</th>
                <th>Category</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$AGALLERY_IMAGES item=image}
                <tr>
                    <td>{$image->id}</td>
                    <td><img src="{$image->thumb_path}" style="max-width:80px;"></td>
                    <td>
                        <form class="ui form" method="post">
                            <input type="hidden" name="image_id" value="{$image->id}">
                            <div class="field">
                                <input type="text" name="title" value="{$image->title|escape}">
                            </div>
                            <div class="field">
                                <textarea name="description">{$image->description|escape}</textarea>
                            </div>
                            <div class="field">
                                <select name="category_id" class="ui dropdown">
                                    {foreach from=$AGALLERY_CATEGORIES item=cat}
                                        <option value="{$cat->id}" {if $cat->id == $image->category_id}selected{/if}>{$cat->name|escape}</option>
                                    {/foreach}
                                </select>
                            </div>
                            <button class="ui primary mini button" name="save" value="1">Save</button>
                            <button class="ui red mini button" name="delete" value="1" onclick="return confirm('Delete?');">Delete</button>
                            {$AGALLERY_TOKEN_INPUT nofilter}
                        </form>
                    </td>
                    <td>{$image->username|escape}</td>
                    <td>{$image->category_name|escape}</td>
                    <td></td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>

{include file='footer.tpl'}
