import { Controller } from "@hotwired/stimulus";

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

    static values = {
        maxSize: Number,
        maxSizeFormatted: String,
    }

    connect() {
        // If this controller doesn't have explicit max size values, try to
        // inherit them from the nearest enclosing file-size-validator scope.
        if (!this.hasMaxSizeValue) {
            const inherited = this.element.closest('[data-file-size-validator-max-size-value]')
            if (inherited?.dataset?.fileSizeValidatorMaxSizeValue) {
                const parsed = parseInt(inherited.dataset.fileSizeValidatorMaxSizeValue, 10)
                if (!Number.isNaN(parsed)) {
                    this.maxSizeValue = parsed
                }
            }
        }

        if (!this.hasMaxSizeFormattedValue) {
            const inherited = this.element.closest('[data-file-size-validator-max-size-formatted-value]')
            if (inherited?.dataset?.fileSizeValidatorMaxSizeFormattedValue) {
                this.maxSizeFormattedValue = inherited.dataset.fileSizeValidatorMaxSizeFormattedValue
            }
        }
    }

    buildOversizeMessage(file) {
        const maxSize = this.maxSizeFormattedValue || this.formatBytes(this.maxSizeValue || 0)
        return `The file "${file.name}" (${this.formatBytes(file.size)}) exceeds the maximum upload size of ${maxSize}.`
    }

    formatBytes(bytes, decimals = 2) {
        if (!bytes || bytes === 0) return '0 Bytes'

        const k = 1024
        const dm = decimals < 0 ? 0 : decimals
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']

        const i = Math.floor(Math.log(bytes) / Math.log(k))

        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + sizes[i]
    }

    /**
     * Generate and display image preview
     * Creates object URL for selected image and updates preview display
     * 
     * @param {Event} event - Change event from file input
     */
    preview(event) {
        if (event.target.files.length > 0) {
            const file = event.target.files[0]

            if (this.hasMaxSizeValue && this.maxSizeValue > 0 && file.size > this.maxSizeValue) {
                alert(this.buildOversizeMessage(file))

                // Clear invalid selection so validators + UI stay consistent
                event.target.value = ''
                return
            }
            const reader = new FileReader();
            reader.onload = () => {
                this.previewTarget.src = reader.result; // This will be a data: URL
                this.loadingTarget.classList.add("d-none");
                this.previewTarget.hidden = false;
            };
            reader.readAsDataURL(file);
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["image-preview"] = ImagePreview;