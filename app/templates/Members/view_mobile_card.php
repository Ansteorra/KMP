<?php

use Cake\I18n\DateTime;
use Cake\I18n\Date;
use Cake\Routing\Asset;

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': Mobile Activities Authorization Card';
$this->KMP->endBlock();
$cardUrl = $this->Url->build(['controller' => 'Members', 'action' => 'viewMobileCardJson', $member->mobile_card_token]);
$cardManifest = $this->Url->build(['controller' => 'Members', 'action' => 'card.webmanifest', $member->mobile_card_token]);
$swUrl = Asset::url("sw.js");
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
$now = Date::now();
?>
<?php echo $this->KMP->startBlock("manifest"); ?>
<link rel="manifest" href="<?= $cardManifest ?>" />
<?php $this->KMP->endBlock(); ?>
<style>
json {
    display: none;
}

.viewMobileCard {
    background-color: <?=h($message_variables["marshal_auth_header_color"],
        ) ?>;
}

.cardbox {
    background-color: rgb(255 255 255 / 85%) !important;
}

.card-body dl,
table.card-body-table tbody tr td,
table.card-body-table tbody tr th {
    background-color: rgb(255 255 255 / 60%) !important;

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
</style>
<div data-controller="member-mobile-card-pwa member-mobile-card-profile"
    data-member-mobile-card-profile-url-value="<?= $cardUrl ?>" data-member-mobile-card-pwa-sw-url-value="<?= $swUrl ?>"
    data-member-mobile-card-profile-pwa-ready-value="false">
    <div scope="col" class="col text-end mx-3 my-2">
        <span data-member-mobile-card-pwa-target="status"
            class="badge rounded-pill text-center bg-danger">Offline</span>
    </div>
    <div class="card cardbox mx-3">
        <div class="card-body">
            <h3 class="card-title text-center display-6">
                <?= h($message_variables["kingdom"]) ?><br />
                Activity Authorization
            </h3>
            <div class="text-center" data-member-mobile-card-profile-target="loading">
                <div class="spinner-border" style="width: 3rem; height: 3rem;" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            <dl class="row" hidden data-member-mobile-card-profile-target="memberDetails">
                <dt class="col-6 text-end">Legal Name</dt>
                <dd class="col-6" data-member-mobile-card-profile-target="name"></dd>
                <dt class="col-6 text-end">Society Name</dt>
                <dd class="col-6" data-member-mobile-card-profile-target="scaName"></dd>
                <dt class="col-6 text-end">Branch</dt>
                <dd class="col-6" data-member-mobile-card-profile-target="branchName"></dd>
                <dt class="col-6 text-end">Membership</dt>
                <dd class="col-6" data-member-mobile-card-profile-target="membershipInfo"></dd>
                <dt class="col-6 text-end">Background Check</dt>
                <dd class="col-6" data-member-mobile-card-profile-target="backgroundCheck"></dd>
                <dt class="col-6 text-end">Last Refresh</dt>
                <dd class="col-6" data-member-mobile-card-profile-target="lastUpdate"></dd>
                </dd>
            </dl>
        </div>
    </div>
    <div id="pluginCards" data-member-mobile-card-profile-target="cardSet"></div>
    <div scope="row" class="row ms-3 me-3 mb-5">
        <span scope="col" class="col text-center">
            <span data-member-mobile-card-pwa-target="refreshBtn"
                data-action="click->member-mobile-card-profile#loadCard"
                class="btn btn-small text-center btn-secondary bi bi-arrow-clockwise"></span>
        </span>
    </div>
    <div class="row text-center">
        <?= $this->element('copyrightFooter', []) ?>
    </div>
    <json data-member-mobile-card-pwa-target="urlCache">
        <?php
        $cacheList = [];
        $cacheList[] = $cardUrl;
        $cacheList[] = $swUrl;
        $cacheList[] = $this->KMP->getMixScriptUrl('manifest', $this->Url);
        $cacheList[] = $this->KMP->getMixScriptUrl('core', $this->Url);
        $cacheList[] = $this->KMP->getMixScriptUrl('controllers', $this->Url);
        $cacheList[] = $this->KMP->getMixScriptUrl('index', $this->Url);
        $cacheList[] = $this->KMP->getMixStyleUrl('app', $this->Url);
        $cacheList[] = Asset::imageUrl("favicon.ico");
        $cacheList[] = $this->request->getPath();
        echo json_encode($cacheList); ?>
    </json>
</div>