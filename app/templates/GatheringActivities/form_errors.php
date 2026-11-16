<?php
declare(strict_types=1);
?>
<turbo-stream action="update" target="gatheringActivityFormFrame">
    <template><?= $this->element('gatheringActivities/editorForm', compact('gatheringActivity')) ?></template>
</turbo-stream>
