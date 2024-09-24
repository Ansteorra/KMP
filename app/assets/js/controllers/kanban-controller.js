import { Controller } from "@hotwired/stimulus"

const optionSelector = "[role='option']:not([aria-disabled])"
const activeSelector = "[aria-selected='true']"

class Kanban extends Controller {
    static targets = ["card", "column"]
    static values = { csrfToken: String, url: String }

    initialize() {
        this.draggedItem = null;
    }

    cardDrag(event) {
        console.log("cardDrag");
        event.preventDefault();
        this.processDrag(event, false);
    }

    dropCard(event) {
        event.preventDefault();
        this.processDrag(event, true);
        this.draggedItem.classList.remove("opacity-25");
        this.draggedItem = null;
    }

    grabCard(event) {
        var target = event.target;
        target.classList.add("opacity-25");
        this.draggedItem = target;
    }

    processDrag(event, isDrop) {
        //console.log(event);
        var targetCol = event.target;
        var entityId = this.draggedItem.dataset['recId'];
        var targetStackRank = null;
        while (!targetCol.classList.contains('sortable')) {
            if (targetCol.tagName == 'BODY') {
                return;
            }
            targetCol = targetCol.parentElement;
        }
        var targetBefore = event.target;
        var foundBefore = true;
        while (!targetBefore.classList.contains('card')) {
            if (targetBefore.tagName == 'TD') {
                foundBefore = false;
                break;
            }
            targetBefore = targetBefore.parentElement;
        }
        if (foundBefore) {
            targetStackRank = targetBefore.dataset['stackRank'];
        }
        if (targetCol.classList.contains('sortable')) {
            const data = event.dataTransfer.getData('Text');
            if (foundBefore) {
                targetCol.insertBefore(this.draggedItem, targetBefore);
            } else {
                targetCol.appendChild(this.draggedItem);
            }
            if (isDrop) {
                //in the targetCol get the card before the draggedItem
                var palaceAfter = -1;
                var palaceBefore = -1;
                var previousSibling = this.draggedItem.previousElementSibling;
                if (previousSibling) {
                    palaceAfter = previousSibling.dataset['recId'];
                } else {
                    palaceAfter = -1;
                }
                var nextSibling = this.draggedItem.nextElementSibling;
                if (nextSibling) {
                    palaceBefore = nextSibling.dataset['recId'];
                } else {
                    palaceBefore = -1;
                }

                fetch(this.urlValue + "/" + entityId, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-Token": this.csrfTokenValue
                    },
                    body: JSON.stringify({
                        status: targetCol.dataset['status'],
                        placeAfter: palaceAfter,
                        placeBefore: palaceBefore
                    })
                });
            }
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["kanban"] = Kanban;