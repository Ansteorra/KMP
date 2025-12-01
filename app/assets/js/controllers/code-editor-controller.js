import { Controller } from "@hotwired/stimulus"

/**
 * Code Editor Controller
 * 
 * Provides syntax validation and enhanced editing for YAML and JSON content.
 * Shows real-time validation errors and line numbers.
 * 
 * Usage:
 * <div data-controller="code-editor"
 *      data-code-editor-language-value="yaml"
 *      data-code-editor-validate-on-change-value="true">
 *   <textarea data-code-editor-target="textarea"></textarea>
 *   <div data-code-editor-target="errorDisplay"></div>
 * </div>
 * 
 * Values:
 * - language: 'yaml' or 'json'
 * - validateOnChange: boolean, whether to validate as user types
 * - minHeight: minimum height of the editor (default: '300px')
 */
class CodeEditorController extends Controller {
    static targets = ["textarea", "errorDisplay", "lineNumbers"]

    static values = {
        language: { type: String, default: 'yaml' },
        validateOnChange: { type: Boolean, default: true },
        minHeight: { type: String, default: '300px' }
    }

    connect() {
        this.setupEditor()
        this.validateContent()
        console.log('Code editor connected for:', this.languageValue)
    }

    disconnect() {
        // Cleanup if needed
    }

    setupEditor() {
        if (!this.hasTextareaTarget) return

        const textarea = this.textareaTarget

        // Create wrapper for editor with line numbers
        const wrapper = document.createElement('div')
        wrapper.className = 'code-editor-wrapper'
        wrapper.style.cssText = `
            display: flex;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            overflow: hidden;
            min-height: ${this.minHeightValue};
        `

        // Create line numbers element
        const lineNumbers = document.createElement('div')
        lineNumbers.className = 'code-editor-line-numbers'
        lineNumbers.style.cssText = `
            background: #f7f7f7;
            border-right: 1px solid #ddd;
            padding: 10px 8px;
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 13px;
            line-height: 1.5;
            color: #999;
            text-align: right;
            user-select: none;
            min-width: 40px;
        `
        lineNumbers.setAttribute('data-code-editor-target', 'lineNumbers')
        this.lineNumbersElement = lineNumbers

        // Style the textarea
        textarea.style.cssText = `
            flex: 1;
            border: none;
            padding: 10px;
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 13px;
            line-height: 1.5;
            resize: vertical;
            outline: none;
            min-height: ${this.minHeightValue};
            white-space: pre;
            overflow-wrap: normal;
            overflow-x: auto;
            tab-size: 2;
        `

        // Insert wrapper before textarea
        textarea.parentNode.insertBefore(wrapper, textarea)
        wrapper.appendChild(lineNumbers)
        wrapper.appendChild(textarea)

        // Update line numbers on content change
        textarea.addEventListener('input', () => {
            this.updateLineNumbers()
            if (this.validateOnChangeValue) {
                this.validateContent()
            }
        })

        textarea.addEventListener('scroll', () => {
            lineNumbers.scrollTop = textarea.scrollTop
        })

        textarea.addEventListener('keydown', (e) => {
            this.handleKeydown(e)
        })

        // Initial line numbers
        this.updateLineNumbers()
    }

    updateLineNumbers() {
        if (!this.lineNumbersElement || !this.hasTextareaTarget) return

        const lines = this.textareaTarget.value.split('\n')
        const lineNumbers = []
        for (let i = 1; i <= lines.length; i++) {
            lineNumbers.push(i)
        }
        this.lineNumbersElement.innerHTML = lineNumbers.join('<br>')
    }

    handleKeydown(e) {
        const textarea = this.textareaTarget

        // Handle Tab key for indentation
        if (e.key === 'Tab') {
            e.preventDefault()
            const start = textarea.selectionStart
            const end = textarea.selectionEnd
            const spaces = '  ' // 2 spaces for YAML/JSON indentation

            if (e.shiftKey) {
                // Shift+Tab: Remove indentation
                const beforeCursor = textarea.value.substring(0, start)
                const afterCursor = textarea.value.substring(end)
                const lineStart = beforeCursor.lastIndexOf('\n') + 1
                const line = textarea.value.substring(lineStart, start)

                if (line.startsWith('  ')) {
                    textarea.value = textarea.value.substring(0, lineStart) +
                        line.substring(2) +
                        textarea.value.substring(start)
                    textarea.selectionStart = textarea.selectionEnd = start - 2
                }
            } else {
                // Tab: Add indentation
                textarea.value = textarea.value.substring(0, start) + spaces + textarea.value.substring(end)
                textarea.selectionStart = textarea.selectionEnd = start + spaces.length
            }

            this.updateLineNumbers()
            if (this.validateOnChangeValue) {
                this.validateContent()
            }
        }

        // Handle Enter key - auto-indent
        if (e.key === 'Enter') {
            const start = textarea.selectionStart
            const beforeCursor = textarea.value.substring(0, start)
            const currentLineStart = beforeCursor.lastIndexOf('\n') + 1
            const currentLine = beforeCursor.substring(currentLineStart)
            const indent = currentLine.match(/^\s*/)[0]

            // Don't prevent default, but set up to add indent after
            setTimeout(() => {
                const newPos = textarea.selectionStart
                textarea.value = textarea.value.substring(0, newPos) + indent + textarea.value.substring(newPos)
                textarea.selectionStart = textarea.selectionEnd = newPos + indent.length
                this.updateLineNumbers()
            }, 0)
        }
    }

    validateContent() {
        if (!this.hasTextareaTarget) return

        const content = this.textareaTarget.value
        let error = null

        if (this.languageValue === 'json') {
            error = this.validateJSON(content)
        } else if (this.languageValue === 'yaml') {
            error = this.validateYAML(content)
        }

        this.displayError(error)
        return error === null
    }

    validateJSON(content) {
        if (!content.trim()) return null

        try {
            JSON.parse(content)
            return null
        } catch (e) {
            // Extract line number from error message if possible
            const match = e.message.match(/position (\d+)/)
            let lineInfo = ''
            if (match) {
                const position = parseInt(match[1])
                const lines = content.substring(0, position).split('\n')
                lineInfo = ` (line ${lines.length}, column ${lines[lines.length - 1].length + 1})`
            }
            return `JSON Error${lineInfo}: ${e.message}`
        }
    }

    validateYAML(content) {
        if (!content.trim()) return null

        // Basic YAML validation - check for common issues
        const lines = content.split('\n')
        const errors = []
        let inMultiline = false
        const indentStack = [0]

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i]
            const lineNum = i + 1

            // Skip empty lines and comments
            if (!line.trim() || line.trim().startsWith('#')) continue

            // Check for tabs (YAML should use spaces)
            if (line.includes('\t')) {
                errors.push(`Line ${lineNum}: Tabs are not allowed in YAML, use spaces`)
            }

            // Check for inconsistent indentation
            const indent = line.match(/^(\s*)/)[1].length
            if (indent % 2 !== 0 && indent > 0) {
                // Warning: odd indentation might be intentional in some cases
            }

            // Check for missing space after colon in key-value pairs
            const colonMatch = line.match(/^(\s*)([^:]+):([^\s])/)
            if (colonMatch && !line.includes(': ') && !line.match(/:\s*$/)) {
                // This might be a string with colon, check if it looks like a key
                const beforeColon = colonMatch[2].trim()
                if (!beforeColon.includes(' ') && !beforeColon.startsWith('-')) {
                    errors.push(`Line ${lineNum}: Missing space after colon`)
                }
            }

            // Check for duplicate keys at the same level (basic check)
            // This is a simplified check and won't catch all duplicates
        }

        // Try to parse as JavaScript object to catch more errors
        // This is a heuristic check for common YAML patterns
        try {
            // Check for basic structure issues
            if (content.includes('{{') && content.includes('}}')) {
                // Template syntax, skip strict validation
                return null
            }

            // Check for unquoted special characters that need quoting
            const specialChars = /[{}\[\]&*#?|>!%@`]/
            for (let i = 0; i < lines.length; i++) {
                const line = lines[i]
                if (line.trim().startsWith('-') || line.trim().startsWith('#')) continue

                const colonIndex = line.indexOf(':')
                if (colonIndex > 0) {
                    const value = line.substring(colonIndex + 1).trim()
                    // Check if unquoted value starts with special char
                    if (value && !value.startsWith('"') && !value.startsWith("'")) {
                        if (value[0] && specialChars.test(value[0]) && value[0] !== '>') {
                            errors.push(`Line ${i + 1}: Value may need to be quoted: ${value.substring(0, 20)}...`)
                        }
                    }
                }
            }
        } catch (e) {
            errors.push(`Parse error: ${e.message}`)
        }

        if (errors.length > 0) {
            return errors.slice(0, 3).join('\n') // Show first 3 errors
        }

        return null
    }

    displayError(error) {
        if (!this.hasErrorDisplayTarget) {
            // Create error display if it doesn't exist
            if (error && this.hasTextareaTarget) {
                const existingError = this.textareaTarget.parentNode.querySelector('.code-editor-error')
                if (existingError) {
                    existingError.innerHTML = this.formatError(error)
                    existingError.style.display = 'block'
                } else {
                    const errorDiv = document.createElement('div')
                    errorDiv.className = 'code-editor-error alert alert-danger mt-2 mb-0 small'
                    errorDiv.innerHTML = this.formatError(error)
                    this.textareaTarget.parentNode.parentNode.insertBefore(
                        errorDiv,
                        this.textareaTarget.parentNode.nextSibling
                    )
                }
            } else {
                const existingError = this.element.querySelector('.code-editor-error')
                if (existingError) {
                    existingError.style.display = 'none'
                }
            }
            return
        }

        if (error) {
            this.errorDisplayTarget.innerHTML = this.formatError(error)
            this.errorDisplayTarget.classList.remove('d-none')
            this.errorDisplayTarget.classList.add('alert', 'alert-danger', 'mt-2', 'mb-0', 'small')
        } else {
            this.errorDisplayTarget.innerHTML = ''
            this.errorDisplayTarget.classList.add('d-none')
            this.errorDisplayTarget.classList.remove('alert', 'alert-danger')
        }
    }

    formatError(error) {
        return `<i class="bi bi-exclamation-triangle me-1"></i><strong>Syntax Error:</strong><br><pre class="mb-0 mt-1" style="white-space: pre-wrap;">${this.escapeHtml(error)}</pre>`
    }

    escapeHtml(text) {
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }

    // Action to manually trigger validation
    validate(event) {
        event?.preventDefault()
        const isValid = this.validateContent()
        return isValid
    }

    // Check validation before form submit
    beforeSubmit(event) {
        if (!this.validateContent()) {
            const proceed = confirm('There are syntax errors in the content. Do you want to save anyway?')
            if (!proceed) {
                event.preventDefault()
            }
        }
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["code-editor"] = CodeEditorController;
