import { Controller } from "@hotwired/stimulus"

class CsvDownloadController extends Controller {
    static values = {
        url: String,
        filename: String
    }
    static targets = ["button"]

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

    connect() {
        if (this.hasButtonTarget) {
            this.buttonTarget.addEventListener('click', this.download.bind(this));
        } else {
            this.element.addEventListener('click', this.download.bind(this));
        }
    }

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
