<div class="ui segment">
  <h2 class="ui header">{$AGALLERY_TITLE}</h2>

  {if $AGALLERY_SUCCESS}<div class="ui positive message">{$AGALLERY_SUCCESS}</div>{/if}
  {if $AGALLERY_ERRORS && count($AGALLERY_ERRORS)}
    <div class="ui negative message">
      <ul>{foreach from=$AGALLERY_ERRORS item=e}<li>{$e}</li>{/foreach}</ul>
    </div>
  {/if}

  <div class="ui menu">
    <a class="item {if $AGALLERY_STATUS=='pending'}active{/if}" href="?status=pending">Pending</a>
    <a class="item {if $AGALLERY_STATUS=='approved'}active{/if}" href="?status=approved">Approved</a>
    <a class="item {if $AGALLERY_STATUS=='declined'}active{/if}" href="?status=declined">Declined</a>
  </div>

  <table class="ui celled table">
    <thead>
      <tr>
        <th>ID</th><th>Thumb</th><th>Title</th><th>User</th><th>Category</th><th>Status</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      {foreach from=$AGALLERY_LIST item=img}
        <tr>
          <td>{$img->id}</td>
          <td>{if $img->thumb_path}<img src="{$img->thumb_path}" style="width:90px;height:auto;">{/if}</td>
          <td>{$img->title|escape}</td>
          <td>{$img->user_id}</td>
          <td>{$img->category_id}</td>
          <td>{$img->status}</td>
          <td>
            {if $img->status=='pending'}
              <form method="post" style="display:inline-block;">
                <input type="hidden" name="token" value="{$AGALLERY_TOKEN}">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="id" value="{$img->id}">
                <button class="ui green button" type="submit">Approve</button>
              </form>

              <form method="post" style="display:inline-block;" onsubmit="return confirm('Decline? Reason required');">
                <input type="hidden" name="token" value="{$AGALLERY_TOKEN}">
                <input type="hidden" name="action" value="decline">
                <input type="hidden" name="id" value="{$img->id}">
                <input type="text" name="reason" placeholder="Reason" required maxlength="255">
                <button class="ui red button" type="submit">Decline</button>
              </form>
            {else}
              —
            {/if}
          </td>
        </tr>
      {/foreach}
    </tbody>
  </table>

  <div class="ui pagination menu">
    {assign var=prev value=$AGALLERY_PAGE-1}
    {assign var=next value=$AGALLERY_PAGE+1}
    <a class="item {if $AGALLERY_PAGE<=1}disabled{/if}" href="?status={$AGALLERY_STATUS}&p={$prev}">«</a>
    {section name=i start=1 loop=$AGALLERY_PAGES+1}
      <a class="item {if $smarty.section.i.index==$AGALLERY_PAGE}active{/if}" href="?status={$AGALLERY_STATUS}&p={$smarty.section.i.index}">
        {$smarty.section.i.index}
      </a>
    {/section}
    <a class="item {if $AGALLERY_PAGE>=$AGALLERY_PAGES}disabled{/if}" href="?status={$AGALLERY_STATUS}&p={$next}">»</a>
  </div>
</div>
