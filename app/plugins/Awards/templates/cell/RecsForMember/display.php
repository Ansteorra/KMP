<?php
$url = $this->URL->build(["controller" => "Recommendations", "action" => "table", "plugin" => "Awards", "SubmittedForMember", "?" => ["member_id" => $id]]);
if (!$isEmpty) : ?>
<turbo-frame id="tableView-frame" loading="lazy" src="<?= $url ?>" data-turbo='true'></turbo-frame>
<?php else :
    echo "<p>No Award Recs</p>";
endif; ?>