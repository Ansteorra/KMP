import { Controller } from "@hotwired/stimulus"

const optionSelector = "[role='option']:not([aria-disabled])"
const activeSelector = "[aria-selected='true']"

class Kanban extends Controller {
    static targets = ["card", "column"]
    static values = { csrfToken: String, url: String }

    initialize() {
        this.draggedItem = null;
    }

    registerBeforeDrop(callback) {
        this.beforeDropCallback = callback;
    }

    cardDrag(event) {
        event.preventDefault();
        this.processDrag(event, false);
    }

    connect() {
        // Add event listeners for drag and drop events 
        document.addEventListener('dragover', this.handleDragOver.bind(this));
        document.addEventListener('drop', this.handleDrop.bind(this));
    }

    disconnect() {
        // Remove event listeners when the controller is disconnected
        document.removeEventListener('dragover', this.handleDragOver.bind(this));
        document.removeEventListener('drop', this.handleDrop.bind(this));
    }
    handleDragOver(event) {
        event.preventDefault();
    }

    handleDrop(event) {
        event.preventDefault();
        if (!this.element.contains(event.target)) {
            console.log('Dropped outside of the table');
            // Handle the drop outside of the table
            this.restoreOriginalPosition();
        }
    }

    restoreOriginalPosition() {
        if (this.draggedItem && this.originalParent) {
            // Insert the dragged item back to its original position
            if (this.originalIndex >= this.originalParent.children.length) {
                this.originalParent.appendChild(this.draggedItem);
            } else {
                this.originalParent.insertBefore(this.draggedItem, this.originalParent.children[this.originalIndex]);
            }
            this.draggedItem.classList.remove("opacity-25");
            this.draggedItem = null;
        }
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
        //record where the object is in the dom before it is moved
        this.originalParent = this.draggedItem.parentElement;
        this.originalIndex = Array.prototype.indexOf.call(this.originalParent.children, this.draggedItem);
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
                var toCol = targetCol.dataset['col'];
                if (this.beforeDropCallback && !this.beforeDropCallback(entityId, toCol)) {
                    this.restoreOriginalPosition()
                    return;
                }
                fetch(this.urlValue + "/" + entityId, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-Token": this.csrfTokenValue
                    },
                    body: JSON.stringify({
                        newCol: targetCol.dataset['col'],
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