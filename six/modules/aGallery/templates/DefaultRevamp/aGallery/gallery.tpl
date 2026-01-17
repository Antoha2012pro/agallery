{include file='header.tpl'}

<div class="ui container">
    <h2 class="ui header">
        {$AGALLERY_GALLERY_LABEL}
    </h2>

    {if isset($AGALLERY_SUCCESS)}
        <div class="ui positive message">
            {$AGALLERY_SUCCESS}
        </div>
    {/if}

    {if isset($AGALLERY_ERRORS)}
        <div class="ui negative message">
            <ul class="list">
                {foreach from=$AGALLERY_ERRORS item=err}
                    <li>{$err}</li>
                {/foreach}
            </ul>
        </div>
    {/if}

    {if $AGALLERY_CAN_UPLOAD}
        <button class="ui primary button" id="agallery-upload-open">
            {$AGALLERY_UPLOAD_LABEL}
        </button>
    {/if}

    <div class="ui divider"></div>

    <div class="ui three stackable cards" id="agallery-grid">
        {foreach from=$AGALLERY_IMAGES item=image}
            <div class="card">
                <div class="image">
                    <img src="{$image->thumb_path}" loading="lazy" alt="{$image->title|escape}">
                </div>
                <div class="content">
                    <div class="header">{$image->title|escape}</div>
                    <div class="meta">
                        {$image->username|escape}
                    </div>
                    <div class="description">
                        {$image->description|escape}
                    </div>
                </div>
            </div>
        {/foreach}
    </div>

    <div class="ui center aligned basic segment">
        {if $AGALLERY_TOTAL_PAGES > 1}
            <div class="ui pagination menu">
                {section name=p loop=$AGALLERY_TOTAL_PAGES+1 start=1}
                    <a class="item {if $AGALLERY_CURRENT_PAGE == $smarty.section.p.index}active{/if}" href="{url path='/gallery' query='p='|cat:$smarty.section.p.index}">
                        {$smarty.section.p.index}
                    </a>
                {/section}
            </div>
        {/if}
    </div>
</div>

{if $AGALLERY_CAN_UPLOAD}
    {include file='aGallery/gallery_upload_modal.tpl'}
{/if}

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('agallery-upload-open');
        if (btn) {
            btn.addEventListener('click', function () {
                $('.ui.modal.agallery-upload').modal('show');
            });
        }

        // Lazy load already handled by loading="lazy", IntersectionObserver можно добавить при необходимости.
    });
</script>

{include file='footer.tpl'}
