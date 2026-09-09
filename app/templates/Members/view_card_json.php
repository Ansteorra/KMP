<?php
declare(strict_types=1);
?>{
"member": <?= json_encode(!empty($mobileCardDto) ? $member->extract(['first_name', 'last_name', 'sca_name', 'membership_number', 'membership_expires_on', 'background_check_expires_on', 'branch', 'profile_photo_url', 'can_share_rsvp_with_kingdom']) : $member) ?>
<?php

use App\Services\ViewCellRegistry;

if (isset($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_JSON]) && !empty($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_JSON])) :
    foreach ($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_JSON] as $cell) : ?>
, "<?= $cell['id'] ?>": <?= $this->cell($cell["cell"], [$member->id]) ?>
<?php endforeach;
endif; ?>
}