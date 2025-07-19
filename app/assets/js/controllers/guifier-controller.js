import Guifier from 'guifier'

const { Controller } = require("@hotwired/stimulus");

/**
 * Guifier Stimulus Controller
 * 
 * Integrates Guifier dynamic form generation library with Stimulus for 
 * JSON schema-based configuration interfaces. Provides real-time form 
 * generation and data binding with automatic change propagation.
 * 
 * Features:
 * - JSON schema-based form generation
 * - Real-time data binding and updates
 * - Fullscreen editing interface
 * - Automatic form collapse management
 * - Change event propagation
 * - Configurable data types (JSON, YAML, etc.)
 * 
 * Values:
 * - type: String - Data type for Guifier (json, yaml, etc.)
 * 
 * Targets:
 * - hidden: Hidden input field containing form data
 * - container: Container element for Guifier interface
 * 
 * Usage:
 * <div data-controller="guifier-control" data-guifier-control-type-value="json">
 *   <input data-guifier-control-target="hidden" type="hidden" name="settings">
 *   <div data-guifier-control-target="container" id="guifier-container"></div>
 * </div>
 */
class GuifierController extends Controller {
    static targets = ["hidden", "container"]
    static values = { type: String }

    /**
     * Connect controller and initialize Guifier
     * Sets up dynamic form interface with data binding and change handling
     */
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