import { Controller } from "@hotwired/stimulus"
import EasyMDE from "easymde"

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

    // Initialize function
    initialize() {
        this.editor = null
    }

    // Connect function - runs when controller connects to DOM
    connect() {
        // Initialize EasyMDE on the textarea
        this.editor = new EasyMDE({
            element: this.element,
            placeholder: this.placeholderValue,
            minHeight: this.minHeightValue,
            spellChecker: false,
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
            // Improve the preview rendering
            previewRender: function(plainText) {
                // Basic markdown to HTML conversion for preview
                // You can enhance this with a proper markdown parser if needed
                return this.parent.markdown(plainText);
            }
        })

        // Log editor initialization
        console.log('Markdown editor initialized on:', this.element)
    }

    // Disconnect function - cleanup when controller disconnects
    disconnect() {
        if (this.editor) {
            this.editor.toTextArea()
            this.editor = null
            console.log('Markdown editor cleaned up')
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
