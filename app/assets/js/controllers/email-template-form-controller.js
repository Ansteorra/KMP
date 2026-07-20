import { Controller } from "@hotwired/stimulus"

/**
 * Email Template Form Controller
 *
 * Manages the dynamic behaviour of the email template form:
 * - Auto-generates slug from Name if slug is empty
 * - Parses {{variable}} placeholders from template content and surfaces them
 *   in the Variables Contract tab as an authoring aid
 */
class EmailTemplateFormController extends Controller {
    static targets = [
        "availableVars", "subjectTemplate",
        "nameField", "slugField",
        "htmlTemplate", "textTemplate",
        "parsedVarsPanel", "parsedVarsList",
        "variablesSchema", "variableRows", "variableRowTemplate",
        "emptyVariableMessage", "variableName", "variableDescription",
        "variableType", "variableRequired",
    ]

    connect() {
        this._renderVariableContractRows(this._readVariableSchema())
        // Parse placeholders from existing template content on connect
        this._refreshParsedPlaceholders()
    }

    // ── Slug auto-generation ────────────────────────────────────────────────

    /**
     * Called on name field input. Auto-fills slug if slug is currently empty.
     */
    nameChanged() {
        if (!this.hasSlugFieldTarget || !this.hasNameFieldTarget) return

        const slug = this.slugFieldTarget.value.trim()
        if (slug !== '') return  // Don't overwrite a manually-entered slug

        this.slugFieldTarget.value = this._slugify(this.nameFieldTarget.value)
    }

    /**
     * Convert an arbitrary string to a valid slug (lowercase, hyphens).
     * @param {string} text
     * @returns {string}
     */
    _slugify(text) {
        return text
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9\s-]/g, '')   // strip non-alphanum
            .replace(/[\s_-]+/g, '-')         // spaces/underscores → hyphen
            .replace(/^-+|-+$/g, '')          // trim leading/trailing hyphens
    }

    // ── Template placeholder parsing ────────────────────────────────────────

    /**
     * Called when html_template or text_template content changes.
     * Refreshes the parsed-placeholders helper in the Variables Contract tab.
     */
    templateChanged() {
        this._refreshParsedPlaceholders()
    }

    /**
     * Add a blank variable contract row.
     */
    addVariable() {
        this._appendVariableRow({
            name: '',
            description: '',
            type: 'string',
            required: false,
        }, true)
        this._refreshVariableRowsState()
    }

    /**
     * Add a detected placeholder to the variable contract.
     *
     * @param {string} name Placeholder name
     */
    addVariableFromPlaceholder(name) {
        if (!name || this._schemaHasVariable(name)) return

        this._appendVariableRow({
            name,
            description: this._humanizeVariableName(name),
            type: 'string',
            required: true,
        }, true)
        this.serializeVariableContract()
        this._refreshVariableRowsState()
        this._refreshParsedPlaceholders()
    }

    /**
     * Remove a variable contract row.
     *
     * @param {Event} event
     */
    removeVariable(event) {
        const row = event.currentTarget.closest('[data-variable-contract-row]')
        if (!row) return

        row.remove()
        this.serializeVariableContract()
        this._refreshVariableRowsState()
        this._refreshParsedPlaceholders()
    }

    /**
     * Keep the hidden JSON field up to date as rows are edited.
     */
    variableContractChanged() {
        this.serializeVariableContract()
        this._refreshVariableRowsState()
        this._refreshParsedPlaceholders()
    }

    /**
     * Serialize the row editor into the backend JSON field.
     */
    serializeVariableContract() {
        if (!this.hasVariablesSchemaTarget) return

        const schema = this._collectVariableContractRows()
        this.variablesSchemaTarget.value = schema.length > 0
            ? JSON.stringify(schema)
            : ''
    }

    /**
     * Parse {{varName}} placeholders from the HTML and text template fields
     * and display them as a helper in the Variables Contract tab.
     */
    _refreshParsedPlaceholders() {
        if (!this.hasParsedVarsPanelTarget || !this.hasParsedVarsListTarget) return

        const htmlContent = this.hasHtmlTemplateTarget ? this.htmlTemplateTarget.value : ''
        const textContent = this.hasTextTemplateTarget ? this.textTemplateTarget.value : ''
        const combined = htmlContent + ' ' + textContent

        const names = this._extractPlaceholders(combined)

        if (names.length === 0) {
            this.parsedVarsPanelTarget.style.display = 'none'
            return
        }

        this.parsedVarsPanelTarget.style.display = ''
        this.parsedVarsListTarget.innerHTML = ''

        names.forEach(name => {
            const button = document.createElement('button')
            button.type = 'button'
            button.className = this._schemaHasVariable(name)
                ? 'btn btn-sm btn-success'
                : 'btn btn-sm btn-outline-primary'
            button.textContent = '{{' + name + '}}'
            button.disabled = this._schemaHasVariable(name)
            button.setAttribute('aria-label', this._schemaHasVariable(name)
                ? `${name} is already in the variable contract`
                : `Add ${name} to the variable contract`)
            button.addEventListener('click', () => this.addVariableFromPlaceholder(name))
            this.parsedVarsListTarget.appendChild(button)
        })
    }

    /**
     * Extract unique placeholder names from a template string.
     * Matches {{varName}} and {{#if varName}} / {{/if}} / {{else}} patterns.
     * Returns names sorted alphabetically, excluding control keywords.
     *
     * @param {string} content
     * @returns {string[]}
     */
    _extractPlaceholders(content) {
        const CONTROL_KEYWORDS = new Set(['if', 'else', '/if', '#if', 'true', 'false'])
        const pattern = /\{\{\s*([a-zA-Z_][a-zA-Z0-9_.]*)\s*\}\}/g
        const conditionPattern = /\{\{#if\s+(.+?)\}\}/gs
        const seen = new Set()
        let match

        while ((match = pattern.exec(content)) !== null) {
            const name = match[1].trim()
            if (!CONTROL_KEYWORDS.has(name)) {
                seen.add(name)
            }
        }

        while ((match = conditionPattern.exec(content)) !== null) {
            const conditionWithoutStrings = match[1].replace(/["'][^"']*["']/g, '')
            const conditionVariablePattern = /\$?\b([a-zA-Z_][a-zA-Z0-9_]*)\b/g
            let conditionMatch

            while ((conditionMatch = conditionVariablePattern.exec(conditionWithoutStrings)) !== null) {
                const name = conditionMatch[1].trim()
                if (!CONTROL_KEYWORDS.has(name)) {
                    seen.add(name)
                }
            }
        }

        return Array.from(seen).sort()
    }

    /**
     * Parse the hidden JSON schema field.
     *
     * @returns {Array<object>}
     */
    _readVariableSchema() {
        if (!this.hasVariablesSchemaTarget || this.variablesSchemaTarget.value.trim() === '') {
            return []
        }

        try {
            const parsed = JSON.parse(this.variablesSchemaTarget.value)
            return this._normalizeSchemaEntries(parsed)
        } catch (error) {
            return []
        }
    }

    /**
     * Normalize supported schema shapes into row entries.
     *
     * @param {Array<object>|object} entries
     * @returns {Array<object>}
     */
    _normalizeSchemaEntries(entries) {
        if (Array.isArray(entries)) {
            return entries
                .filter(entry => entry && typeof entry === 'object')
                .map(entry => ({
                    name: entry.name || '',
                    description: entry.description || '',
                    type: entry.type || 'string',
                    required: Boolean(entry.required),
                }))
        }

        if (!entries || typeof entries !== 'object') {
            return []
        }

        return Object.entries(entries).map(([name, entry]) => ({
            name,
            description: entry?.description || '',
            type: entry?.type || 'string',
            required: Boolean(entry?.required),
        }))
    }

    /**
     * Render schema entries into editor rows.
     *
     * @param {Array<object>} entries
     */
    _renderVariableContractRows(entries) {
        if (!this.hasVariableRowsTarget || !this.hasVariableRowTemplateTarget) return

        this.variableRowsTarget.innerHTML = ''
        entries.forEach(entry => this._appendVariableRow(entry))
        this.serializeVariableContract()
        this._refreshVariableRowsState()
    }

    /**
     * Append one variable contract row.
     *
     * @param {object} entry
     * @param {boolean} shouldFocus Whether to focus the new row name input
     */
    _appendVariableRow(entry, shouldFocus = false) {
        if (!this.hasVariableRowsTarget || !this.hasVariableRowTemplateTarget) return

        const fragment = this.variableRowTemplateTarget.content.cloneNode(true)
        const row = fragment.querySelector('[data-variable-contract-row]')
        const name = row.querySelector('[data-email-template-form-target~="variableName"]')
        const description = row.querySelector('[data-email-template-form-target~="variableDescription"]')
        const type = row.querySelector('[data-email-template-form-target~="variableType"]')
        const required = row.querySelector('[data-email-template-form-target~="variableRequired"]')

        name.value = entry.name || ''
        description.value = entry.description || ''
        type.value = entry.type || 'string'
        required.checked = Boolean(entry.required)

        this.variableRowsTarget.appendChild(fragment)
        if (shouldFocus) {
            name.focus()
        }
    }

    /**
     * Collect valid variable contract rows for serialization.
     *
     * @returns {Array<object>}
     */
    _collectVariableContractRows() {
        if (!this.hasVariableRowsTarget) return []

        return Array.from(this.variableRowsTarget.querySelectorAll('[data-variable-contract-row]'))
            .map(row => {
                const name = row.querySelector('[data-email-template-form-target~="variableName"]')?.value.trim() || ''
                const description = row
                    .querySelector('[data-email-template-form-target~="variableDescription"]')?.value.trim() || ''
                const type = row.querySelector('[data-email-template-form-target~="variableType"]')?.value || 'string'
                const required = row.querySelector('[data-email-template-form-target~="variableRequired"]')?.checked || false

                return { name, description, type, required }
            })
            .filter(entry => entry.name !== '')
    }

    /**
     * Check whether the row editor already has a variable.
     *
     * @param {string} name Variable name
     * @returns {boolean}
     */
    _schemaHasVariable(name) {
        return this._collectVariableContractRows().some(entry => entry.name === name)
    }

    /**
     * Toggle empty-state messaging.
     */
    _refreshVariableRowsState() {
        if (!this.hasEmptyVariableMessageTarget || !this.hasVariableRowsTarget) return

        this.emptyVariableMessageTarget.classList.toggle(
            'd-none',
            this.variableRowsTarget.querySelector('[data-variable-contract-row]') !== null,
        )
    }

    /**
     * Create a readable default description from a variable name.
     *
     * @param {string} name Variable name
     * @returns {string}
     */
    _humanizeVariableName(name) {
        return name
            .replace(/_/g, ' ')
            .replace(/([a-z])([A-Z])/g, '$1 $2')
            .replace(/\b\w/g, character => character.toUpperCase())
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["email-template-form"] = EmailTemplateFormController;
