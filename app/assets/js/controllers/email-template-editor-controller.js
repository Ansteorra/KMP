import { Controller } from "@hotwired/stimulus"
import EasyMDE from "easymde"

/**
 * Email Template Editor Controller
 * 
 * Extends markdown editor functionality with variable insertion support for email templates.
 * Provides buttons to insert available variables into the template.
 * 
 * Usage:
 * <div data-controller="email-template-editor"
 *      data-email-template-editor-variables-value='[{"name":"userName","description":"User name"}]'>
 *   <textarea data-email-template-editor-target="editor"></textarea>
 *   <div data-email-template-editor-target="variableButtons"></div>
 * </div>
 * 
 * Values:
 * - variables: Array of available variables [{name, description}]
 * - placeholder: Placeholder text for the editor
 * - minHeight: Minimum height of the editor
 */
class EmailTemplateEditorController extends Controller {
    static targets = ["editor", "variableButtons"]
    
    static values = {
        variables: { type: Array, default: [] },
        placeholder: { type: String, default: "Enter email template..." },
        minHeight: { type: String, default: "400px" }
    }

    initialize() {
        this.editor = null
    }

    connect() {
        // Initialize EasyMDE on the textarea
        this.editor = new EasyMDE({
            element: this.editorTarget,
            placeholder: this.placeholderValue,
            minHeight: this.minHeightValue,
            spellChecker: false,
            status: ["lines", "words", "cursor"],
            toolbar: this.buildToolbar(),
            forceSync: true,
            autosave: {
                enabled: false
            },
            previewRender: (plainText) => {
                return this.renderPreview(plainText);
            }
        })

        // Render variable insertion buttons if we have variables
        if (this.hasVariableButtonsTarget && this.variablesValue.length > 0) {
            this.renderVariableButtons()
        }

        console.log('Email template editor initialized with', this.variablesValue.length, 'variables')
    }

    disconnect() {
        if (this.editor) {
            this.editor.toTextArea()
            this.editor = null
        }
    }

    /**
     * Build custom toolbar with variable insertion button
     */
    buildToolbar() {
        const toolbar = [
            "bold",
            "italic",
            "heading",
            "|",
            "quote",
            "unordered-list",
            "ordered-list",
            "|",
            "link",
            "|",
            "preview",
            "side-by-side",
            "fullscreen",
            "|",
        ];

        // Add custom variable insertion button
        if (this.variablesValue.length > 0) {
            toolbar.push({
                name: "insert-variable",
                action: (editor) => {
                    this.showVariableMenu(editor)
                },
                className: "fa fa-code",
                title: "Insert Variable",
            });
        }

        toolbar.push("guide");

        return toolbar;
    }

    /**
     * Show variable insertion menu
     */
    showVariableMenu(editor) {
        // Get cursor position
        const cm = editor.codemirror;
        const cursor = cm.getCursor();
        
        // Create a simple prompt with variable options
        const varNames = this.variablesValue.map(v => v.name).join(', ');
        const selectedVar = prompt(`Available variables:\n${varNames}\n\nEnter variable name to insert:`);
        
        if (selectedVar) {
            const variable = this.variablesValue.find(v => v.name === selectedVar);
            if (variable) {
                cm.replaceSelection(`{{${variable.name}}}`);
            } else {
                alert('Invalid variable name');
            }
        }
    }

    /**
     * Render variable insertion buttons
     */
    renderVariableButtons() {
        const container = this.variableButtonsTarget;
        container.innerHTML = '<div class="mb-2"><strong>Available Variables:</strong> Click to insert</div>';
        
        const buttonGroup = document.createElement('div');
        buttonGroup.className = 'btn-group flex-wrap mb-3';
        buttonGroup.setAttribute('role', 'group');
        
        this.variablesValue.forEach(variable => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-primary';
            btn.textContent = `{{${variable.name}}}`;
            btn.title = variable.description || variable.name;
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.insertVariable(variable.name);
            });
            
            buttonGroup.appendChild(btn);
        });
        
        container.appendChild(buttonGroup);

        // Add syntax help
        const helpText = document.createElement('div');
        helpText.className = 'alert alert-info small';
        helpText.innerHTML = '<strong>Syntax:</strong> Use <code>{{variableName}}</code> or <code>${variableName}</code> to insert variables. They will be replaced with actual values when the email is sent.';
        container.appendChild(helpText);
    }

    /**
     * Insert a variable at the current cursor position
     */
    insertVariable(variableName) {
        if (!this.editor) return;
        
        const cm = this.editor.codemirror;
        const doc = cm.getDoc();
        const cursor = doc.getCursor();
        
        doc.replaceRange(`{{${variableName}}}`, cursor);
        
        // Move cursor after the inserted text
        cm.focus();
    }

    /**
     * Render preview with variable highlighting
     */
    renderPreview(plainText) {
        // Convert markdown to HTML
        let html = this.editor.markdown(plainText);
        
        // Highlight variables in the preview
        html = html.replace(/\{\{([^}]+)\}\}/g, '<span class="badge bg-primary">{{$1}}</span>');
        html = html.replace(/\$\{([^}]+)\}/g, '<span class="badge bg-success">${$1}</span>');
        
        return html;
    }

    /**
     * Get the editor content
     */
    getValue() {
        return this.editor ? this.editor.value() : '';
    }

    /**
     * Set the editor content
     */
    setValue(value) {
        if (this.editor) {
            this.editor.value(value);
        }
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["email-template-editor"] = EmailTemplateEditorController;
