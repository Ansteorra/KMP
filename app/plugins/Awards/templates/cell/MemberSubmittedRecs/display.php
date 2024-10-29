<?php
$url = $this->URL->build(["controller" => "Recommendations", "action" => "Table", "plugin" => "Awards", "SubmittedByMember", "?" => ["member_id" => $id]]);
echo $this->Html->link(
    __("Submit Award Rec."),
    ['controller' => 'Recommendations', 'action' => 'add', 'plugin' => 'Awards'],
    ["class" => "btn btn-primary"]
);
if (!$isEmpty) : ?>
<turbo-frame id="tableView-frame" loading="lazy" src="<?= $url ?>" data-turbo='true'></turbo-frame>
<?php else :
    echo "<p>No Award Recs</p>";
endif; ?>