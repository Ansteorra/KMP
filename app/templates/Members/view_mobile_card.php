<?php

use Cake\I18n\DateTime;
use Cake\I18n\Date;
use Cake\Routing\Asset;
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
<link rel="manifest" href="<?= $this->Url->build([
                                "controller" => "Members",
                                "action" => "card.webmanifest",
                                $member->mobile_card_token,
                            ], ["fullBase" => true]) ?>" />
<?php $this->KMP->endBlock(); ?>
<style>
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
<div scope="col" class="col text-end mx-3 my-2">
    <span id="status" class="badge rounded-pill text-center bg-danger">Offline</span>
</div>
<div class="card cardbox mx-3">
    <div class="card-body">
        <h3 class="card-title text-center display-6">
            <?= h($message_variables["kingdom"]) ?><br />
            Activity Authorization
        </h3>
        <div class="text-center" id="loading">
            <div class="spinner-border" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        <dl class="row" style="display:none" id="memberDetails">
            <dt class="col-6 text-end">Legal Name</dt>
            <dd class="col-6" id="member_name"></dd>
            <dt class="col-6 text-end">Society Name</dt>
            <dd class="col-6" id="member_sca_name"></dd>
            <dt class="col-6 text-end">Branch</dt>
            <dd class="col-6" id="member_branch_name"></dd>
            <dt class="col-6 text-end">Membership</dt>
            <dd class="col-6" id="member_membership_info"></dd>
            <dt class="col-6 text-end">Background Check</dt>
            <dd class="col-6" id="member_background_check"></dd>
            <dt class="col-6 text-end">Last Refresh</dt>
            <dd class="col-6" id="last_update"></dd>
            </dd>
        </dl>
    </div>
</div>
<div id="pluginCards"></div>
<div scope="row" class="row ms-3 me-3 mb-5">
    <span scope="col" class="col text-center">
        <span id="refresh" class="btn btn-small text-center btn-secondary bi bi-arrow-clockwise"></span>
    </span>
</div>
<div class="row text-center">
    <?= $this->element('copyrightFooter', []) ?>
</div>
<?php
echo $this->KMP->startBlock('script'); ?>
<script>
class memberViewMobileCard {
    constructor() {
        this.ac = null;
    };
    updateOnlineStatus() {
        const statusDiv = document.getElementById('status');
        const refreshButton = $('#refresh');
        if (navigator.onLine) {
            statusDiv.textContent = 'Online';
            statusDiv.classList.remove('bg-danger');
            statusDiv.classList.add('bg-success');
            refreshButton.show();
        } else {
            statusDiv.textContent = 'Offline';
            statusDiv.classList.remove('bg-success');
            statusDiv.classList.add('bg-danger');
            refreshButton.hide();
        }
    }
    refreshPageIfOnline() {
        if (navigator.onLine) {
            window.location.reload();
        }
    }
    run(urlsToCache, swPath) {
        var me = this;
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                me.updateOnlineStatus();
                window.addEventListener('online', me.updateOnlineStatus);
                window.addEventListener('offline', me.updateOnlineStatus);
                navigator.serviceWorker.register(swPath)
                    .then(registration => {
                        console.log('Service Worker registered with scope:', registration.scope);
                        registration.active.postMessage({
                            type: 'CACHE_URLS',
                            payload: urlsToCache
                        });
                    }, error => {
                        console.log('Service Worker registration failed:', error);
                    });
            });
        }
        setInterval(me.refreshPageIfOnline, 300000);
    }
}
const urlCache = [
    '<?= $this->request->getPath() ?>',
    'https://ajax.aspnetcdn.com/ajax/jQuery/jquery-3.7.1.min.js',
    '<?= Asset::scriptUrl("BootstrapUI.popper.min") ?>',
    '<?= Asset::scriptUrl("BootstrapUI.bootstrap.min") ?>',
    '<?= Asset::scriptUrl("app/sw.js") ?>',
    '<?= Asset::cssUrl("BootstrapUI.bootstrap.min") ?>',
    '<?= Asset::cssUrl("BootstrapUI./font/bootstrap-icons") ?>',
    '<?= Asset::cssUrl("BootstrapUI./font/bootstrap-icon-sizes") ?>',
    '<?= Asset::imageUrl("favicon.ico") ?>',
    '<?= $this->Url->build(['controller' => 'Members', 'action' => 'viewMobileCardJson', $member->mobile_card_token]) ?>'
];
swPath = '<?= Asset::scriptUrl("app/sw.js") ?>';

var url =
    '<?= $this->Url->build(['controller' => 'Members', 'action' => 'viewMobileCardJson', $member->mobile_card_token]) ?>';
var currentCard = null;
var cardCount = 0;

function startCard(title) {
    cardCount++;
    var card = $("<div>", {
        class: "card cardbox m-3",
        id: "card_" + cardCount
    });
    cardDetails = $("<div>", {
        class: "card-body",
        id: "cardDetails_" + cardCount
    });
    $("<h3>", {
        class: "card-title text-center display-6"
    }).text(title).appendTo(cardDetails);
    card.append(cardDetails);
    $("#pluginCards").append(card);
    currentCard = cardDetails;
}

function appendToCard(element) {
    currentCard.append(element);
}

function loadCard() {
    $("#pluginCards").html("");
    $("#loading").show();
    $("#memberDetails").hide();
    $.get(url, function(data) {
        $("#loading").hide();
        $("#memberDetails").show();
        $('#member_name').text(data.member.first_name + ' ' + data.member.last_name);
        $('#member_sca_name').text(data.member.sca_name);
        $('#member_branch_name').text(data.member.branch.name);
        $('#member_membership_info').text(data.member.membership_number + ' ' + data.member
            .membership_expires_on);
        if (data.member.membership_number && data.member.membership_number.length > 0) {
            var memberExpDate = new Date(data.member.membership_expires_on);
            if (memberExpDate < new Date()) {
                memberExpDate = "Expired";
            } else {
                memberExpDate = " - " + memberExpDate.toLocaleDateString();
            }
            $('#member_membership_info').text(data.member.membership_number + ' ' + memberExpDate);
        } else {
            $('#member_membership_info').text("No Membership Info");
        }
        if (data.member.background_check_expires_on) {
            var backgroundCheckExpDate = new Date(data.member.background_check_expires_on);
            if (backgroundCheckExpDate < new Date()) {
                backgroundCheckExpDate = "Expired";
            } else {
                backgroundCheckExpDate = 'Current' + backgroundCheckExpDate.toLocaleDateString();
            }
            $('#member_background_check').append("strong").text(backgroundCheckExpDate);
        } else {
            $('#member_background_check').text("Not on file");
        }
        $('#last_update').text(new Date().toLocaleString());
        for (let key in data) {
            if (key === 'member') {
                continue;
            }
            var pluginData = data[key];
            for (let sectionKey in pluginData) {
                var sectionData = pluginData[sectionKey];
                var keysCount = Object.keys(sectionData).length;
                if (keysCount > 0) {
                    startCard(sectionKey);
                } else {
                    continue;
                }
                var groupTable = $("<table>", {
                    class: "table card-body-table"
                });
                var groupTableBody = $("<tbody>");
                groupTable.append(groupTableBody);

                for (let groupKey in sectionData) {
                    groupData = sectionData[groupKey];
                    if (groupData.length === 0) {
                        continue;
                    }
                    var groupRow = $("<tr>", {
                        scope: "row"
                    });
                    var groupHeader = $("<th>", {
                        class: "col-12 text-center",
                        colspan: "2",
                    }).text(groupKey);
                    groupRow.append(groupHeader);
                    groupTableBody.append(groupRow);
                    var colCount = 0;
                    var groupRow = $("<tr>", {
                        scope: "row"
                    });
                    var textAlignClass = "text-center";
                    for (let i = 0; i < groupData.length; i++) {
                        var itemData = groupData[i];
                        if (colCount == 2) {
                            groupTable.append(groupRow);
                            groupRow = $("<tr>", {
                                scope: "row"
                            });
                            textAlignClass = "text-center";
                            colCount = 0;
                        } else {
                            textAlignClass = "text-center";
                        }
                        //if there is a : split it into 2 columns of data
                        if (itemData.indexOf(":") > 2) {
                            var itemValue = itemData.split(":");
                            var itemValueRow = $("<tr>", {
                                scope: "row"
                            });
                            var itemValueCol1 = $("<td>", {
                                class: "col-6 text-end",
                            }).text(itemValue[0]);
                            var itemValueCol2 = $("<td>", {
                                class: "col-6 text-start",
                            }).text(itemValue[1]);
                            itemValueRow.append(itemValueCol1);
                            itemValueRow.append(itemValueCol2);
                            groupTable.append(itemValueRow);
                            colCount = 2;
                        } else {
                            var colspan = 1;
                            if (i + 1 == groupData.length && colCount == 0) {
                                var colspan = 2;
                            }
                            var itemValueCol = $("<td>", {
                                class: "col-6 " + textAlignClass,
                                colspan: colspan
                            }).text(itemData);
                            groupRow.append(itemValueCol);
                            colCount++;
                        }
                    }
                    groupTableBody.append(groupRow);
                }
                appendToCard(groupTable);
            }
        }
    });
}
$(document).ready(function() {
    var pageControl = new memberViewMobileCard();
    pageControl.run(urlCache, swPath);
    loadCard();
    $('#refresh').click(function() {
        if (navigator.onLine) {
            loadCard();
        }
    });
});
</script>
<?php
echo $this->KMP->endBlock(); ?>