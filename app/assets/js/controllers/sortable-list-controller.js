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

    connect() {
        console.log("Sortable List Controller connected");
        this.draggedElement = null;
        this.draggedOverElement = null;

        // Make items draggable
        this.itemTargets.forEach(item => {
            item.setAttribute('draggable', 'true');
        });
    }

    /**
     * Handle drag start - store reference to dragged element
     */
    dragStart(event) {
        this.draggedElement = event.currentTarget;
        this.draggedElement.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/html', this.draggedElement.innerHTML);
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
        console.log("Items reordered:", order);
    }

    /**
     * Get current order of items
     */
    getOrder() {
        return this.itemTargets.map(item => {
            return item.dataset.itemId || item.dataset.columnKey || item.id;
        });
    }
}

// Register controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["sortable-list"] = SortableListController;
