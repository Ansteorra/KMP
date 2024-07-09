<?php
$url = $this->URL->build(["controller" => "Recommendations", "action" => "MemberSubmissions", "plugin" => "Awards", $id]);
echo $this->Html->link(
    __("Submit Award Rec."),
    ['controller' => 'Recommendations', 'action' => 'add', 'plugin' => 'Awards'],
    ["class" => "btn btn-primary"]
);
if (!$isEmpty) : ?>
<turbo-frame id="AwardRecs-frame" loading="lazy" src="<?= $url ?>" data-turbo='true'></turbo-frame>
<?php else :
    echo "<p>No Award Recs</p>";
endif; ?>