<?php

use Cake\I18n\Date;
use Cake\Routing\Asset;

function checkCardCount($cardCount)
{
    if ($cardCount == 2) {
        echo "</div><div style='clear:both'></div><div class='auth_cards'>";
        return 0;
    } else {
        return $cardCount;
    }
}
//home_marshal6.gif
$watermarkimg =
    "data:image/gif;base64," .
    base64_encode(
        file_get_contents(
            $this->Url->image($message_variables["marshal_auth_graphic"], [
                "fullBase" => true,
            ]),
        ),
    );
// sort authorization types by group
usort($member->authorizations, function ($a, $b) {
    return $a->activity->activity_group->name <=>
        $b->activity->activity_group->name;
});
$now = Date::now();
?>
<?php $this->start("manifest"); ?>
<link rel="manifest" href="<?= $this->Url->build([
                                "controller" => "Members",
                                "action" => "card.webmanifest",
                                $member->mobile_card_token,
                            ], ["fullBase" => true]) ?>" />
<?php $this->end(); ?>
<style>
.viewMobileCard {
    background-color: <?=h($message_variables["marshal_auth_header_color"],
        ) ?>;
}

.card-body::after {
    content: "";
    background-image: url('<?php echo $watermarkimg; ?>');
    background-size: 21.4rem 20rem;
    background-repeat: no-repeat;
    background-position: center;
    opacity: 1;
    top: 0;
    left: 0;
    bottom: 0;
    right: 0;
    position: absolute;
    z-index: -1;
    display: inline-block;
}

.cardbox {
    background-color: rgb(255 255 255 / 85%) !important;
}
</style>

<div class="card cardbox m-3">
    <div class="card-body">
        <h3 class="card-title text-center display-6">
            <?= h($message_variables["kingdom"]) ?><br />
            Activity Authorization
        </h3>
        <dl class="row">
            <dt class="col-6 text-end">Legal Name</dt>
            <dd class="col-6"><?= h($member->first_name) ?> <?= h($member->last_name) ?></dd>
            <dt class="col-6 text-end">Society Name</dt>
            <dd class="col-6"><?= h($member->sca_name) ?></dd>
            <dt class="col-6 text-end">Branch</dt>
            <dd class="col-6"><?= h($member->branch->name) ?></dd>
            <dt class="col-6 text-end">Membership</dt>
            <dd class="col-6"><?= h($member->membership_number) ?> Expires:<?= h(
                                                                                $member->membership_expires_on,
                                                                            ) ?></dd>
            <dt class="col-6 text-end">Background Check</dt>
            <dd class="col-6">
                <?php if ($member->background_check_expires_on > $now) { ?>
                <b>* Current *</b> : <?= h(
                                                $member->background_check_expires_on,
                                            ) ?>
                <?php } else { ?>
                <?php if ($member->background_check_expires_on == null) { ?>
                <b>* Not on file *</b>
                <?php } else { ?>
                <b>* Expired *</b>: <?= h(
                                                $member->background_check_expires_on,
                                            ) ?>
                <?php } ?>
                <?php } ?>
            </dd>
        </dl>
    </div>
</div>
<?php if (count($authTypes) > 0) : ?>
<div class="card cardbox m-3">
    <div class="card-body">
        <h3 class="card-title text-center display-6">Authorizing Marshal for:</h3>
        <table class='table '>
            <tbody>
                <?php $i = 0; ?>
                <?php foreach ($authTypes as $role) : ?>
                <?php $i++; ?>
                <?php if ($i == 1) : ?>
                <tr scope="row">
                    <?php endif; ?>
                    <td class="col-6 text-center"><?= str_replace(
                                                                "Authorizing Marshal",
                                                                "",
                                                                $role,
                                                            ) ?></td>
                    <?php if ($i == 2) : ?>
                </tr>
                <?php $i = 0; ?>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($i == 1) : ?>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php
$group = "";
$authCount = 0;
?>
<div class="card cardbox m-3">
    <div class="card-body">
        <h3 class="card-title text-center display-6">Authorizations:</h3>
        <table class='table '>
            <tbody>
                <?php if (empty($member->authorizations)) : ?>
                <tr scope="row">
                    <td class="col-12 text-center">No Authorizations</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($member->authorizations as $auth) : ?>

                <?php if (
                        $group !=
                        $auth->activity
                        ->activity_group->name
                    ) : ?>
                <?php $group =
                            $auth->activity
                            ->activity_group->name; ?>
                <tr scope="row">
                    <th class="col-12 text-center" colspan="2" class="cardboxAuthorizationsLabel">
                        <?= $group ?>
                    </th>
                </tr>
                <?php endif; ?>
                <tr scope="row">
                    <td class="col-6 text-end"><?= $auth
                                                        ->activity->name ?></td>
                    <td class="col-6 text-start"><?= $auth->expires_on->toDateString() ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div id="status" class="badge rounded-pill ms-3 text-center"></div>
<?php
$this->append(
    "script",
    $this->Html->script(["app/members/view_mobile_card.js"])
);
$this->append(
    "script",
    $this->Html->scriptBlock(
        "
    const urlCache = [
        '" . $this->request->getPath() . "',
        'https://ajax.aspnetcdn.com/ajax/jQuery/jquery-3.7.1.min.js',
        '" . Asset::scriptUrl("BootstrapUI.popper.min") . "',
        '" . Asset::scriptUrl("BootstrapUI.bootstrap.min") . "',
        '" . Asset::scriptUrl("app/sw.js") . "',
        '" . Asset::scriptUrl("app/members/view_mobile_card.js") . "',
        '" . Asset::cssUrl("BootstrapUI.bootstrap.min") . "',
        '" . Asset::cssUrl("BootstrapUI./font/bootstrap-icons") . "',
        '" . Asset::cssUrl("BootstrapUI./font/bootstrap-icon-sizes") . "',
        '" . Asset::imageUrl("favicon.ico") . "'
    ];
    swPath = '" . Asset::scriptUrl("app/sw.js") . "';
    $(document).ready(function() {
        var pageControl = new memberViewMobileCard();
        pageControl.run(urlCache,swPath);
    })",
    ),
);
?>