<?php
$user = $this->request->getAttribute("identity");
$this->extend("/layout/TwitterBootstrap/signin");

?>

<div class="card">
    <?= $this->html->image("NoAccessKnight.png", [
        "class" => "card-img",
        "alt" => "No Access Knight",
    ]) ?>
    <div class="card-img-overlay">
        <h1 class="card-title text-start">Not Found</h1>
    </div>
</div>
<?= $this->Html->link(
    "Return to your Profile",
    ['controller' => 'Members', 'action' => 'view', $user->id],
    ["class" => "btn btn-primary mt-3"]
) ?>