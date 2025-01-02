import Guifier from 'guifier'

const { Controller } = require("@hotwired/stimulus");

class GuifierController extends Controller {
    static targets = ["hidden", "container"]
    static values = { type: String }

    connect() {
        var params = {
            elementSelector: '#' + this.containerTarget.id,
            data: this.hiddenTarget.value,
            dataType: this.typeValue,
            rootContainerName: 'setting',
            fullScreen: true,
            onChange: () => {
                this.hiddenTarget.value = this.guifier.getData(this.typeValue)
                // console.log(this.hiddenTarget.value);
                this.hiddenTarget.dispatchEvent(new Event('change'))
            }
        }
        this.guifier = new Guifier(params);
        //find all the elements with guifierContainerCollapseButton class and click them
        var collapseButtons = this.containerTarget.querySelectorAll('.guifierContainerCollapseButton');
        collapseButtons.forEach(function (button) {
            button.click();
        });
    }

}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["guifier-control"] = GuifierController;