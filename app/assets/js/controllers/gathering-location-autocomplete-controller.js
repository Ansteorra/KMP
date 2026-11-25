import { Controller } from '@hotwired/stimulus'

const mapsAuthFailureEvent = 'kmp:maps-auth-failure'
const placesLoadTimeout = 10_000
let placesRequest
let watchingAuthentication = false
let authenticationFailed = false

function watchMapsAuthentication() {
    if (watchingAuthentication) return

    const previous = window.gm_authFailure
    window.gm_authFailure = () => {
        authenticationFailed = true
        window.dispatchEvent(new Event(mapsAuthFailureEvent))
        previous?.call(window)
    }
    watchingAuthentication = true
}

function loadPlaces(apiKey) {
    if (authenticationFailed) return Promise.resolve(null)

    const maps = window.google?.maps
    if (maps?.places?.Autocomplete) return Promise.resolve(maps.places.Autocomplete)

    placesRequest ??= new Promise(resolve => {
        const callback = 'initGatheringLocationAutocomplete'
        let script
        let finished = false
        const timer = window.setTimeout(() => finish(), placesLoadTimeout)

        function finish(Autocomplete) {
            if (finished) return

            finished = true
            window.clearTimeout(timer)
            if (script) {
                script.onerror = null
                delete window[callback]
                if (!Autocomplete) script.remove()
            }
            resolve(Autocomplete)
        }

        if (maps?.importLibrary) {
            maps.importLibrary('places').then(library => finish(library.Autocomplete), () => finish())
            return
        }

        script = document.createElement('script')
        const params = new URLSearchParams({ key: apiKey, libraries: 'places', loading: 'async', callback })
        script.src = 'https://maps.googleapis.com/maps/api/js?' + params.toString()
        script.async = true
        script.onerror = () => finish()
        window[callback] = () => finish(window.google?.maps?.places?.Autocomplete)
        document.head.appendChild(script)
    })
    return placesRequest
}

/** Enhance the native address input with optional Google Places suggestions. */
class GatheringLocationAutocompleteController extends Controller {
    static targets = ['input', 'latitude', 'longitude', 'help']
    static values = { apiKey: String, fallbackMessage: String }

    connection = 0

    async connect() {
        const connection = ++this.connection
        this.address = this.inputTarget.value
        this.usingFallback = false

        const apiKey = this.apiKeyValue.trim()
        if (!apiKey) return

        watchMapsAuthentication()
        window.addEventListener(mapsAuthFailureEvent, this.usePlainAddress)
        try {
            const Autocomplete = await loadPlaces(apiKey)
            if (connection !== this.connection || this.usingFallback || !this.element.isConnected) return
            if (!Autocomplete || authenticationFailed) {
                this.usePlainAddress()
                return
            }
            this.enableAutocomplete(Autocomplete)
        } catch {
            if (connection === this.connection && this.element.isConnected) this.usePlainAddress()
        }
    }

    enableAutocomplete(Autocomplete) {
        const input = this.inputTarget
        this.nativeInput = input.cloneNode(false)
        this.enhancedInput = input
        const checkInput = () => {
            if (input.disabled || input.readOnly || input.classList.contains('gm-err-autocomplete')) {
                this.usePlainAddress()
            }
        }
        this.inputObserver = new MutationObserver(checkInput)
        this.inputObserver.observe(input, { attributes: true, attributeFilter: ['disabled', 'readonly', 'class'] })

        const autocomplete = new Autocomplete(input, { types: ['geocode'] })
        if (!this.usingFallback) {
            this.placeListener = autocomplete.addListener('place_changed', () => this.selectPlace(autocomplete))
            checkInput()
        }
    }

    selectPlace(autocomplete) {
        try {
            const place = autocomplete.getPlace()
            if (!place?.formatted_address) return

            const location = place.geometry?.location
            this.address = place.formatted_address
            this.inputTarget.value = this.address
            this.setCoordinates(location?.lat() ?? '', location?.lng() ?? '')
        } catch {
            this.usePlainAddress()
        }
    }

    addressChanged() {
        const address = this.inputTarget.value
        if (address === this.address) return

        this.address = address
        this.setCoordinates('', '')
    }

    setCoordinates(latitude, longitude) {
        this.latitudeTarget.value = latitude
        this.longitudeTarget.value = longitude
    }

    usePlainAddress = () => {
        if (this.usingFallback || !this.element.isConnected) return

        this.usingFallback = true
        this.restorePlainInput(true)
        this.helpTarget.textContent = this.fallbackMessageValue
    }

    restorePlainInput(preserveFocus = false) {
        this.inputObserver?.disconnect()
        this.placeListener?.remove()
        this.inputObserver = null
        this.placeListener = null

        const input = this.enhancedInput
        const nativeInput = this.nativeInput
        if (!input || !nativeInput) return

        // The widget can disable the input and replace its placeholder after an authentication error.
        const wasFocused = preserveFocus && document.activeElement === input
        const selection = [input.selectionStart, input.selectionEnd]
        nativeInput.value = this.address
        input.replaceWith(nativeInput)
        this.enhancedInput = null
        this.nativeInput = null
        if (wasFocused) {
            nativeInput.focus({ preventScroll: true })
            nativeInput.setSelectionRange(...selection)
        }
    }

    disconnect() {
        this.connection++
        window.removeEventListener(mapsAuthFailureEvent, this.usePlainAddress)
        this.restorePlainInput()
    }
}

window.Controllers = window.Controllers || {}
window.Controllers['gathering-location-autocomplete'] = GatheringLocationAutocompleteController
