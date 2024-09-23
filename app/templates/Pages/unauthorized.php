<?php

use Cake\Routing\Asset;

$this->extend("/layout/TwitterBootstrap/signin");

?>

<div class="card" data-controller="delay-forward" data-delay-forward-delay-ms-value="5000"
    data-delay-forward-url-value="<?= Asset::Url("/", ['fullBase' => true]) ?>">
    <?= $this->html->image("NoAccessKnight.png", [
        "class" => "card-img",
        "alt" => "No Access Knight",
    ]) ?>
    <div class="card-img-overlay">
        <h1 class="card-title text-start">Unauthorized Request</h1>
    </div>
</div>
<?= $this->Html->link(
    "Return to your Profile",
    ['controller' => 'Members', 'action' => 'view', $user->id],
    ["class" => "btn btn-primary mt-3"]
) ?>