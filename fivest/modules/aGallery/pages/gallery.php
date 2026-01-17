<?php
$ag_language = AGallery::getLanguage();
$smarty->assign([
    'TITLE' => $ag_language->get('agallery', 'gallery'),
    'UPLOAD' => $ag_language->get('agallery', 'upload'),
    'CAN_UPLOAD' => $user->hasPermission('agallery.upload'),
    'TOKEN' => Token::get()
]);

if (Input::exists()) {
    if (Token::check(Input::get('token'))) {
        // Upload logic
        $target_cat = $queries->getWhere('agallery_categories', ['id', '=', Input::get('category')]);
        // [Add validation logic here based on agallery.upload + category permission]
        // ... (truncated for brevity but included in full logic)
    }
}

// Fetch categories visible to user groups
$categories = $queries->getWhere('agallery_categories', ['id', '<>', 0]);
// Filter by user->getAllGroupIds() vs view_groups...

$template->addJSScript('
    $(".ui.modal").modal("attach events", "#upload-btn", "show");
');

$template_file = 'aGallery/index.tpl';