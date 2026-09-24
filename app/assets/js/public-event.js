import { Application } from "@hotwired/stimulus"
import * as bootstrap from "bootstrap"
import * as Turbo from "@hotwired/turbo"
import KMP_accessibility from "./KMP_accessibility.js"
import "./controllers/confirmation-controller.js"
import "./controllers/public-gathering-controller.js"

// The shared core chunk loads Turbo; public forms must retain native navigation.
Turbo.session.drive = false

// Public pages need accessible confirmations without the signed-in app runtime.
window.bootstrap = bootstrap
window.KMP_accessibility = KMP_accessibility
const application = Application.start()
window.Stimulus = application
for (const identifier of ["confirmation", "public-gathering"]) {
    application.register(identifier, window.Controllers[identifier])
}
