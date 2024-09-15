import { Controller } from "@hotwired/stimulus"

class MemberMobileCardProfile extends Controller {
    static targets = ["cardSet", "name", "scaName", "branchName", "membershipInfo", "backgroundCheck", "lastUpdate", "loading", "memberDetails"];
    static values = {
        url: String,
    }
    initialize() {
        this.currentCard = null;
        this.cardCount = 0;
    }

    startCard(title) {
        this.cardCount++;
        var card = document.createElement("div");
        card.classList.add("card", "cardbox", "m-3");
        card.id = "card_" + this.cardCount;

        var cardDetails = document.createElement("div");
        cardDetails.classList.add("card-body");
        cardDetails.id = "cardDetails_" + this.cardCount;

        var cardTitle = document.createElement("h3");
        cardTitle.classList.add("card-title", "text-center", "display-6");
        cardTitle.textContent = title;
        cardDetails.appendChild(cardTitle);

        card.appendChild(cardDetails);
        this.cardSetTarget.appendChild(card);

        this.currentCard = cardDetails;
    }

    loadCard() {
        this.cardSetTarget.innerHTML = "";
        this.loadingTarget.hidden = false;
        this.memberDetailsTarget.hidden = true;
        fetch(this.urlValue)
            .then(response => response.json())
            .then(data => {
                this.loadingTarget.hidden = true;
                this.memberDetailsTarget.hidden = false;
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
                    this.membershipInfoTarget.textContent = "No Membership Info";
                }
                if (data.member.background_check_expires_on) {
                    var backgroundCheckExpDate = new Date(data.member.background_check_expires_on);
                    if (backgroundCheckExpDate < new Date()) {
                        backgroundCheckExpDate = "Expired";
                    } else {
                        backgroundCheckExpDate = 'Current' + backgroundCheckExpDate.toLocaleDateString();
                    }
                    this.backgroundCheckTarget.textContent = backgroundCheckExpDate;
                } else {
                    this.backgroundCheckTarget.textContent = "Not on file";
                }
                this.lastUpdateTarget.textContent = new Date().toLocaleString();
                for (let key in data) {
                    if (key === 'member') {
                        continue;
                    }
                    var pluginData = data[key];
                    for (let sectionKey in pluginData) {
                        var sectionData = pluginData[sectionKey];
                        var keysCount = Object.keys(sectionData).length;
                        if (keysCount > 0) {
                            this.startCard(sectionKey);
                        } else {
                            continue;
                        }
                        var groupTable = document.createElement("table");
                        groupTable.classList.add("table", "card-body-table");
                        var groupTableBody = document.createElement("tbody");
                        groupTable.appendChild(groupTableBody);

                        for (let groupKey in sectionData) {
                            var groupData = sectionData[groupKey];
                            if (groupData.length === 0) {
                                continue;
                            }
                            var groupRow = document.createElement("tr");
                            var groupHeader = document.createElement("th");
                            groupHeader.classList.add("col-12", "text-center");
                            groupHeader.colSpan = "2";
                            groupHeader.textContent = groupKey;
                            groupRow.appendChild(groupHeader);
                            groupTableBody.appendChild(groupRow);
                            var colCount = 0;
                            var groupRow = document.createElement("tr");
                            var textAlignClass = "text-center";
                            for (let i = 0; i < groupData.length; i++) {
                                var itemData = groupData[i];
                                if (colCount == 2) {
                                    groupTableBody.appendChild(groupRow);
                                    groupRow = document.createElement("tr");
                                    textAlignClass = "text-center";
                                    colCount = 0;
                                } else {
                                    textAlignClass = "text-center";
                                }
                                //if there is a : split it into 2 columns of data
                                if (itemData.indexOf(":") > 2) {
                                    var itemValue = itemData.split(":");
                                    var itemValueRow = document.createElement("tr");
                                    var itemValueCol1 = document.createElement("td");
                                    itemValueCol1.classList.add("col-6", "text-end");
                                    itemValueCol1.textContent = itemValue[0];
                                    var itemValueCol2 = document.createElement("td");
                                    itemValueCol2.classList.add("col-6", "text-start");
                                    itemValueCol2.textContent = itemValue[1];
                                    itemValueRow.appendChild(itemValueCol1);
                                    itemValueRow.appendChild(itemValueCol2);
                                    groupTableBody.appendChild(itemValueRow);
                                } else {
                                    var colspan = 1;
                                    if (i + 1 == groupData.length && colCount == 0) {
                                        var colspan = 2;
                                    }
                                    var itemValueCol = document.createElement("td");
                                    itemValueCol.classList.add("col-6", textAlignClass);
                                    itemValueCol.colSpan = colspan;
                                    itemValueCol.textContent = itemData;
                                    groupRow.appendChild(itemValueCol);
                                    colCount++;
                                }
                            }
                            groupTableBody.appendChild(groupRow);
                        }
                        this.currentCard.appendChild(groupTable);
                    }
                }
            });
    }
    connect() {
        console.log("MemberMobileCardProfile connected");
        //this.loadCard();
    }

}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["member-mobile-card-profile"] = MemberMobileCardProfile;