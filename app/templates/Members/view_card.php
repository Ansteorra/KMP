<?php

use Cake\I18n\Date;



echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': Activities Authorization Card';
$this->KMP->endBlock();

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
$now = Date::now();
?>
<html>

<head>
    <style>
    .letter {
        font-size: 10pt;
        clear: both;
        margin-left: 10px;
        margin-right: 10px;
        margin-top: 10px;
        margin-bottom: 20px;
    }

    .header {
        width: 100%;
        height: 68px;
    }

    .header-left,
    .header-right {
        float: left;
        width: 20%;
        text-align: center;
    }

    .header-left img,
    .header-right img {
        height: 68px;
    }

    .header-center {
        background-color: <?=h($message_variables["marshal_auth_header_color"],
            ) ?>;
        float: left;
        font-size: 18pt;
        text-align: center;
        width: 60%;
        vertical-align: middle;
        font-weight: bold;
    }

    .cardbox::after {
        content: "";
        background-image: url('<?php echo $watermarkimg; ?>');
        background-size: 45mm 42mm;
        background-repeat: no-repeat;
        background-position: center;
        opacity: 0.05;
        top: 0;
        left: 0;
        bottom: 0;
        right: 0;
        position: absolute;
        z-index: -1;
        display: inline-block;
    }

    .cardbox {
        border: .5mm;
        border-style: solid;
        border-radius: 3mm;
        border-color: black;
        width: 48mm;
        height: 75mm;
        text-align: center;
        position: relative;
        margin: 0px;
        overflow: hidden;
        float: left;
    }

    .auth_cards {
        text-align: center;
        font-size: 0;
    }

    .auth_card {
        display: inline-block;
        margin: 0;
    }

    .cardboxheader {
        font-weight: bold;
        font-size: 9pt;
    }

    .cardbox dl {
        display: block;
        width: 60%;
        margin: 0px;
        margin-left: 5px;
        padding: 0px;
        text-align: left;
        font-size: 7pt;
        margin-bottom: 0px;
    }

    .cardbox dl dt {
        font-weight: 900;
        margin: 0px;
        padding: 0px;
        display: block;
    }

    .cardbox dl dd {
        margin-left: 3px;
        margin-bottom: 0px;
        display: block;
    }

    .cardbox ul {
        display: block;
        width: 100%;
        margin-left: 5px;
        padding: 0px;
        text-align: left;
        font-size: 7pt;
        margin-bottom: 0px;
        list-style: none;
    }

    .cardbox h3 {
        font-size: 9pt;
        font-weight: bold;
        padding-top: 2px;
        padding-bottom: 2px;
        margin-top: 3px;
        margin-bottom: 3px;
        border-top: black 1px solid;
        border-bottom: black 1px solid;
        border: left 0 right 0;
    }

    .cardbox h5 {
        font-size: 7pt;
        font-weight: bold;
        border: left 0 right 0;
    }

    .cardboxAuthorizingLabel,
    .cardboxAuthorizationsLabel {
        font-size: 7pt;
        font-weight: 900;
        width: 95%;
    }

    .cardboxAuthorizing {
        margin: 0px;
        padding: 0px;
        margin-left: 5px;
        font-size: 7pt;
        list-style: none;
        text-align: left;
    }

    .cardboxAuthorizations {
        margin: 0px;
        padding: 0px;
        margin-left: 5px;
        font-size: 7pt;
        list-style: none;
        text-align: center;
        width: 95%;
    }

    hr {
        border: .5mm;
        border-style: solid;
        border-color: black;
        margin: 0px;
    }
    </style>
</head>

<body>
    <div class="header">
        <div class="header-left">
            <img src='<?php echo $watermarkimg; ?>'>
        </div>
        <div class="header-center">
            Kingdom of <?= h($message_variables["kingdom"]) ?><br />
            Activities Authorization
        </div>
        <div class="header-right">
            <img src='<?php echo $watermarkimg; ?>'>
        </div>
        <div style="clear:both"></div>
    </div>
    <div class="letter">
        <p>Greetings <?= h($message_variables["kingdom"]) ?> Participant, </p>

        <p>You will be pleased to find your new activities authorization card below. Please note that while
            there is an expiration date, it can be revoked per the customs and laws of the Kingdom of <?= h(
                                                                                                            $message_variables["kingdom"],
                                                                                                        ) ?> and the
            Society for Creative Anachronism. Your authorization comes from the Crown, Earl Marshal and respective
            deputies, so remember that you are representing the Crown and their trust in you everytime you take the
            field. </p>

        <p>Remember to have your authorization card with you at any SCA event or practice that you will be
            fighting or marshalling. It can also be asked to be seen by the Marshal in Charge or a senior Marshal at any
            time. At most interkingdom wars, it is normal to also be required to provide your site token and legal
            identification when being inspected. </p>

        <p>Cut off around the edges of the below box, fold on the dotted line and keep the card safe. Please feel free
            to print out multiple copies and keep them where you will have them available at SCA events and practices.
        </p>

        <p>It is recommend that you laminate your card to protect from moisture (dew, sweat, water, etc). You can do
            this by buying a laminating pouch or carefully putting clear packing tape on both sides to cover. </p>

        <p>If something is missing or is incorrect, don't hesitate to contact me. </p>

        <p>In Service,<br />
            <?= h($message_variables["secratary"]) ?><br />
            Kingdom of <?= h(
                            $message_variables["kingdom"],
                        ) ?> - Society for Creative Anachronism<br />
            <?= h($message_variables["secretary_email"]) ?><br />
        </p>
    </div>
    <div class="auth_cards" id="auth_cards">
        <div class="auth_card" id="card_1">
            <div class="cardbox" id="cardDetails_1">
                <div class="cardboxheader">
                    Kingdom of <?= h($message_variables["kingdom"]) ?><br />
                    Martial Authorization
                </div>
                <h2 id="loading">Loading...</h2>
            </div>
        </div>

    </div>
</body>

</html>
<?php
echo $this->KMP->startBlock('script'); ?>
<script>
var url = '<?= $this->Url->build(['controller' => 'Members', 'action' => 'viewCardJson', $member->id]) ?>';
var cardCount = 1;
var currentCard = {};
var maxCardLength = 0;

function usedSpaceInCard() {
    var cardChildren = currentCard.children();
    var runningTotal = 0;
    for (var i = 0; i < cardChildren.length; i++) {
        runningTotal += $(cardChildren[i]).height();
    }
    return runningTotal;
}

function appendToCard(element, minSpace) {
    currentCard.append(element);
    if (minSpace === null) {
        minSpace = 2;
    }
    minSpace = maxCardLength * (minSpace / 100);
    if (usedSpaceInCard() > (maxCardLength - minSpace)) {
        debugger;
        currentCard.remove(element);
        startCard();
        currentCard.append(element);
    }
}

function startCard() {
    cardCount++;
    var card = $("<div>", {
        class: "auth_card",
        id: "card_" + cardCount
    });
    cardDetails = $("<div>", {
        class: "cardbox",
        id: "cardDetails_" + cardCount
    });
    card.append(cardDetails);
    $("#auth_cards").append(card);
    currentCard = cardDetails;
}
window.addEventListener('DOMContentLoaded', function() {
    currentCard = $("#cardDetails_1");
    maxCardLength = currentCard.height();
    $.get(url, function(data) {
        $("#loading").hide();
        var detailsList = $("<dl>", {
            id: "details"
        });
        detailsList.append($("<dt>").text("Legal Name"));
        detailsList.append($("<dd>").text(data.member.first_name + ' ' + data.member.last_name));
        detailsList.append($("<dt>").text("Society Name"));
        detailsList.append($("<dd>").text(data.member.sca_name));
        detailsList.append($("<dt>").text("Branch"));
        detailsList.append($("<dd>").text(data.member.branch.name));
        detailsList.append($("<dt>").text("Membership Info"));
        if (data.member.membership_number && data.member.membership_number.length > 0) {
            var memberExpDate = new Date(data.member.membership_expires_on);
            if (memberExpDate < new Date()) {
                memberExpDate = "Expired";
            } else {
                memberExpDate = " - " + memberExpDate.toLocaleDateString();
            }
            detailsList.append($("<dd>").text(data.member.membership_number + ' ' + memberExpDate));
        } else {
            detailsList.append($("<dd>").text("No Membership Info"));
        }
        if (data.member.background_check_expires_on) {
            var backgroundCheckExpDate = new Date(data.member.background_check_expires_on);
            if (backgroundCheckExpDate < new Date()) {
                backgroundCheckExpDate = "Expired";
            } else {
                backgroundCheckExpDate = " - " + backgroundCheckExpDate.toLocaleDateString();
            }
            detailsList.append($("<dt>").text("Background Check"));
            detailsList.append($("<dd>").append("strong").text(backgroundCheckExpDate));
        } else {
            detailsList.append($("<dt>").text("Background Check"));
            detailsList.append($("<dd>").text("No Background Check"));
        }
        appendToCard(detailsList);
        for (let key in data) {
            if (key === 'member') {
                continue;
            }
            var pluginData = data[key];
            for (let sectionKey in pluginData) {
                var sectionData = pluginData[sectionKey];
                var groupCount = sectionData.length;
                if (groupCount === 0) {
                    continue;
                }
                var sectionHeader = $("<h3>").text(sectionKey);
                appendToCard(sectionHeader, 20);
                for (let groupKey in sectionData) {
                    var groupData = sectionData[groupKey];
                    var groupHeader = $("<h5>").text(groupKey);
                    var groupDiv = $("<div>", {
                        class: "cardGroup"
                    });
                    groupDiv.append(groupHeader);
                    var groupList = $("<ul>");
                    for (let i = 0; i < groupData.length; i++) {
                        var itemValue = groupData[i];
                        groupList.append($("<li>").text(itemValue));
                    }
                    groupDiv.append(groupList);
                    appendToCard(groupDiv, 10);
                }
            }
        }
    });
});
</script>
<?php
echo $this->KMP->endBlock(); ?>