import { Controller } from "@hotwired/stimulus"

const optionSelector = "[role='option']:not([aria-disabled])"
const activeSelector = "[aria-selected='true']"

/**
 * Kanban Stimulus Controller
 * 
 * Implements drag-and-drop functionality for Kanban board interfaces with
 * server synchronization and position tracking. Provides comprehensive
 * card movement, visual feedback, and AJAX-based persistence.
 * 
 * Features:
 * - Drag-and-drop card movement between columns
 * - Visual feedback during drag operations
 * - Position restoration on invalid drops
 * - Server synchronization via AJAX
 * - CSRF protection for security
 * - Validation callbacks before drop
 * - Automatic position calculation
 * 
 * Values:
 * - csrfToken: String - CSRF token for secure requests
 * - url: String - API endpoint for position updates
 * 
 * Targets:
 * - card: Individual card elements that can be dragged
 * - column: Column containers for card organization
 * 
 * Usage:
 * <div data-controller="kanban" 
 *      data-kanban-csrf-token-value="<%= @csrf_token %>"
 *      data-kanban-url-value="/api/cards/move">
 *   <div data-kanban-target="column" class="sortable" data-col="todo">
 *     <div data-kanban-target="card" data-rec-id="1" data-stack-rank="100"
 *          draggable="true" data-action="dragstart->kanban#grabCard">
 *       Card Content
 *     </div>
 *   </div>
 * </div>
 */
class Kanban extends Controller {
    static targets = ["card", "column"]
    static values = { csrfToken: String, url: String }

    /**
     * Initialize controller state
     * Sets up drag tracking variables
     */
    initialize() {
        this.draggedItem = null;
    }

    /**
     * Register callback function to validate drops before processing
     * Allows custom validation logic for card movements
     * 
     * @param {Function} callback - Validation function returning boolean
     */
    registerBeforeDrop(callback) {
        this.beforeDropCallback = callback;
    }

    /**
     * Handle card drag operation
     * Processes drag movement without dropping
     * 
     * @param {DragEvent} event - Drag event from card element
     */
    cardDrag(event) {
        event.preventDefault();
        this.processDrag(event, false);
    }

    /**
     * Connect controller to DOM
     * Sets up global drag and drop event listeners
     */
    connect() {
        // Add event listeners for drag and drop events 
        document.addEventListener('dragover', this.handleDragOver.bind(this));
        document.addEventListener('drop', this.handleDrop.bind(this));
    }

    /**
     * Disconnect controller from DOM
     * Cleans up global event listeners
     */
    disconnect() {
        // Remove event listeners when the controller is disconnected
        document.removeEventListener('dragover', this.handleDragOver.bind(this));
        document.removeEventListener('drop', this.handleDrop.bind(this));
    }

    /**
     * Handle global drag over events
     * Prevents default behavior to enable drop
     * 
     * @param {DragEvent} event - Global dragover event
     */
    handleDragOver(event) {
        event.preventDefault();
    }

    /**
     * Handle global drop events
     * Restores original position if dropped outside valid area
     * 
     * @param {DragEvent} event - Global drop event
     */
    handleDrop(event) {
        event.preventDefault();
        if (!this.element.contains(event.target)) {
            console.log('Dropped outside of the table');
            // Handle the drop outside of the table
            this.restoreOriginalPosition();
        }
    }

    /**
     * Restore card to its original position
     * Used when drop is invalid or cancelled
     */
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


    /**
     * Handle card drop operation
     * Processes final drop with server synchronization
     * 
     * @param {DragEvent} event - Drop event from target element
     */
    dropCard(event) {
        event.preventDefault();
        this.processDrag(event, true);
        this.draggedItem.classList.remove("opacity-25");
        this.draggedItem = null;
    }

    /**
     * Handle card grab (drag start)
     * Sets up drag operation and records original position
     * 
     * @param {DragEvent} event - Dragstart event from card element
     */
    grabCard(event) {
        var target = event.target;
        target.classList.add("opacity-25");
        this.draggedItem = target;
        //record where the object is in the dom before it is moved
        this.originalParent = this.draggedItem.parentElement;
        this.originalIndex = Array.prototype.indexOf.call(this.originalParent.children, this.draggedItem);
    }

    /**
     * Process drag operation with position calculation and server sync
     * Handles both preview and final drop operations
     * 
     * @param {DragEvent} event - Drag or drop event
     * @param {Boolean} isDrop - Whether this is final drop operation
     */
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