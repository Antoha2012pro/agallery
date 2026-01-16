{include file='header.tpl'}

<div class="ui container">
  <h1 class="ui header">{$TITLE}</h1>

  {if $SUCCESS}<div class="ui positive message">{$SUCCESS}</div>{/if}
  {if $ERROR}<div class="ui negative message">{$ERROR}</div>{/if}

  <table class="ui celled table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Thumb</th>
        <th>Title</th>
        <th>Category</th>
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
        <td>{$i->created_at|date_format:"%Y-%m-%d %H:%M"}</td>
        <td><a class="ui button" href="{url path='/panel/agallery/images' query="id=`$i->id`"}">Edit</a></td>
      </tr>
      {/foreach}
    </tbody>
  </table>

  {if $TOTAL_PAGES > 1}
    <div class="ui pagination menu">
      {section name=i start=1 loop=$TOTAL_PAGES+1}
        {assign var=p value=$smarty.section.i.index}
        <a class="{if $p == $PAGE_NO}active {/if}item" href="{url path='/panel/agallery/images' query="p=`$p`"}">{$p}</a>
      {/section}
    </div>
  {/if}

  {if $FOCUS}
    <div class="ui divider"></div>
    <h3 class="ui header">Edit #{$FOCUS->id}</h3>
    <img src="/{$FOCUS->file_path}" style="max-width: 600px" loading="lazy">

    <form class="ui form" method="post" action="{url path='/panel/agallery/images'}">
      <input type="hidden" name="token" value="{$TOKEN}">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="image_id" value="{$FOCUS->id}">

      <div class="field">
        <label>{$L.category}</label>
        <select class="ui dropdown" name="category" required>
          {foreach from=$CATS item=c}
            <option value="{$c->id}" {if $c->id == $FOCUS->category_id}selected{/if}>{$c->name|escape}</option>
          {/foreach}
        </select>
      </div>

      <div class="field">
        <label>{$L.title}</label>
        <input type="text" name="title" maxlength="128" value="{$FOCUS->title|escape}" required>
      </div>

      <div class="field">
        <label>{$L.description}</label>
        <textarea name="description">{$FOCUS->description|escape}</textarea>
      </div>

      <button class="ui primary button" type="submit">{$L.save}</button>
    </form>

    <form method="post" action="{url path='/panel/agallery/images'}" style="margin-top:10px" onsubmit="return confirm('{$L.confirm|escape}');">
      <input type="hidden" name="token" value="{$TOKEN}">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="image_id" value="{$FOCUS->id}">
      <button class="ui red button" type="submit">{$L.delete}</button>
    </form>
  {/if}
</div>

{include file='footer.tpl'}
