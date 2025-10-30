import { Controller } from "@hotwired/stimulus";

/**
 * MemberMobileCardProfile Stimulus Controller
 * 
 * Manages mobile-optimized member profile card display with PWA integration and
 * responsive design. Provides Bootstrap table layout optimized for mobile viewing
 * with automatic content organization and plugin data display.
 * 
 * Features:
 * - Mobile-optimized profile card layout
 * - PWA readiness integration and state management
 * - Bootstrap table-based responsive design
 * - Automatic plugin content organization
 * - Member status tracking with expiration handling
 * - Dynamic card generation for multiple sections
 * - Column-based data formatting for mobile display
 * - Real-time loading states and user feedback
 * 
 * Values:
 * - url: String - API endpoint for member profile data
 * - pwaReady: Boolean - PWA availability state for conditional loading
 * 
 * Targets:
 * - cardSet: Container for generated profile cards
 * - name: Member's full name display
 * - scaName: Member's SCA name display
 * - branchName: Member's branch name display
 * - membershipInfo: Membership number and expiration info
 * - backgroundCheck: Background check status and expiration
 * - lastUpdate: Last update timestamp display
 * - loading: Loading state indicator
 * - memberDetails: Container for member information
 * 
 * Usage:
 * <div data-controller="member-mobile-card-profile" 
 *      data-member-mobile-card-profile-url-value="/api/member/123/mobile"
 *      data-member-mobile-card-profile-pwa-ready-value="false">
 *   <div data-member-mobile-card-profile-target="loading">Loading...</div>
 *   <div data-member-mobile-card-profile-target="memberDetails" hidden>
 *     <h2 data-member-mobile-card-profile-target="name"></h2>
 *     <p data-member-mobile-card-profile-target="scaName"></p>
 *     <div data-member-mobile-card-profile-target="cardSet"></div>
 *   </div>
 * </div>
 */
class MemberMobileCardProfile extends Controller {
    static targets = ["cardSet", "name", "scaName", "branchName", "membershipInfo", "backgroundCheck", "lastUpdate", "loading", "memberDetails"];
    static values = {
        url: String,
        pwaReady: Boolean
    }

    /**
     * Initialize controller state
     * Sets up card generation tracking variables
     */
    initialize() {
        this.currentCard = null;
        this.cardCount = 0;
        
        // Listen for PWA ready event
        this.handlePwaReady = this.handlePwaReady.bind(this);
    }

    /**
     * Disconnect controller from DOM
     * Cleans up event listeners
     */
    disconnect() {
        this.element.removeEventListener('pwa-ready', this.handlePwaReady);
    }

    /**
     * Handle PWA ready event from PWA controller
     */
    handlePwaReady() {
        this.pwaReadyValue = true;
    }

    /**
     * Create and initialize new mobile card with Bootstrap styling
     * Generates responsive card structure optimized for mobile viewing
     * 
     * @param {String} title - Title for the card section
     */
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

    /**
     * Handle PWA ready state changes
     * Triggers card loading when PWA becomes available
     */
    pwaReadyValueChanged() {
        if (this.pwaReadyValue) {
            this.loadCard();
        }
    }

    /**
     * Configure fetch options for AJAX requests
     * Sets up headers for mobile-optimized JSON API communication
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
     * Load member profile data and generate mobile-optimized display
     * Creates responsive Bootstrap table layout with automatic content organization
     */
    loadCard() {
        this.cardSetTarget.innerHTML = "";
        this.loadingTarget.hidden = false;
        this.memberDetailsTarget.hidden = true;
        if (!this.pwaReadyValue) {
            return;
        }
        fetch(this.urlValue, this.optionsForFetch())
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
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
            })
            .catch(error => {
                console.error("Error loading card:", error);
                this.loadingTarget.hidden = true;
                this.memberDetailsTarget.hidden = false;
                this.nameTarget.textContent = "Error loading card data";
            });
    }

    /**
     * Connect controller to DOM
     * Initializes mobile profile card interface and sets up PWA event listener
     */
    connect() {
        this.element.addEventListener('pwa-ready', this.handlePwaReady);
        
        // Check if PWA is already ready (event may have fired before we connected)
        if (this.pwaReadyValue) {
            this.loadCard();
        }
    }

}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["member-mobile-card-profile"] = MemberMobileCardProfile;