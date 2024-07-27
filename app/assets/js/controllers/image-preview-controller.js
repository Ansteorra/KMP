import { Controller } from "@hotwired/stimulus"

class ImagePreview extends Controller {

    static targets = ['file', 'preview', 'loading']

    preview(event) {
        if (event.target.files.length > 0) {
            let src = URL.createObjectURL(event.target.files[0]);
            this.previewTarget.src = src;
            this.loadingTarget.classList.add("d-none")
            this.previewTarget.hidden = false;
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["image-preview"] = ImagePreview;