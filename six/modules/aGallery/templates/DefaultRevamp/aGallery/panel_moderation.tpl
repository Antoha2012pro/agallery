{include file='header.tpl'}

<div class="ui container">
    <h2 class="ui header">{$PANEL_TITLE}</h2>

    <div class="ui pointing secondary menu">
        <a class="item {if $AGALLERY_STATUS == 'pending'}active{/if}" href="{url path='/panel/agallery/moderation' query='status=pending'}">Pending</a>
        <a class="item {if $AGALLERY_STATUS == 'approved'}active{/if}" href="{url path='/panel/agallery/moderation' query='status=approved'}">Approved</a>
        <a class="item {if $AGALLERY_STATUS == 'declined'}active{/if}" href="{url path='/panel/agallery/moderation' query='status=declined'}">Declined</a>
    </div>

    <table class="ui celled table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Thumb</th>
                <th>Title</th>
                <th>User</th>
                <th>Category</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$AGALLERY_IMAGES item=image}
                <tr>
                    <td>{$image->id}</td>
                    <td><img src="{$image->thumb_path}" style="max-width:80px;"></td>
                    <td>{$image->title|escape}</td>
                    <td>{$image->username|escape}</td>
                    <td>{$image->category_name|escape}</td>
                    <td>{$image->status}</td>
                    <td>{$image->created_at|date_format:"%Y-%m-%d %H:%M"}</td>
                    <td>
                        <form class="ui form" method="post">
                            <input type="hidden" name="image_id" value="{$image->id}">
                            {if $AGALLERY_STATUS == 'pending'}
                                <button class="ui green mini button" name="approve" value="1">Approve</button>
                                <div class="ui input mini">
                                    <input type="text" name="decline_reason" placeholder="Reason">
                                </div>
                                <button class="ui red mini button" name="decline" value="1">Decline</button>
                            {/if}
                            {$AGALLERY_TOKEN_INPUT nofilter}
                        </form>
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>

{include file='footer.tpl'}
