<?php
$url = $this->URL->build(["controller" => "Recommendations", "action" => "SubmittedForMember", "plugin" => "Awards", $id]);
if (!$isEmpty) : ?>
<turbo-frame id="AwardsFor-frame" loading="lazy" src="<?= $url ?>" data-turbo='true'></turbo-frame>
<?php else :
    echo "<p>No Award Recs</p>";
endif; ?>