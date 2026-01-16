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
      <div class="ui menu">
        <a class="item {if $STATUS=='pending'}active{/if}" href="{URL::build('/panel/agallery/moderation','status=pending')}">pending</a>
        <a class="item {if $STATUS=='approved'}active{/if}" href="{URL::build('/panel/agallery/moderation','status=approved')}">approved</a>
        <a class="item {if $STATUS=='declined'}active{/if}" href="{URL::build('/panel/agallery/moderation','status=declined')}">declined</a>
      </div>

      <table class="ui celled table">
        <thead><tr>
          <th>ID</th><th>{$smarty.const.LANG.agallery_thumb}</th><th>{$smarty.const.LANG.agallery_title}</th><th>{$smarty.const.LANG.agallery_user}</th><th>{$smarty.const.LANG.agallery_category}</th><th></th>
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
              <a class="ui button" href="{URL::build('/panel/agallery/moderation','status='|cat:$STATUS|cat:'&id='|cat:$img->id)}">{$smarty.const.LANG.agallery_view}</a>
            </td>
          </tr>
        {/foreach}
        </tbody>
      </table>

      {if $TOTAL_PAGES > 1}
        <div class="ui pagination menu">
          {section name=i start=1 loop=$TOTAL_PAGES+1}
            {assign var=p value=$smarty.section.i.index}
            <a class="item {if $p==$PAGE}active{/if}" href="{URL::build('/panel/agallery/moderation','status='|cat:$STATUS|cat:'&p='|cat:$p)}">{$p}</a>
          {/section}
        </div>
      {/if}
    </div>

    {if $FOCUS}
    <div class="ui segment">
      <h3 class="ui header">#{$FOCUS->id} — {$FOCUS->title|escape}</h3>
      <p><b>{$smarty.const.LANG.agallery_user}:</b> {$FOCUS->username|escape}</p>
      <p><b>{$smarty.const.LANG.agallery_category}:</b> {$FOCUS->category_name|escape}</p>
      <img src="/{$FOCUS->file_path|escape}" style="max-width: 100%; height:auto;">

      <form class="ui form" method="post" style="margin-top: 15px;">
        <input type="hidden" name="token" value="{$TOKEN}">
        <input type="hidden" name="image_id" value="{$FOCUS->id}">

        {if $FOCUS->status == 'pending'}
          <button class="ui green button" name="approve" value="1">{$smarty.const.LANG.agallery_approve}</button>

          <div class="field" style="margin-top: 10px;">
            <label>{$smarty.const.LANG.agallery_decline_reason}</label>
            <textarea name="decline_reason" required></textarea>
          </div>
          <button class="ui red button" name="decline" value="1">{$smarty.const.LANG.agallery_decline}</button>
        {else}
          <div class="ui message">Status: {$FOCUS->status}</div>
          {if $FOCUS->decline_reason}
            <div class="ui warning message">{$FOCUS->decline_reason|escape}</div>
          {/if}
        {/if}
      </form>
    </div>
    {/if}
  </section>
</div>