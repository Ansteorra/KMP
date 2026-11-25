import { Controller } from "@hotwired/stimulus"
import { DefaultAllowlist, sanitizeHtml } from "bootstrap/js/dist/util/sanitizer.js"

/**
 * Markdown Editor Controller
 * 
 * Initializes an EasyMDE markdown editor on a textarea element.
 * 
 * Usage:
 * <textarea data-controller="markdown-editor"
 *           data-markdown-editor-placeholder-value="Enter your markdown here..."
 *           data-markdown-editor-min-height-value="200px"></textarea>
 * 
 * Values:
 * - placeholder: Placeholder text for the editor (optional)
 * - minHeight: Minimum height of the editor (default: "300px")
 */
class MarkdownEditorController extends Controller {
    static values = {
        placeholder: { type: String, default: "Enter text here..." },
        minHeight: { type: String, default: "300px" }
    }

    connection = 0

    // Initialize function
    initialize() {
        this.editor = null
    }

    // Connect function - runs when controller connects to DOM
    async connect() {
        const connection = ++this.connection
        const { default: EasyMDE } = await import('easymde')
        if (connection !== this.connection || !this.element.isConnected) return

        // Initialize EasyMDE on the textarea
        this.editor = new EasyMDE({
            element: this.element,
            placeholder: this.placeholderValue,
            minHeight: this.minHeightValue,
            spellChecker: false,
            autoDownloadFontAwesome: false, // Font Awesome is already loaded via @fortawesome/fontawesome-free
            status: ["lines", "words", "cursor"],
            toolbar: [
                "bold",
                "italic",
                "heading",
                "|",
                "quote",
                "unordered-list",
                "ordered-list",
                "|",
                "link",
                "image",
                "|",
                "preview",
                "side-by-side",
                "fullscreen",
                "|",
                "guide"
            ],
            // Ensure the editor works with form submissions
            forceSync: true,
            // Configure autosave
            autosave: {
                enabled: false
            },
            renderingConfig: {
                sanitizerFunction: html => sanitizeHtml(html, DefaultAllowlist)
            }
        })

        const input = this.editor.codemirror.getInputField?.()
        const label = this.element.labels?.[0]
        if (input && label) {
            label.id ||= `${this.element.id}-label`
            input.setAttribute('aria-labelledby', label.id)
            label.addEventListener('click', this.focusEditor)
        }
        for (const attribute of ['aria-describedby', 'aria-invalid']) {
            const value = this.element.getAttribute(attribute)
            if (value) input?.setAttribute(attribute, value)
        }
    }

    focusEditor = () => this.editor?.codemirror.focus()

    // Disconnect function - cleanup when controller disconnects
    disconnect() {
        this.connection += 1
        this.element.labels?.[0]?.removeEventListener('click', this.focusEditor)
        if (this.editor) {
            this.editor.toTextArea()
            this.editor = null
        }
    }

    // Public method to get markdown content
    getValue() {
        return this.editor ? this.editor.value() : ''
    }

    // Public method to set markdown content
    setValue(value) {
        if (this.editor) {
            this.editor.value(value)
        }
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["markdown-editor"] = MarkdownEditorController;
