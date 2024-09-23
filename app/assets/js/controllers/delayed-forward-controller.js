const { Controller } = require("@hotwired/stimulus");

class DelayForwardController extends Controller {
    static values = { url: String, delayMs: Number };

    connect() {
        console.log("DelayForwardController connected");
        this.timeout = null;
        this.forward();
    }

    forward() {
        if (this.timeout) {
            clearTimeout(this.timeout);
        }
        this.timeout = setTimeout(() => {
            console.log("Forwarding to " + this.urlValue);
            window.location.href = this.urlValue;
        }, this.delayMsValue);
    }

    disconnect() {
        if (this.timeout) {
            clearTimeout(this.timeout);
        }
    }

}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["delay-forward"] = DelayForwardController;