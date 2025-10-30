import { Controller } from "@hotwired/stimulus"

/**
 * WaiverTemplate Stimulus Controller
 * 
 * Manages the waiver template source selection interface, allowing users
 * to choose between uploading a PDF file or providing an external URL.
 * 
 * Features:
 * - Toggle between file upload and URL input
 * - Show/hide appropriate fields based on selection
 * - Clear unused fields when switching modes
 * 
 * Values:
 * - source: String - Current template source ('upload', 'url', or 'none')
 * 
 * Targets:
 * - uploadSection: Container for file upload field
 * - urlSection: Container for external URL field
 * - fileInput: File input element
 * - urlInput: URL text input element
 * 
 * Usage:
 * <div data-controller="waiver-template">
 *   <select data-action="change->waiver-template#toggleSource" 
 *           data-waiver-template-target="sourceSelect">
 *     <option value="none">No Template</option>
 *     <option value="upload">Upload PDF</option>
 *     <option value="url">External URL</option>
 *   </select>
 *   
 *   <div data-waiver-template-target="uploadSection">
 *     <input type="file" data-waiver-template-target="fileInput">
 *   </div>
 *   
 *   <div data-waiver-template-target="urlSection">
 *     <input type="text" data-waiver-template-target="urlInput">
 *   </div>
 * </div>
 */
class WaiverTemplateController extends Controller {
    static targets = ["uploadSection", "urlSection", "fileInput", "urlInput", "sourceSelect"]
    
    static values = {
        source: { type: String, default: "none" }
    }
    
    /**
     * Initialize controller
     */
    connect() {
        // Set initial state based on existing values
        this.updateDisplay()
    }
    
    /**
     * Handle template source selection change
     * 
     * @param {Event} event - Change event from source select
     */
    toggleSource(event) {
        this.sourceValue = event.target.value
        this.updateDisplay()
    }
    
    /**
     * Handle file input change - auto-select upload option
     * 
     * @param {Event} event - Change event from file input
     */
    fileSelected(event) {
        if (event.target.files && event.target.files.length > 0) {
            // Auto-select "upload" in the dropdown
            if (this.hasSourceSelectTarget) {
                this.sourceSelectTarget.value = "upload"
                this.sourceValue = "upload"
                this.updateDisplay()
            }
        }
    }
    
    /**
     * Update the display based on current source value
     */
    updateDisplay() {
        const source = this.sourceValue
        
        // Show/hide sections based on selection
        if (this.hasUploadSectionTarget) {
            this.uploadSectionTarget.style.display = source === "upload" ? "block" : "none"
        }
        
        if (this.hasUrlSectionTarget) {
            this.urlSectionTarget.style.display = source === "url" ? "block" : "none"
        }
        
        // Clear unused fields
        if (source !== "upload" && this.hasFileInputTarget) {
            this.fileInputTarget.value = ""
        }
        
        if (source !== "url" && this.hasUrlInputTarget) {
            this.urlInputTarget.value = ""
        }
    }
    
    /**
     * Handle source value changes
     */
    sourceValueChanged() {
        this.updateDisplay()
    }
}

// Register controller globally
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["waiver-template"] = WaiverTemplateController;
