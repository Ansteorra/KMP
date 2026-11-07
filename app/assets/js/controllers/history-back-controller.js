import { Controller } from "@hotwired/stimulus"
import { goBack } from '../services/ui-navigation-service.js';

class HistoryBackController extends Controller {
    go(event) {
        if (goBack()) event.preventDefault();
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["history-back"] = HistoryBackController
