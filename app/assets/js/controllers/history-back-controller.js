import { Controller } from "@hotwired/stimulus"

class HistoryBackController extends Controller {
    go(event) {
        event.preventDefault()
        history.back()
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["history-back"] = HistoryBackController
