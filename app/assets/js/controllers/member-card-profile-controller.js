import { Controller } from "@hotwired/stimulus"

class MemberCardProfile extends Controller {
    static targets = ["cardSet",
        "firstCard",
        "name",
        "scaName",
        "branchName",
        "membershipInfo",
        "backgroundCheck",
        "lastUpdate",
        "loading",
        "memberDetails"];
    static values = {
        url: String,
    }
    initialize() {
        this.currentCard = null;
        this.cardCount = 1;
        this.maxCardLength = 0;
    }

    usedSpaceInCard() {
        var cardChildren = this.currentCard.children;
        var runningTotal = 0;
        for (var i = 0; i < cardChildren.length; i++) {
            runningTotal += cardChildren[i].offsetHeight;
        }
        return runningTotal;
    }

    appendToCard(element, minSpace) {
        this.currentCard.appendChild(element);
        if (minSpace === null) {
            minSpace = 2;
        }
        minSpace = this.maxCardLength * (minSpace / 100);
        if (this.usedSpaceInCard() > (this.maxCardLength - minSpace)) {
            this.currentCard.removeChild(element);
            this.startCard();
            this.currentCard.appendChild(element);
        }
    }

    startCard() {
        this.cardCount++;
        var card = document.createElement("div");
        card.classList.add("auth_card");
        card.id = "card_" + this.cardCount;
        var cardDetails = document.createElement("div");
        cardDetails.classList.add("cardbox");
        cardDetails.id = "cardDetails_" + this.cardCount;
        card.appendChild(cardDetails);
        this.cardSetTarget.appendChild(card);
        this.currentCard = cardDetails;
    }

    optionsForFetch() {
        return {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        }
    }

    loadCard() {
        this.currentCard = this.firstCardTarget;
        this.maxCardLength = this.firstCardTarget.offsetHeight;
        this.cardCount = 1;
        fetch(this.urlValue, this.optionsForFetch())
            .then(response => response.json())
            .then(data => {
                this.nameTarget.textContent = data.member.first_name + ' ' + data.member.last_name;
                this.scaNameTarget.textContent = data.member.sca_name;
                this.branchNameTarget.textContent = data.member.branch.name;
                if (data.member.membership_number && data.member.membership_number.length > 0) {
                    var memberExpDate = new Date(data.member.membership_expires_on);
                    if (memberExpDate < new Date()) {
                        memberExpDate = "Expired";
                    } else {
                        memberExpDate = " - " + memberExpDate.toLocaleDateString();
                    }
                    this.membershipInfoTarget.textContent = data.member.membership_number + ' ' + memberExpDate;
                } else {
                    this.membershipInfoTarget.innerHtml = "";
                    this.membershipInfoTarget.textContent = "No Membership Info";
                }
                if (data.member.background_check_expires_on) {
                    var backgroundCheckExpDate = new Date(data.member.background_check_expires_on);
                    if (backgroundCheckExpDate < new Date()) {
                        backgroundCheckExpDate = "Expired";
                    } else {
                        backgroundCheckExpDate = " - " + backgroundCheckExpDate.toLocaleDateString();
                    }

                    var strong = document.createElement("strong");
                    strong.textContent = backgroundCheckExpDate;
                    this.backgroundCheckTarget.innerHtml = "";
                    this.backgroundCheckTarget.appendChild(strong);
                } else {
                    this.backgroundCheckTarget.innerHtml = "";
                    this.backgroundCheckTarget.textContent = "No Background Check";
                }
                var today = new Date();
                this.lastUpdateTarget.textContent = today.toLocaleDateString();
                this.loadingTarget.hidden = true;
                this.memberDetailsTarget.hidden = false;
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
                        var sectionHeader = document.createElement("h3");
                        sectionHeader.textContent = sectionKey;
                        this.appendToCard(sectionHeader, 20);
                        for (let groupKey in sectionData) {
                            var groupData = sectionData[groupKey];
                            var groupHeader = document.createElement("h5");
                            groupHeader.textContent = groupKey;
                            var groupDiv = document.createElement("div");
                            groupDiv.classList.add("cardGroup");
                            groupDiv.appendChild(groupHeader);
                            var groupList = document.createElement("ul");
                            for (let i = 0; i < groupData.length; i++) {
                                var itemValue = groupData[i];
                                var listItem = document.createElement("li");
                                listItem.textContent = itemValue;
                                groupList.appendChild(listItem);
                            }
                            groupDiv.appendChild(groupList);
                            this.appendToCard(groupDiv, 10);
                        }
                    }
                }
            });
    }
    connect() {
        this.loadCard();
    }

}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["member-card-profile"] = MemberCardProfile;
