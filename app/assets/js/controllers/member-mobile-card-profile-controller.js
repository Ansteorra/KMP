import MobileControllerBase from "./mobile-controller-base.js";

/**
 * MemberMobileCardProfile Stimulus Controller
 * 
 * Manages mobile-optimized member profile card display with PWA integration.
 * Extends MobileControllerBase for centralized connection handling.
 * 
 * Features:
 * - Mobile-optimized profile card layout
 * - PWA readiness integration
 * - Fetch with retry for reliability
 * - Dynamic card generation for plugin sections
 */
class MemberMobileCardProfile extends MobileControllerBase {
    static targets = ["cardSet", "name", "scaName", "branchName", "membershipInfo", "backgroundCheck", "lastUpdate", "loading", "memberDetails"];
    static values = {
        url: String,
        pwaReady: Boolean
    }

    initialize() {
        super.initialize();
        this.currentCard = null;
        this.cardCount = 0;
        this.handlePwaReady = this.handlePwaReady.bind(this);
    }

    /**
     * Called after base class connect
     */
    onConnect() {
        this.element.addEventListener('pwa-ready', this.handlePwaReady);
        
        // Check if PWA is already ready
        if (this.pwaReadyValue) {
            this.loadCard();
        }
    }

    /**
     * Called after base class disconnect
     */
    onDisconnect() {
        this.element.removeEventListener('pwa-ready', this.handlePwaReady);
    }

    /**
     * Handle PWA ready event
     */
    handlePwaReady() {
        this.pwaReadyValue = true;
    }

    /**
     * Create a new mobile card section
     */
    startCard(title) {
        this.cardCount++;
        const card = document.createElement("div");
        card.classList.add("card", "cardbox", "m-3");
        card.id = "card_" + this.cardCount;

        const cardDetails = document.createElement("div");
        cardDetails.classList.add("card-body");
        cardDetails.id = "cardDetails_" + this.cardCount;

        const cardTitle = document.createElement("h3");
        cardTitle.classList.add("card-title", "text-center", "display-6");
        cardTitle.textContent = title;
        cardDetails.appendChild(cardTitle);

        card.appendChild(cardDetails);
        this.cardSetTarget.appendChild(card);

        this.currentCard = cardDetails;
    }

    /**
     * Handle PWA ready state changes
     */
    pwaReadyValueChanged() {
        if (this.pwaReadyValue) {
            this.loadCard();
        }
    }

    /**
     * Load member profile data with retry logic
     */
    async loadCard() {
        if (!this.pwaReadyValue) return;
        
        this.cardSetTarget.innerHTML = "";
        this.loadingTarget.hidden = false;
        this.memberDetailsTarget.hidden = true;

        try {
            // Use base class fetchWithRetry for reliability
            const response = await this.fetchWithRetry(this.urlValue);
            const data = await response.json();
            
            this.loadingTarget.hidden = true;
            this.memberDetailsTarget.hidden = false;
            
            this.renderMemberData(data);
        } catch (error) {
            console.error("Error loading card:", error);
            this.loadingTarget.hidden = true;
            this.memberDetailsTarget.hidden = false;
            this.nameTarget.textContent = "Error loading card data";
            
            // Show retry button if offline
            if (!this.online) {
                this.showOfflineMessage();
            }
        }
    }

    /**
     * Render member data to the card
     */
    renderMemberData(data) {
        this.nameTarget.textContent = data.member.first_name + ' ' + data.member.last_name;
        this.scaNameTarget.textContent = data.member.sca_name;
        this.branchNameTarget.textContent = data.member.branch.name;
        
        // Membership info
        if (data.member.membership_number && data.member.membership_number.length > 0) {
            const memberExpDate = new Date(data.member.membership_expires_on);
            const expText = memberExpDate < new Date() ? "Expired" : " - " + memberExpDate.toLocaleDateString();
            this.membershipInfoTarget.textContent = data.member.membership_number + ' ' + expText;
        } else {
            this.membershipInfoTarget.textContent = "No Membership Info";
        }
        
        // Background check
        if (data.member.background_check_expires_on) {
            const bgCheckDate = new Date(data.member.background_check_expires_on);
            const bgText = bgCheckDate < new Date() ? "Expired" : 'Current ' + bgCheckDate.toLocaleDateString();
            this.backgroundCheckTarget.textContent = bgText;
        } else {
            this.backgroundCheckTarget.textContent = "Not on file";
        }
        
        this.lastUpdateTarget.textContent = new Date().toLocaleString();
        
        // Render plugin sections
        this.renderPluginSections(data);
    }

    /**
     * Render plugin-provided sections
     */
    renderPluginSections(data) {
        for (let key in data) {
            if (key === 'member') continue;
            
            const pluginData = data[key];
            for (let sectionKey in pluginData) {
                const sectionData = pluginData[sectionKey];
                if (Object.keys(sectionData).length === 0) continue;
                
                this.startCard(sectionKey);
                
                const groupTable = document.createElement("table");
                groupTable.classList.add("table", "card-body-table");
                const groupTableBody = document.createElement("tbody");
                groupTable.appendChild(groupTableBody);

                for (let groupKey in sectionData) {
                    const groupData = sectionData[groupKey];
                    if (groupData.length === 0) continue;
                    
                    // Group header
                    const headerRow = document.createElement("tr");
                    const groupHeader = document.createElement("th");
                    groupHeader.classList.add("col-12", "text-center");
                    groupHeader.colSpan = "2";
                    groupHeader.textContent = groupKey;
                    headerRow.appendChild(groupHeader);
                    groupTableBody.appendChild(headerRow);
                    
                    // Group items
                    let colCount = 0;
                    let groupRow = document.createElement("tr");
                    
                    for (let i = 0; i < groupData.length; i++) {
                        const itemData = groupData[i];
                        
                        if (colCount === 2) {
                            groupTableBody.appendChild(groupRow);
                            groupRow = document.createElement("tr");
                            colCount = 0;
                        }
                        
                        // Handle key:value format
                        if (itemData.indexOf(":") > 2) {
                            const itemValue = itemData.split(":");
                            const itemValueRow = document.createElement("tr");
                            const itemValueCol1 = document.createElement("td");
                            itemValueCol1.classList.add("col-6", "text-end");
                            itemValueCol1.textContent = itemValue[0];
                            const itemValueCol2 = document.createElement("td");
                            itemValueCol2.classList.add("col-6", "text-start");
                            itemValueCol2.textContent = itemValue[1];
                            itemValueRow.appendChild(itemValueCol1);
                            itemValueRow.appendChild(itemValueCol2);
                            groupTableBody.appendChild(itemValueRow);
                        } else {
                            const colspan = (i + 1 === groupData.length && colCount === 0) ? 2 : 1;
                            const itemValueCol = document.createElement("td");
                            itemValueCol.classList.add("col-6", "text-center");
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
    }

    /**
     * Show offline message with retry option
     */
    showOfflineMessage() {
        const offlineMsg = document.createElement("div");
        offlineMsg.className = "alert alert-warning text-center m-3";
        offlineMsg.innerHTML = `
            <i class="bi bi-wifi-off me-2"></i>
            Unable to load - you're offline
            <button class="btn btn-sm btn-outline-warning ms-2" data-action="click->member-mobile-card-profile#retryLoad">
                Retry
            </button>
        `;
        this.cardSetTarget.appendChild(offlineMsg);
    }

    /**
     * Retry loading card data
     */
    retryLoad() {
        this.loadCard();
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["member-mobile-card-profile"] = MemberMobileCardProfile;