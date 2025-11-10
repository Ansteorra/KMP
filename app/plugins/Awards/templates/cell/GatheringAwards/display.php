<?php

/**
 * Gathering Awards Cell Display Template
 * 
 * Renders the awards recommendations tab content for a gathering detail view.
 * Uses turbo-frame lazy loading to defer loading the recommendation table until
 * the tab is activated, improving initial page load performance.
 * 
 * @var int|null $gatheringId The ID of the gathering (may be null if permission denied)
 * @var bool $isEmpty Whether the gathering has any recommendations
 */

// If we don't have a gathering ID, permission was denied or gathering not found
if (!isset($gatheringId)) {
    echo '<p>Unable to load award recommendations for this gathering.</p>';
    return;
}

// Build URL to recommendation table with gathering_id filter
$url = $this->Url->build([
    'controller' => 'Recommendations',
    'action' => 'Table',
    'plugin' => 'Awards',
    'Event', // View config name
    '?' => ['gathering_id' => $gatheringId]
]);

if (!$isEmpty) : ?>
    <turbo-frame id="tableView-frame" loading="lazy" src="<?= $url ?>" data-turbo="true"></turbo-frame>
<?php else : ?>
    <p>No Award Recommendations for this gathering</p>
<?php endif; ?>