import { Controller } from "@hotwired/stimulus"

/**
 * Camera Capture Controller
 * 
 * Provides enhanced mobile camera capture functionality.
 * Works in conjunction with HTML5 file input capture attribute.
 * 
 * Note: Most camera functionality is handled by HTML5 capture="environment"
 * attribute. This controller provides UI enhancements and fallback behavior.
 * 
 * Targets:
 * - cameraInput: File input with camera capture
 * - cameraButton: Optional button to trigger camera
 * - preview: Preview area for captured images
 * 
 * Actions:
 * - triggerCamera: Open device camera
 * - handleCapture: Handle image capture
 */
class CameraCaptureController extends Controller {
    static targets = ["cameraInput", "cameraButton", "preview"]

    /**
     * Initialize controller
     */
    connect() {
        console.log('CameraCaptureController connected')
        this.detectMobileDevice()
    }

    /**
     * Detect if user is on mobile device
     * Updates UI to show mobile-specific instructions
     */
    detectMobileDevice() {
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
        
        if (isMobile) {
            console.log('Mobile device detected - camera capture available')
            
            // Could add visual indicators that camera is available
            if (this.hasCameraInputTarget) {
                const parent = this.cameraInputTarget.parentElement
                const helpText = parent.querySelector('.form-text')
                if (helpText) {
                    helpText.classList.add('text-success')
                    helpText.innerHTML = '<i class="bi bi-camera-fill"></i> ' + helpText.innerHTML
                }
            }
        }
    }

    /**
     * Trigger camera input
     * Useful if you have a custom camera button instead of using the file input directly
     * 
     * @param {Event} event Button click event
     */
    triggerCamera(event) {
        event.preventDefault()
        
        if (this.hasCameraInputTarget) {
            this.cameraInputTarget.click()
        }
    }

    /**
     * Handle image capture
     * Called when user selects/captures an image
     * 
     * @param {Event} event File input change event
     */
    handleCapture(event) {
        const files = event.target.files
        
        if (files && files.length > 0) {
            console.log(`Captured ${files.length} image(s)`)
            
            // Show preview if target exists
            if (this.hasPreviewTarget) {
                this.showImagePreview(files[0])
            }
            
            // Dispatch custom event for other controllers to handle
            this.dispatch('imageCaptured', {
                detail: { files: Array.from(files) }
            })
        }
    }

    /**
     * Show image preview (optional enhancement)
     * 
     * @param {File} file Image file to preview
     */
    showImagePreview(file) {
        const reader = new FileReader()
        
        reader.onload = (e) => {
            this.previewTarget.innerHTML = `
                <div class="card">
                    <img src="${e.target.result}" class="card-img-top" alt="Preview" style="max-height: 300px; object-fit: contain;">
                    <div class="card-body">
                        <p class="card-text text-center">
                            <small class="text-muted">${file.name} (${this.formatFileSize(file.size)})</small>
                        </p>
                    </div>
                </div>
            `
            this.previewTarget.style.display = 'block'
        }
        
        reader.readAsDataURL(file)
    }

    /**
     * Format file size
     * 
     * @param {number} bytes File size in bytes
     * @returns {string} Formatted size
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes'
        
        const k = 1024
        const sizes = ['Bytes', 'KB', 'MB']
        const i = Math.floor(Math.log(bytes) / Math.log(k))
        
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
    }

    /**
     * Check if browser supports camera capture
     * 
     * @returns {boolean} True if capture is supported
     */
    static supportsCameraCapture() {
        const input = document.createElement('input')
        input.setAttribute('capture', 'camera')
        return input.capture !== undefined
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["camera-capture"] = CameraCaptureController
