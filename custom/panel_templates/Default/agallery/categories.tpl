{include file='header.tpl'}

<div class="ui container">
  <h1 class="ui header">{$TITLE}</h1>

  {if $SUCCESS}<div class="ui positive message">{$SUCCESS}</div>{/if}
  {if $ERROR}<div class="ui negative message">{$ERROR}</div>{/if}

  <h3 class="ui header">{$L.categories}</h3>

  <table class="ui celled table">
    <thead>
      <tr>
        <th>ID</th>
        <th>{$L.name}</th>
        <th>{$L.sort}</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      {foreach from=$CATS item=c}
      <tr>
        <td>{$c->id}</td>
        <td>{$c->name|escape}</td>
        <td>{$c->sort_order}</td>
        <td>
          <a class="ui button" href="{url path='/panel/agallery/categories' query="edit=`$c->id`"}">{$L.edit}</a>
          <form style="display:inline" method="post" action="{url path='/panel/agallery/categories'}" onsubmit="return confirm('{$L.confirm|escape}');">
            <input type="hidden" name="token" value="{$TOKEN}">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="{$c->id}">
            <button class="ui red button" type="submit">{$L.delete}</button>
          </form>
        </td>
      </tr>
      {/foreach}
    </tbody>
  </table>

  <div class="ui divider"></div>

  {assign var=editId value=$smarty.get.edit|default:0}
  {assign var=editing value=false}
  {foreach from=$CATS item=c}
    {if $editId == $c->id}{assign var=editing value=true}{assign var=editCat value=$c}{/if}
  {/foreach}

  <h3 class="ui header">{if $editing}{$L.edit}{else}{$L.add}{/if}</h3>

  <form class="ui form" method="post" action="{url path='/panel/agallery/categories'}">
    <input type="hidden" name="token" value="{$TOKEN}">
    <input type="hidden" name="action" value="{if $editing}update{else}create{/if}">
    <input type="hidden" name="id" value="{if $editing}{$editCat->id}{else}0{/if}">

    <div class="field">
      <label>{$L.name}</label>
      <input type="text" name="name" maxlength="64" value="{if $editing}{$editCat->name|escape}{/if}" required>
    </div>

    <div class="field">
      <label>{$L.description}</label>
      <textarea name="description">{if $editing}{$editCat->description|escape}{/if}</textarea>
    </div>

    <div class="field">
      <label>{$L.sort}</label>
      <input type="number" name="sort_order" value="{if $editing}{$editCat->sort_order}{else}0{/if}">
    </div>

    <div class="field">
      <label>{$L.view_groups}</label>
      <select name="view_groups[]" multiple class="ui dropdown">
        {foreach from=$GROUPS item=g}
          <option value="{$g->id}">{$g->name|escape}</option>
        {/foreach}
      </select>
      <div class="ui tiny message">Пусто = видно всем.</div>
    </div>

    <div class="field">
      <label>{$L.upload_groups}</label>
      <select name="upload_groups[]" multiple class="ui dropdown">
        {foreach from=$GROUPS item=g}
          <option value="{$g->id}">{$g->name|escape}</option>
        {/foreach}
      </select>
      <div class="ui tiny message">Пусто = можно всем (но всё равно нужен agallery.upload).</div>
    </div>

    <button class="ui primary button" type="submit">{$L.save}</button>
  </form>
</div>

{include file='footer.tpl'}
