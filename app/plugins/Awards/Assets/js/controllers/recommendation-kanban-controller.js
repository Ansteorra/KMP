import { Controller } from "@hotwired/stimulus"

class RecommendationKanbanController extends Controller {
    static targets = ["stateRulesBlock"];
    static outlets = ["kanban"];
    board = null;
    kanbanOutletConnected(outlet, element) {
        this.board = outlet;
        var controller = this;
        this.board.registerBeforeDrop((recId, toCol) => {
            return controller.checkRules(recId, toCol);
        });
    }
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