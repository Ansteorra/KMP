import { Controller } from "@hotwired/stimulus"

/**
 * MemberCardProfile Stimulus Controller
 * 
 * Manages multi-card member profile displays with dynamic layout and content organization.
 * Automatically loads member data via AJAX and creates additional cards when content
 * overflow occurs, ensuring optimal readability and presentation.
 * 
 * Features:
 * - Dynamic card creation with overflow management
 * - AJAX-based member data loading
 * - Plugin content organization and display
 * - Membership status tracking and expiration handling
 * - Background check status display
 * - Responsive card layout with space calculation
 * - Multi-section content organization
 * 
 * Values:
 * - url: String - API endpoint for member data
 * 
 * Targets:
 * - cardSet: Container for all profile cards
 * - firstCard: Initial card element
 * - name: Member name display element
 * - scaName: SCA name display element
 * - branchName: Branch name display element
 * - membershipInfo: Membership information display
 * - backgroundCheck: Background check status display
 * - lastUpdate: Last update timestamp display
 * - loading: Loading indicator element
 * - memberDetails: Member details container
 * 
 * Usage:
 * <div data-controller="member-card-profile" data-member-card-profile-url-value="/api/member/123">
 *   <div data-member-card-profile-target="cardSet">
 *     <div data-member-card-profile-target="firstCard" class="auth_card">
 *       <div data-member-card-profile-target="loading">Loading...</div>
 *       <div data-member-card-profile-target="memberDetails" hidden>
 *         <div data-member-card-profile-target="name"></div>
 *         <div data-member-card-profile-target="scaName"></div>
 *         <!-- Additional profile elements -->
 *       </div>
 *     </div>
 *   </div>
 * </div>
 */
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

    /**
     * Initialize controller state
     * Sets up card management variables
     */
    initialize() {
        this.currentCard = null;
        this.cardCount = 1;
        this.maxCardLength = 0;
    }

    /**
     * Calculate used space in current card
     * Measures total height of all child elements
     * 
     * @returns {Number} Total height of card content in pixels
     */
    usedSpaceInCard() {
        var cardChildren = this.currentCard.children;
        var runningTotal = 0;
        for (var i = 0; i < cardChildren.length; i++) {
            runningTotal += cardChildren[i].offsetHeight;
        }
        return runningTotal;
    }

    /**
     * Append element to card with overflow handling
     * Creates new card if content would exceed available space
     * 
     * @param {HTMLElement} element - Element to append to card
     * @param {Number|null} minSpace - Minimum space percentage to maintain
     */
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

    /**
     * Create and initialize new card
     * Sets up new card structure and updates current card reference
     */
    startCard() {
        this.cardCount++;
        var card = document.createElement("div");
        card.classList.add("auth_card");
        card.id = "card_" + this.cardCount;
        var cardDetails = document.createElement("div");
        cardDetails.classList.add("cardbox");
        cardDetails.id = "cardDetails_" + this.cardCount;
        cardDetails.dataset.section = "auth-card";
        card.appendChild(cardDetails);
        this.cardSetTarget.appendChild(card);
        this.currentCard = cardDetails;
    }

    /**
     * Configure fetch options for AJAX requests
     * Sets up headers for JSON API communication
     * 
     * @returns {Object} Fetch options object
     */
    optionsForFetch() {
        return {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        }
    }

    /**
     * Load member card data from API
     * Fetches member information and organizes plugin content into cards
     */
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

    /**
     * Connect controller to DOM
     * Initiates card loading process
     */
    connect() {
        this.loadCard();
    }

}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["member-card-profile"] = MemberCardProfile;
