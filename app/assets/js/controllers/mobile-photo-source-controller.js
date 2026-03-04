import { Controller } from "@hotwired/stimulus";

class MobilePhotoSourceController extends Controller {
    static targets = ["fileInput"];

    chooseCamera(event) {
        event.preventDefault();
        this.openPicker("user");
    }

    chooseGallery(event) {
        event.preventDefault();
        this.openPicker(null);
    }

    openPicker(captureMode) {
        if (!this.hasFileInputTarget) {
            return;
        }
        if (captureMode) {
            this.fileInputTarget.setAttribute("capture", captureMode);
        } else {
            this.fileInputTarget.removeAttribute("capture");
        }
        this.fileInputTarget.click();
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["mobile-photo-source"] = MobilePhotoSourceController;

export default MobilePhotoSourceController;
