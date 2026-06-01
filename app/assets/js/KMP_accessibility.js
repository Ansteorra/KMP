import { Modal } from 'bootstrap'

const getBootstrapModal = () => window.bootstrap?.Modal || globalThis.bootstrap?.Modal || Modal

const escapeHtml = (text) => {
    const div = document.createElement('div')
    div.textContent = text
    return div.innerHTML
}

const ensureLiveRegion = (role = 'status') => {
    const id = role === 'alert' ? 'kmp-global-alert-region' : 'kmp-global-status-region'
    let region = document.getElementById(id)
    if (!region) {
        region = document.createElement('div')
        region.id = id
        region.className = 'visually-hidden'
        region.setAttribute('role', role)
        region.setAttribute('aria-live', role === 'alert' ? 'assertive' : 'polite')
        region.setAttribute('aria-atomic', 'true')
        document.body.appendChild(region)
    }
    return region
}

const announce = (message, options = {}) => {
    const region = ensureLiveRegion(options.assertive ? 'alert' : 'status')
    region.textContent = ''
    window.setTimeout(() => {
        region.textContent = message
    }, 10)
}

const openDialog = ({ title, message, inputLabel, inputRequired = false, confirmLabel = 'OK', cancelLabel = 'Cancel' }) => {
    const Modal = getBootstrapModal()
    if (!Modal || !document.body) {
        return Promise.resolve({ confirmed: false, value: '' })
    }

    const previouslyFocused = document.activeElement
    const modal = document.createElement('div')
    modal.className = 'modal fade'
    modal.tabIndex = -1
    modal.setAttribute('aria-labelledby', 'kmp-a11y-dialog-title')
    modal.setAttribute('aria-describedby', 'kmp-a11y-dialog-message')

    const inputMarkup = inputLabel ? `
        <div class="mb-3">
            <label class="form-label" for="kmp-a11y-dialog-input">${escapeHtml(inputLabel)}</label>
            <input id="kmp-a11y-dialog-input" class="form-control" type="text" ${inputRequired ? 'required' : ''}>
        </div>
    ` : ''
    const cancelMarkup = cancelLabel ? `<button type="button" class="btn btn-secondary" data-dialog-cancel>${escapeHtml(cancelLabel)}</button>` : ''

    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="kmp-a11y-dialog-title">${escapeHtml(title)}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="kmp-a11y-dialog-message"></p>
                    ${inputMarkup}
                </div>
                <div class="modal-footer">
                    ${cancelMarkup}
                    <button type="button" class="btn btn-primary" data-dialog-confirm>${escapeHtml(confirmLabel)}</button>
                </div>
            </div>
        </div>
    `
    modal.querySelector('#kmp-a11y-dialog-message').textContent = message
    document.body.appendChild(modal)

    return new Promise((resolve) => {
        const instance = new Modal(modal)
        const input = modal.querySelector('#kmp-a11y-dialog-input')
        let settled = false

        const settle = (confirmed) => {
            if (settled) return
            settled = true
            const value = input ? input.value : ''
            instance.hide()
            resolve({ confirmed, value })
        }

        modal.querySelector('[data-dialog-confirm]').addEventListener('click', () => {
            if (inputRequired && input && input.value.trim() === '') {
                input.setAttribute('aria-invalid', 'true')
                input.focus()
                announce(`${inputLabel} is required.`, { assertive: true })
                return
            }
            settle(true)
        })

        modal.querySelector('[data-dialog-cancel]')?.addEventListener('click', () => settle(false))
        modal.addEventListener('hidden.bs.modal', () => {
            if (!settled) {
                resolve({ confirmed: false, value: '' })
            }
            instance.dispose()
            modal.remove()
            if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
                previouslyFocused.focus()
            }
        }, { once: true })
        modal.addEventListener('shown.bs.modal', () => {
            const focusTarget = input || modal.querySelector('[data-dialog-confirm]')
            focusTarget.focus()
        }, { once: true })
        instance.show()
    })
}

const confirm = async (message, options = {}) => {
    const result = await openDialog({
        title: options.title || 'Confirm action',
        message,
        confirmLabel: options.confirmLabel || 'Confirm',
        cancelLabel: options.cancelLabel || 'Cancel',
    })
    return result.confirmed
}

const prompt = async (message, options = {}) => {
    const result = await openDialog({
        title: options.title || 'Input required',
        message,
        inputLabel: options.inputLabel || message,
        inputRequired: options.required || false,
        confirmLabel: options.confirmLabel || 'Save',
        cancelLabel: options.cancelLabel || 'Cancel',
    })
    return result.confirmed ? result.value : null
}

const alert = async (message, options = {}) => {
    await openDialog({
        title: options.title || 'Notice',
        message,
        confirmLabel: options.confirmLabel || 'OK',
        cancelLabel: null,
    })
    announce(message, { assertive: options.assertive || false })
}

const findCakePostLinkForm = (trigger) => {
    const onclick = trigger.getAttribute('onclick') || ''
    const formName = onclick.match(/document\.([A-Za-z0-9_]+)\.requestSubmit\(\)/)?.[1]
    if (!formName) {
        return null
    }

    return document.forms[formName] instanceof HTMLFormElement ? document.forms[formName] : null
}

const submitConfirmedTrigger = (trigger) => {
    const form = findCakePostLinkForm(trigger) || trigger.closest('form')
    if (form instanceof HTMLFormElement) {
        HTMLFormElement.prototype.submit.call(form)
        return
    }

    if (trigger instanceof HTMLAnchorElement && trigger.href && trigger.getAttribute('href') !== '#') {
        window.location.assign(trigger.href)
    }
}

const installCakeConfirmAdapter = () => {
    if (document.documentElement.dataset.kmpCakeConfirmAdapter === 'true') {
        return
    }
    document.documentElement.dataset.kmpCakeConfirmAdapter = 'true'

    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-confirm-message]')
        if (!trigger) {
            return
        }

        event.preventDefault()
        event.stopImmediatePropagation()

        const confirmed = await confirm(trigger.dataset.confirmMessage, {
            title: trigger.dataset.confirmTitle || 'Confirm action',
            confirmLabel: trigger.dataset.confirmLabel || 'Confirm',
        })
        if (confirmed) {
            submitConfirmedTrigger(trigger)
        }
    }, true)
}

export default {
    alert,
    announce,
    confirm,
    installCakeConfirmAdapter,
    prompt,
}
