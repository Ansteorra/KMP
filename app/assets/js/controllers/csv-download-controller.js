import { Controller } from "@hotwired/stimulus"

/**
 * CsvDownload Stimulus Controller
 * 
 * Handles CSV file downloads with AJAX requests and automatic file saving.
 * Provides seamless CSV export functionality with error handling and 
 * proper resource cleanup.
 * 
 * Features:
 * - AJAX-based file download
 * - Automatic file saving via browser download
 * - Configurable filename and URL
 * - Error handling with user feedback
 * - Automatic resource cleanup
 * 
 * Values:
 * - url: String - URL to download CSV from
 * - filename: String - Name for downloaded file
 * 
 * Targets:
 * - button: Optional button element for click handling
 * 
 * Usage:
 * <button data-controller="csv-download" 
 *         data-csv-download-url-value="/export.csv"
 *         data-csv-download-filename-value="members.csv">
 *   Download CSV
 * </button>
 */
class CsvDownloadController extends Controller {
    static values = {
        url: String,
        filename: String
    }
    static targets = ["button"]

    /**
     * Download CSV file via AJAX and trigger browser download
     * Handles the complete download workflow with error handling
     * 
     * @param {Event} event - Click event from download trigger
     */
    async download(event) {
        event.preventDefault();
        const url = this.urlValue || this.element.getAttribute('href') || this.element.dataset.url;
        if (!url) {
            alert("No CSV URL provided.");
            return;
        }
        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) {
                throw new Error(`Failed to download CSV: ${response.status}`);
            }
            const blob = await response.blob();
            const downloadUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = downloadUrl;
            a.download = this.filenameValue || 'export.csv';
            document.body.appendChild(a);
            a.click();
            setTimeout(() => {
                window.URL.revokeObjectURL(downloadUrl);
                a.remove();
            }, 100);
        } catch (error) {
            alert("Error downloading CSV: " + error.message);
        }
    }

    /**
     * Connect controller to DOM
     * Sets up event listeners for download triggering
     */
    connect() {
        if (this.hasButtonTarget) {
            this.buttonTarget.addEventListener('click', this.download.bind(this));
        } else {
            this.element.addEventListener('click', this.download.bind(this));
        }
    }

    /**
     * Disconnect controller from DOM
     * Cleans up event listeners
     */
    disconnect() {
        if (this.hasButtonTarget) {
            this.buttonTarget.removeEventListener('click', this.download.bind(this));
        } else {
            this.element.removeEventListener('click', this.download.bind(this));
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["csv-download"] = CsvDownloadController;
