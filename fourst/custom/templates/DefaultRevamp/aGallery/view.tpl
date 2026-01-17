<div class="ui container">
  <a class="ui button" href="{$AGALLERY_BACK_URL}">←</a>
  <h2 class="ui header">{$AGALLERY_IMG->title|escape}</h2>

  <div class="ui segment">
    <img src="{$AGALLERY_IMG->file_path}" style="max-width:100%;height:auto;" alt="{$AGALLERY_IMG->title|escape}">
    {if $AGALLERY_IMG->description}
      <div class="ui divider"></div>
      <p>{$AGALLERY_IMG->description|escape}</p>
    {/if}
  </div>
</div>
