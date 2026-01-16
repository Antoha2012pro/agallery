{include file='header.tpl'}

<div class="ui container">
  <h1 class="ui header">{$TITLE}</h1>

  {if $SUCCESS}<div class="ui positive message">{$SUCCESS}</div>{/if}
  {if $ERROR}<div class="ui negative message">{$ERROR}</div>{/if}

  <div class="ui secondary menu">
    <a class="{if $STATUS=='pending'}active {/if}item" href="{url path='/panel/agallery/moderation' query='status=pending'}">{$L.pending}</a>
    <a class="{if $STATUS=='approved'}active {/if}item" href="{url path='/panel/agallery/moderation' query='status=approved'}">{$L.approved}</a>
    <a class="{if $STATUS=='declined'}active {/if}item" href="{url path='/panel/agallery/moderation' query='status=declined'}">{$L.declined}</a>
  </div>

  <table class="ui celled table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Preview</th>
        <th>Title</th>
        <th>Category</th>
        <th>User</th>
        <th>Date</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      {foreach from=$ITEMS item=i}
      <tr>
        <td>{$i->id}</td>
        <td><img src="/{$i->thumb_path}" style="max-width:120px" loading="lazy"></td>
        <td>{$i->title|escape}</td>
        <td>{$i->category_name|escape}</td>
        <td>{$i->user_id}</td>
        <td>{$i->created_at|date_format:"%Y-%m-%d %H:%M"}</td>
        <td><a class="ui button" href="{url path='/panel/agallery/moderation' query="status=`$STATUS`&id=`$i->id`"}">Open</a></td>
      </tr>
      {/foreach}
    </tbody>
  </table>

  {if $TOTAL_PAGES > 1}
    <div class="ui pagination menu">
      {section name=i start=1 loop=$TOTAL_PAGES+1}
        {assign var=p value=$smarty.section.i.index}
        <a class="{if $p == $PAGE_NO}active {/if}item" href="{url path='/panel/agallery/moderation' query="status=`$STATUS`&p=`$p`"}">{$p}</a>
      {/section}
    </div>
  {/if}

  {if $FOCUS}
    <div class="ui divider"></div>
    <h3 class="ui header">Request #{$FOCUS->id}</h3>
    <img src="/{$FOCUS->file_path}" style="max-width: 600px" loading="lazy">
    <p><b>Title:</b> {$FOCUS->title|escape}</p>
    <p><b>Description:</b> {$FOCUS->description|escape}</p>

    <form method="post" action="{url path='/panel/agallery/moderation' query="status=`$STATUS`"}" style="display:inline">
      <input type="hidden" name="token" value="{$TOKEN}">
      <input type="hidden" name="action" value="approve">
      <input type="hidden" name="image_id" value="{$FOCUS->id}">
      <button class="ui green button" type="submit" onclick="return confirm('{$L.confirm|escape}');">{$L.approve}</button>
    </form>

    <form method="post" action="{url path='/panel/agallery/moderation' query="status=`$STATUS`"}" class="ui form" style="margin-top:10px">
      <input type="hidden" name="token" value="{$TOKEN}">
      <input type="hidden" name="action" value="decline">
      <input type="hidden" name="image_id" value="{$FOCUS->id}">
      <div class="field">
        <label>{$L.reason}</label>
        <textarea name="reason" required></textarea>
      </div>
      <button class="ui red button" type="submit" onclick="return confirm('{$L.confirm|escape}');">{$L.decline}</button>
    </form>
  {/if}

</div>

{include file='footer.tpl'}
