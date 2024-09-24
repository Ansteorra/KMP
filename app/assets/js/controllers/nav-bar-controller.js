const { Controller } = require("@hotwired/stimulus");

class NavBarController extends Controller {
    static targets = ["navHeader"]

    navHeaderClicked(event) {
        var state = event.target.getAttribute('aria-expanded');

        if (state === 'true') {
            var recordExpandUrl = event.target.getAttribute('data-expand-url');
            fetch(recordExpandUrl, this.optionsForFetch());
        } else {
            var recordCollapseUrl = event.target.getAttribute('data-collapse-url');
            fetch(recordCollapseUrl, this.optionsForFetch());
        }
    }

    navHeaderTargetConnected(event) {
        event.addEventListener('click', this.navHeaderClicked.bind(this));
    }
    navHeaderTargetDisconnected(event) {
        event.removeEventListener('click', this.navHeaderClicked.bind(this));
    }

    optionsForFetch() {
        return {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        }
    }
}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["nav-bar"] = NavBarController;