import { Controller } from "@hotwired/stimulus"

class ModalOpener extends Controller {
    static values = { modalBtn: String }
    modalBtnValueChanged() {
        let modal = document.getElementById(this.modalBtnValue);
        modal.click();
    }
}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["modal-opener"] = ModalOpener;