import { Controller } from "@hotwired/stimulus";
import QRCode from 'qrcode';

/**
 * QR Code Generator Controller
 * 
 * Generates QR codes dynamically using the qrcode library.
 * Generates the QR code when the modal/container is shown to avoid unnecessary generation.
 * 
 * Usage:
 * <div data-controller="qrcode"
 *      data-qrcode-url-value="https://example.com"
 *      data-qrcode-size-value="256"
 *      data-qrcode-modal-id-value="qrCodeModal">
 *   <div data-qrcode-target="canvas"></div>
 * </div>
 * 
 * Or with a modal:
 * <div class="modal" id="qrCodeModal" data-controller="qrcode" 
 *      data-qrcode-url-value="https://example.com">
 *   <div data-qrcode-target="canvas"></div>
 * </div>
 */
class QrcodeController extends Controller {
    static targets = ["canvas"]
    
    static values = {
        url: String,           // The URL to encode in QR code
        size: { type: Number, default: 256 },  // QR code size in pixels
        modalId: String,       // Optional modal ID to detect when to generate
        colorDark: { type: String, default: '#000000' },
        colorLight: { type: String, default: '#ffffff' },
        errorCorrectionLevel: { type: String, default: 'H' } // L, M, Q, H
    }
    
    connect() {
        // If modal ID is provided, wait for modal to show
        if (this.hasModalIdValue) {
            const modal = document.getElementById(this.modalIdValue);
            if (modal) {
                modal.addEventListener('shown.bs.modal', () => this.generate());
            }
        } else {
            // Generate immediately if no modal
            this.generate();
        }
    }
    
    disconnect() {
        // Cleanup if needed
        this.generated = false;
    }
    
    /**
     * Generate the QR code
     */
    generate() {
        // Only generate once
        if (this.generated) {
            return;
        }
        
        if (!this.hasUrlValue) {
            console.error('QR Code Controller: URL value is required');
            return;
        }
        
        if (!this.hasCanvasTarget) {
            console.error('QR Code Controller: Canvas target is required');
            return;
        }
        
        // Clear any existing content
        this.canvasTarget.innerHTML = '';
        
        // Create canvas element
        const canvas = document.createElement('canvas');
        this.canvasTarget.appendChild(canvas);
        
        // Generate QR code
        QRCode.toCanvas(canvas, this.urlValue, {
            width: this.sizeValue,
            margin: 2,
            color: {
                dark: this.colorDarkValue,
                light: this.colorLightValue
            },
            errorCorrectionLevel: this.errorCorrectionLevelValue
        }, (error) => {
            if (error) {
                console.error('QR Code generation error:', error);
                this.canvasTarget.innerHTML = '<p class="text-danger">Error generating QR code</p>';
            } else {
                this.generated = true;
            }
        });
    }
    
    /**
     * Regenerate the QR code (useful if URL changes)
     */
    regenerate() {
        this.generated = false;
        this.generate();
    }
    
    /**
     * Download the QR code as PNG
     */
    download() {
        if (!this.generated) {
            this.generate();
        }
        
        const canvas = this.canvasTarget.querySelector('canvas');
        if (!canvas) {
            console.error('QR Code Controller: Canvas not found');
            return;
        }
        
        // Create download link
        const link = document.createElement('a');
        link.download = 'qrcode.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }
    
    /**
     * Copy QR code to clipboard as image
     */
    async copyToClipboard() {
        if (!this.generated) {
            this.generate();
        }
        
        const canvas = this.canvasTarget.querySelector('canvas');
        if (!canvas) {
            console.error('QR Code Controller: Canvas not found');
            return;
        }
        
        try {
            canvas.toBlob(async (blob) => {
                const item = new ClipboardItem({ 'image/png': blob });
                await navigator.clipboard.write([item]);
                console.log('QR Code copied to clipboard');
            });
        } catch (error) {
            console.error('Failed to copy QR code:', error);
        }
    }
}

// Register controller globally
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["qrcode"] = QrcodeController;

export default QrcodeController;
