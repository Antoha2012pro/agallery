<div class="ui segment">
  <h2 class="ui header">{$AGALLERY_TITLE}</h2>

  {if $AGALLERY_SUCCESS}<div class="ui positive message">{$AGALLERY_SUCCESS}</div>{/if}
  {if $AGALLERY_ERRORS && count($AGALLERY_ERRORS)}
    <div class="ui negative message">
      <ul>
        {foreach from=$AGALLERY_ERRORS item=e}<li>{$e}</li>{/foreach}
      </ul>
    </div>
  {/if}

  <h3 class="ui header">Create</h3>
  <form class="ui form" method="post">
    <input type="hidden" name="token" value="{$AGALLERY_TOKEN}">
    <input type="hidden" name="action" value="create">
    <div class="field required">
      <label>Name</label>
      <input type="text" name="name" maxlength="64" required>
    </div>
    <div class="field">
      <label>Description</label>
      <input type="text" name="description" maxlength="255">
    </div>
    <div class="field">
      <label>Sort order</label>
      <input type="number" name="sort_order" value="0">
    </div>

    <div class="field">
      <label>view_groups (empty => everyone)</label>
      <select class="ui fluid dropdown" name="view_groups[]" multiple>
        {foreach from=$AGALLERY_GROUPS item=g}
          <option value="{$g.id}">#{$g.id} {$g.name|escape}</option>
        {/foreach}
      </select>
    </div>

    <div class="field">
      <label>upload_groups (empty => nobody)</label>
      <select class="ui fluid dropdown" name="upload_groups[]" multiple>
        {foreach from=$AGALLERY_GROUPS item=g}
          <option value="{$g.id}">#{$g.id} {$g.name|escape}</option>
        {/foreach}
      </select>
    </div>

    <button class="ui primary button" type="submit">Save</button>
  </form>

  <div class="ui divider"></div>

  <h3 class="ui header">Existing</h3>
  <table class="ui celled table">
    <thead>
      <tr>
        <th>ID</th><th>Name</th><th>Sort</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      {foreach from=$AGALLERY_CATS item=c}
        <tr>
          <td>{$c->id}</td>
          <td>{$c->name|escape}</td>
          <td>{$c->sort_order}</td>
          <td>
            <form method="post" style="display:inline-block;">
              <input type="hidden" name="token" value="{$AGALLERY_TOKEN}">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="{$c->id}">
              <button class="ui red button" type="submit" onclick="return confirm('Delete?')">Delete</button>
            </form>
          </td>
        </tr>
      {/foreach}
    </tbody>
  </table>
</div>

<script>
if (window.$) $('.ui.dropdown').dropdown();
</script>
