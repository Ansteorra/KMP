import { Controller } from "@hotwired/stimulus"

/**
 * Sortable List Controller - Drag and Drop Reordering
 * 
 * Provides drag-and-drop reordering functionality for list items.
 * Emits a custom event when items are reordered so parent controllers
 * can respond to changes.
 * 
 * Usage:
 * <ul data-controller="sortable-list" data-action="sortable-list:reordered->parent#handleReorder">
 *   <li data-sortable-list-target="item" data-item-id="1" draggable="true">
 *     <span data-sortable-list-target="handle">☰</span> Item 1
 *   </li>
 *   <li data-sortable-list-target="item" data-item-id="2" draggable="true">
 *     <span data-sortable-list-target="handle">☰</span> Item 2
 *   </li>
 * </ul>
 * 
 * Events:
 * - sortable-list:reordered - Dispatched when order changes
 *   detail: { order: ['id1', 'id2', 'id3'], items: [...DOMElements] }
 */
class SortableListController extends Controller {
    static targets = ["item", "handle"]

    initialize() {
        this.draggedElement = null;
        this.draggedOverElement = null;
        this.boundHandlers = {
            dragstart: this.dragStart.bind(this),
            dragover: this.dragOver.bind(this),
            dragenter: this.dragEnter.bind(this),
            dragleave: this.dragLeave.bind(this),
            drop: this.drop.bind(this),
            dragend: this.dragEnd.bind(this),
        };
    }

    connect() {
        // Make items draggable
        this.itemTargets.forEach(item => {
            item.setAttribute('draggable', 'true');
            this.addDragListeners(item);
        });
    }

    disconnect() {
        this.itemTargets.forEach(item => {
            this.removeDragListeners(item);
        });
    }

    /**
     * Handle drag start - store reference to dragged element
     */
    dragStart(event) {
        this.draggedElement = event.currentTarget;
        this.draggedElement.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
    }

    /**
     * Handle drag over - allow drop by preventing default
     */
    dragOver(event) {
        if (event.preventDefault) {
            event.preventDefault();
        }
        event.dataTransfer.dropEffect = 'move';

        const targetItem = event.currentTarget;
        if (targetItem !== this.draggedElement) {
            this.draggedOverElement = targetItem;
            targetItem.classList.add('drag-over');
        }

        return false;
    }

    /**
     * Handle drag enter - visual feedback
     */
    dragEnter(event) {
        const targetItem = event.currentTarget;
        if (targetItem !== this.draggedElement) {
            targetItem.classList.add('drag-over');
        }
    }

    /**
     * Handle drag leave - remove visual feedback
     */
    dragLeave(event) {
        event.currentTarget.classList.remove('drag-over');
    }

    /**
     * Handle drop - reorder items
     */
    drop(event) {
        if (event.stopPropagation) {
            event.stopPropagation();
        }

        const targetItem = event.currentTarget;

        if (this.draggedElement !== targetItem) {
            // Determine if we're dropping above or below
            const rect = targetItem.getBoundingClientRect();
            const midpoint = rect.top + (rect.height / 2);
            const dropAbove = event.clientY < midpoint;

            // Reorder in DOM
            if (dropAbove) {
                targetItem.parentNode.insertBefore(this.draggedElement, targetItem);
            } else {
                targetItem.parentNode.insertBefore(this.draggedElement, targetItem.nextSibling);
            }

            // Emit reordered event
            this.emitReorderedEvent();
        }

        return false;
    }

    /**
     * Handle drag end - cleanup
     */
    dragEnd(event) {
        // Remove all drag-related classes
        this.itemTargets.forEach(item => {
            item.classList.remove('dragging', 'drag-over');
        });

        this.draggedElement = null;
        this.draggedOverElement = null;
    }

    /**
     * Emit custom event with new order
     */
    emitReorderedEvent() {
        const order = this.itemTargets.map(item => {
            return item.dataset.itemId || item.dataset.columnKey || item.id;
        });

        const event = new CustomEvent('sortable-list:reordered', {
            detail: {
                order: order,
                items: this.itemTargets
            },
            bubbles: true,
            cancelable: true
        });

        this.element.dispatchEvent(event);
    }

    /**
     * Get current order of items
     */
    getOrder() {
        return this.itemTargets.map(item => {
            return item.dataset.itemId || item.dataset.columnKey || item.id;
        });
    }

    addDragListeners(item) {
        item.addEventListener('dragstart', this.boundHandlers.dragstart);
        item.addEventListener('dragover', this.boundHandlers.dragover);
        item.addEventListener('dragenter', this.boundHandlers.dragenter);
        item.addEventListener('dragleave', this.boundHandlers.dragleave);
        item.addEventListener('drop', this.boundHandlers.drop);
        item.addEventListener('dragend', this.boundHandlers.dragend);
    }

    removeDragListeners(item) {
        item.removeEventListener('dragstart', this.boundHandlers.dragstart);
        item.removeEventListener('dragover', this.boundHandlers.dragover);
        item.removeEventListener('dragenter', this.boundHandlers.dragenter);
        item.removeEventListener('dragleave', this.boundHandlers.dragleave);
        item.removeEventListener('drop', this.boundHandlers.drop);
        item.removeEventListener('dragend', this.boundHandlers.dragend);
    }
}

// Register controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["sortable-list"] = SortableListController;
