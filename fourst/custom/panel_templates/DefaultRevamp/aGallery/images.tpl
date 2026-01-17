<div class="ui segment">
  <h2 class="ui header">{$AGALLERY_TITLE}</h2>

  {if $AGALLERY_SUCCESS}<div class="ui positive message">{$AGALLERY_SUCCESS}</div>{/if}
  {if $AGALLERY_ERRORS && count($AGALLERY_ERRORS)}
    <div class="ui negative message"><ul>{foreach from=$AGALLERY_ERRORS item=e}<li>{$e}</li>{/foreach}</ul></div>
  {/if}

  <table class="ui celled table">
    <thead>
      <tr>
        <th>ID</th><th>Thumb</th><th>Title</th><th>Category</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      {foreach from=$AGALLERY_LIST item=img}
        <tr>
          <td>{$img->id}</td>
          <td>{if $img->thumb_path}<img src="{$img->thumb_path}" style="width:90px;height:auto;">{/if}</td>
          <td>
            <form class="ui form" method="post">
              <input type="hidden" name="token" value="{$AGALLERY_TOKEN}">
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="id" value="{$img->id}">
              <input type="text" name="title" value="{$img->title|escape}" required maxlength="128">
              <input type="text" name="description" value="{$img->description|escape}" maxlength="600">
              <select class="ui dropdown" name="category" required>
                {foreach from=$AGALLERY_CATS item=c}
                  <option value="{$c->id}" {if $c->id == $img->category_id}selected{/if}>{$c->name|escape}</option>
                {/foreach}
              </select>
              <button class="ui primary button" type="submit">Save</button>
            </form>
          </td>
          <td>{$img->category_id}</td>
          <td>
            <form method="post" onsubmit="return confirm('Delete image & files?');">
              <input type="hidden" name="token" value="{$AGALLERY_TOKEN}">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="{$img->id}">
              <button class="ui red button" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      {/foreach}
    </tbody>
  </table>

  <div class="ui pagination menu">
    {assign var=prev value=$AGALLERY_PAGE-1}
    {assign var=next value=$AGALLERY_PAGE+1}
    <a class="item {if $AGALLERY_PAGE<=1}disabled{/if}" href="?p={$prev}">«</a>
    {section name=i start=1 loop=$AGALLERY_PAGES+1}
      <a class="item {if $smarty.section.i.index==$AGALLERY_PAGE}active{/if}" href="?p={$smarty.section.i.index}">
        {$smarty.section.i.index}
      </a>
    {/section}
    <a class="item {if $AGALLERY_PAGE>=$AGALLERY_PAGES}disabled{/if}" href="?p={$next}">»</a>
  </div>
</div>

<script>
if (window.$) $('.ui.dropdown').dropdown();
</script>
