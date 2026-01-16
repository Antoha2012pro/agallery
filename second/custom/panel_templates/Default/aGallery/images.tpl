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
      <table class="ui celled table">
        <thead><tr>
          <th>ID</th><th>{$smarty.const.LANG.agallery_thumb}</th><th>{$smarty.const.LANG.agallery_title}</th><th>{$smarty.const.LANG.agallery_user}</th><th>{$smarty.const.LANG.agallery_category}</th><th>{$smarty.const.LANG.agallery_actions}</th>
        </tr></thead>
        <tbody>
        {foreach from=$LIST item=img}
          <tr>
            <td>{$img->id}</td>
            <td><img src="/{$img->thumb_path|escape}" style="width:120px" loading="lazy"></td>
            <td>{$img->title|escape}</td>
            <td>{$img->username|escape}</td>
            <td>{$img->category_name|escape}</td>
            <td>
              <details>
                <summary>{$smarty.const.LANG.agallery_edit}</summary>
                <form class="ui form" method="post">
                  <input type="hidden" name="token" value="{$TOKEN}">
                  <input type="hidden" name="image_id" value="{$img->id}">
                  <div class="field">
                    <label>{$smarty.const.LANG.agallery_title}</label>
                    <input type="text" name="title" value="{$img->title|escape}" maxlength="128" required>
                  </div>
                  <div class="field">
                    <label>{$smarty.const.LANG.agallery_description}</label>
                    <textarea name="description">{$img->description|escape}</textarea>
                  </div>
                  <div class="field">
                    <label>{$smarty.const.LANG.agallery_category}</label>
                    <select name="category_id" class="ui dropdown">
                      {foreach from=$CATEGORIES item=c}
                        <option value="{$c->id}" {if $c->id == $img->category_id}selected{/if}>{$c->name|escape}</option>
                      {/foreach}
                    </select>
                  </div>
                  <button class="ui primary button" name="update" value="1">{$smarty.const.LANG.agallery_save}</button>
                  <button class="ui orange button" name="recompress" value="1">{$smarty.const.LANG.agallery_recompress}</button>
                  <button class="ui red button" name="delete" value="1" onclick="return confirm('Delete?')">{$smarty.const.LANG.agallery_delete}</button>
                </form>
              </details>
            </td>
          </tr>
        {/foreach}
        </tbody>
      </table>

      {if $TOTAL_PAGES > 1}
        <div class="ui pagination menu">
          {section name=i start=1 loop=$TOTAL_PAGES+1}
            {assign var=p value=$smarty.section.i.index}
            <a class="item {if $p==$PAGE}active{/if}" href="{URL::build('/panel/agallery/images','p='|cat:$p)}">{$p}</a>
          {/section}
        </div>
      {/if}
    </div>
  </section>
</div>