<div class="content-wrapper">
  <section class="content-header">
    <h1>{$TITLE}</h1>
  </section>

  <section class="content">
    {include file=$TABS_TEMPLATE}

    {if count($ERRORS)}
      <div class="ui negative message"><ul>{foreach from=$ERRORS item=e}<li>{$e|escape}</li>{/foreach}</ul></div>
    {/if}
    {if $SUCCESS}
      <div class="ui positive message">{$SUCCESS|escape}</div>
    {/if}

    <div class="ui segment">
      <h3 class="ui header">{if $EDIT}{$smarty.const.LANG.agallery_edit}{else}{$smarty.const.LANG.agallery_create}{/if}</h3>

      <form class="ui form" method="post">
        <input type="hidden" name="token" value="{$TOKEN}">
        {if $EDIT}<input type="hidden" name="id" value="{$EDIT->id}">{/if}

        <div class="field">
          <label>{$smarty.const.LANG.agallery_name}</label>
          <input type="text" name="name" maxlength="64" value="{if $EDIT}{$EDIT->name|escape}{/if}" required>
        </div>

        <div class="field">
          <label>{$smarty.const.LANG.agallery_description}</label>
          <textarea name="description">{if $EDIT}{$EDIT->description|escape}{/if}</textarea>
        </div>

        <div class="field">
          <label>{$smarty.const.LANG.agallery_sort_order}</label>
          <input type="number" name="sort_order" value="{if $EDIT}{$EDIT->sort_order}{else}0{/if}">
        </div>

        <div class="two fields">
          <div class="field">
            <label>{$smarty.const.LANG.agallery_view_groups}</label>
            {foreach from=$GROUPS item=g}
              <div class="ui checkbox">
                <input type="checkbox" name="view_groups[]" value="{$g->id}" {if in_array($g->id, $EDIT_VIEW_GROUPS)}checked{/if}>
                <label>{$g->name|escape}</label>
              </div><br>
            {/foreach}
          </div>

          <div class="field">
            <label>{$smarty.const.LANG.agallery_upload_groups}</label>
            {foreach from=$GROUPS item=g}
              <div class="ui checkbox">
                <input type="checkbox" name="upload_groups[]" value="{$g->id}" {if in_array($g->id, $EDIT_UPLOAD_GROUPS)}checked{/if}>
                <label>{$g->name|escape}</label>
              </div><br>
            {/foreach}
          </div>
        </div>

        {if $EDIT}
          <button class="ui primary button" name="update" value="1">{$smarty.const.LANG.agallery_save}</button>
          <button class="ui red button" name="delete" value="1" onclick="return confirm('Delete?')">{$smarty.const.LANG.agallery_delete}</button>
        {else}
          <button class="ui primary button" name="create" value="1">{$smarty.const.LANG.agallery_create}</button>
        {/if}
      </form>
    </div>

    <div class="ui segment">
      <h3 class="ui header">{$smarty.const.LANG.agallery_list}</h3>
      <table class="ui celled table">
        <thead><tr>
          <th>ID</th><th>{$smarty.const.LANG.agallery_name}</th><th>{$smarty.const.LANG.agallery_sort_order}</th><th></th>
        </tr></thead>
        <tbody>
        {foreach from=$CATEGORIES item=c}
          <tr>
            <td>{$c->id}</td>
            <td>{$c->name|escape}</td>
            <td>{$c->sort_order}</td>
            <td><a class="ui button" href="{URL::build('/panel/agallery/categories','action=edit&id='|cat:$c->id)}">{$smarty.const.LANG.agallery_edit}</a></td>
          </tr>
        {/foreach}
        </tbody>
      </table>
    </div>
  </section>
</div>