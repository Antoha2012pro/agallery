<div class="ui secondary pointing menu">
  <a class="item {if $ACTIVE_TAB=='categories'}active{/if}" href="{URL::build('/panel/agallery/categories')}">{$smarty.const.LANG.agallery_staffcp_categories}</a>
  <a class="item {if $ACTIVE_TAB=='moderation'}active{/if}" href="{URL::build('/panel/agallery/moderation')}">{$smarty.const.LANG.agallery_staffcp_moderation}</a>
  <a class="item {if $ACTIVE_TAB=='images'}active{/if}" href="{URL::build('/panel/agallery/images')}">{$smarty.const.LANG.agallery_staffcp_images}</a>
  <a class="item {if $ACTIVE_TAB=='settings'}active{/if}" href="{URL::build('/panel/agallery/settings')}">{$smarty.const.LANG.agallery_staffcp_settings}</a>
</div>