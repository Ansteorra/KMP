import { Controller } from "@hotwired/stimulus"

/**
 * ImagePreview Stimulus Controller
 * 
 * Provides real-time image preview functionality for file upload forms.
 * Creates object URLs for selected images and displays them immediately
 * for better user experience during file selection.
 * 
 * Features:
 * - Instant image preview on file selection
 * - Object URL creation and management
 * - Loading state management
 * - Preview visibility control
 * - Automatic cleanup of resources
 * 
 * Targets:
 * - file: File input element for image selection
 * - preview: Image element for displaying preview
 * - loading: Loading indicator element
 * 
 * Usage:
 * <div data-controller="image-preview">
 *   <input data-image-preview-target="file" 
 *          data-action="change->image-preview#preview" 
 *          type="file" accept="image/*">
 *   <div data-image-preview-target="loading">Loading...</div>
 *   <img data-image-preview-target="preview" hidden>
 * </div>
 */
class ImagePreview extends Controller {

    static targets = ['file', 'preview', 'loading']

    /**
     * Generate and display image preview
     * Creates object URL for selected image and updates preview display
     * 
     * @param {Event} event - Change event from file input
     */
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