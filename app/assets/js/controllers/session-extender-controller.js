const { Controller } = require("@hotwired/stimulus");

class SessionExtender extends Controller {
    static values = { url: String }

    urlValueChanged() {
        if (this.timer) {
            clearTimeout(this.timer)
        }
        var me = this;
        this.timer = setTimeout(function () {
            alert('Session Expiring! Click ok to extend session.');
            fetch(me.urlValue)
                .then(res => {
                    return res.json()
                })
                .then(data => {
                    me.urlValueChanged();
                })
            //minutes * 60000 miliseconds per minute
        }, 25 * 60000)
    }
}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["session-extender"] = SessionExtender;
