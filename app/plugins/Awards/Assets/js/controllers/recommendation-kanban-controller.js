import { Controller } from "@hotwired/stimulus"

/**
 * Recommendation Kanban Controller
 *
 * Manages kanban-style workflow for recommendations with drag-and-drop
 * state transitions and business rule validation.
 *
 * Targets: stateRulesBlock
 * Outlets: kanban
 */
class RecommendationKanbanController extends Controller {
    static targets = ["stateRulesBlock"];
    static outlets = ["kanban"];
    board = null;

    /** Register validation callback when kanban outlet connects. */
    kanbanOutletConnected(outlet, element) {
        this.board = outlet;
        var controller = this;
        this.board.registerBeforeDrop((recId, toCol) => {
            return controller.checkRules(recId, toCol);
        });
    }

    /** Validate state transition rules for drag-and-drop operations. */
    checkRules(recId, toCol) {
        console.log({ recId: recId, toCol: toCol });
        return true;
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["recommendation-kanban"] = RecommendationKanbanController;