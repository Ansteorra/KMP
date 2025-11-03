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
    
    // Initialize the generated flag
    generated = false;
    
    connect() {
        this.generated = false;
        
        // If modal ID is provided, wait for modal to show
        if (this.hasModalIdValue) {
            const modal = document.getElementById(this.modalIdValue);
            if (modal) {
                // Store the listener as an instance property
                this._onModalShown = () => this.generate();
                modal.addEventListener('shown.bs.modal', this._onModalShown);
            }
        } else {
            // Generate immediately if no modal
            this.generate();
        }
    }
    
    disconnect() {
        // Remove event listener to prevent memory leak
        if (this.hasModalIdValue && this._onModalShown) {
            const modal = document.getElementById(this.modalIdValue);
            if (modal) {
                modal.removeEventListener('shown.bs.modal', this._onModalShown);
            }
        }
        this.generated = false;
    }
    
    /**
     * Generate the QR code
     * Returns a Promise that resolves when generation is complete
     */
    generate() {
        // Only generate once
        if (this.generated) {
            return Promise.resolve();
        }
        
        if (!this.hasUrlValue) {
            throw new Error('QR Code: URL value is required');
        }
        
        if (!this.hasCanvasTarget) {
            throw new Error('QR Code: Canvas target is required');
        }
        
        // Clear any existing content
        this.canvasTarget.innerHTML = '';
        
        // Create canvas element
        const canvas = document.createElement('canvas');
        this.canvasTarget.appendChild(canvas);
        
        // Return a Promise that resolves when QR code generation is complete
        return new Promise((resolve, reject) => {
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
                    this.canvasTarget.innerHTML = '<p class="text-danger">Error generating QR code</p>';
                    reject(error);
                } else {
                    this.generated = true;
                    resolve();
                }
            });
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
    async download() {
        try {
            // Wait for generation to complete
            await this.generate();
            
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
        } catch (error) {
            console.error('QR Code generation failed:', error);
        }
    }
    
    /**
     * Copy QR code to clipboard as image
     */
    async copyToClipboard() {
        try {
            // Wait for generation to complete
            await this.generate();
            
            const canvas = this.canvasTarget.querySelector('canvas');
            if (!canvas) {
                console.error('QR Code Controller: Canvas not found');
                return;
            }
            
            // Convert canvas to blob using Promise
            const blob = await new Promise((resolve, reject) => {
                canvas.toBlob(blob => {
                    if (blob) {
                        resolve(blob);
                    } else {
                        reject(new Error('Failed to create blob from canvas'));
                    }
                });
            });
            
            // Copy to clipboard
            const item = new ClipboardItem({ 'image/png': blob });
            await navigator.clipboard.write([item]);
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
