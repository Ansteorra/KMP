import { Controller } from "@hotwired/stimulus"

/**
 * GatheringLocationAutocompleteController
 * 
 * Provides Google Places Autocomplete for gathering location input fields
 * using the classic google.maps.places.Autocomplete API.
 * 
 * Note: We use the classic API instead of PlaceAutocompleteElement because
 * the new web component API doesn't expose selected place data programmatically.
 * The classic API provides reliable place_changed events and getPlace() method.
 * 
 * @example
 * <input type="text" 
 *        data-controller="gathering-location-autocomplete"
 *        data-gathering-location-autocomplete-api-key-value="YOUR-KEY">
 */
class GatheringLocationAutocompleteController extends Controller {
    static values = {
        apiKey: String  // Google Maps API key
    }
    
    static targets = ["input", "latitude", "longitude"]  // Input field and hidden form fields for lat/lng
    
    /**
     * Initialize the controller
     */
    initialize() {
        this.autocompleteElement = null
        this.isGoogleMapsLoaded = false
        this.isInitialized = false  // Prevent re-initialization loop
        this.lastSelectedAddress = null  // Store the selected address
        this.lastSelectedPlace = null  // Store the full place object with geometry
    }
    
    /**
     * Connect function - runs when controller connects to DOM
     */
    async connect() {
        console.log("GatheringLocationAutocompleteController connected")
        
        // Prevent re-initialization if already set up
        if (this.isInitialized) {
            console.log("Autocomplete already initialized, skipping")
            return
        }
        
        // Mark as initializing immediately to prevent race conditions
        this.isInitialized = true
        
        // Load Google Maps Places library if not already loaded
        await this.loadGoogleMapsPlaces()
        
        // Initialize autocomplete on the input field
        this.initAutocomplete()
    }
    
    /**
     * Load Google Maps Places library
     */
    async loadGoogleMapsPlaces() {
        // Check if already loaded
        if (typeof google !== 'undefined' && google.maps && google.maps.places) {
            this.isGoogleMapsLoaded = true
            return Promise.resolve()
        }
        
        return new Promise((resolve, reject) => {
            // Create script tag to load Google Maps with Places library
            const script = document.createElement('script')
            const apiKey = this.apiKeyValue || ''
            const keyParam = apiKey ? `key=${apiKey}&` : ''
            
            script.src = `https://maps.googleapis.com/maps/api/js?${keyParam}libraries=places&loading=async&callback=initGatheringLocationAutocomplete`
            script.async = true
            script.defer = true
            
            // Set up callback
            window.initGatheringLocationAutocomplete = () => {
                this.isGoogleMapsLoaded = true
                delete window.initGatheringLocationAutocomplete
                resolve()
            }
            
            script.onerror = () => {
                console.error('Failed to load Google Maps Places library')
                reject(new Error('Failed to load Google Maps Places library'))
            }
            
            document.head.appendChild(script)
        })
    }
    
    /**
     * Initialize Google Places Autocomplete using the classic Autocomplete class
     * (PlaceAutocompleteElement doesn't expose data programmatically, so we use the old API)
     */
    initAutocomplete() {
        if (!this.isGoogleMapsLoaded) {
            console.error('Google Maps not loaded')
            return
        }
        
        // Use the old Autocomplete API which actually works
        // Use 'geocode' type for addresses, or omit types to get all place types
        this.autocomplete = new google.maps.places.Autocomplete(this.inputTarget, {
            types: ['geocode']
        })
        
        // Listen for place selection
        this.autocomplete.addListener('place_changed', () => {
            const place = this.autocomplete.getPlace()
            
            if (place && place.formatted_address) {
                console.log('✓ Place selected:', place.formatted_address)
                this.lastSelectedAddress = place.formatted_address
                this.lastSelectedPlace = place
                this.inputTarget.value = place.formatted_address
                
                // Extract and store latitude/longitude if available
                if (place.geometry && place.geometry.location) {
                    const lat = place.geometry.location.lat()
                    const lng = place.geometry.location.lng()
                    
                    console.log('✓ Coordinates:', lat, lng)
                    
                    // Update hidden form fields if they exist
                    if (this.hasLatitudeTarget) {
                        this.latitudeTarget.value = lat
                        console.log('✓ Set latitude field:', lat)
                    }
                    if (this.hasLongitudeTarget) {
                        this.longitudeTarget.value = lng
                        console.log('✓ Set longitude field:', lng)
                    }
                } else {
                    console.log('⚠ No geometry data available for selected place')
                }
            }
        })
        
        console.log('Google Places Autocomplete initialized (classic API)')
    }
    
    /**
     * Cleanup when controller disconnects
     */
    disconnect() {
        // Clean up the autocomplete
        if (this.autocomplete) {
            google.maps.event.clearInstanceListeners(this.autocomplete)
        }
        
        this.autocomplete = null
        this.isInitialized = false
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["gathering-location-autocomplete"] = GatheringLocationAutocompleteController;

console.log("GatheringLocationAutocompleteController registered in window.Controllers");
