import { Controller } from "@hotwired/stimulus"

/**
 * GatheringMapController
 * 
 * Handles interactive map display for gathering locations using Google Maps.
 * Displays the location on a map and provides options to open in external mapping services
 * for directions and navigation.
 * 
 * @example
 * <div data-controller="gathering-map" 
 *      data-gathering-map-location-value="123 Main St, City, State"
 *      data-gathering-map-gathering-name-value="Great Western War">
 *   <div data-gathering-map-target="map"></div>
 * </div>
 */
class GatheringMapController extends Controller {
    // Define targets - elements this controller interacts with
    static targets = ["map", "error"]
    
    // Define values - properties that can be set from HTML
    static values = {
        location: String,        // The address/location string
        gatheringName: String,   // Name of the gathering
        apiKey: String,          // Google Maps API key (optional)
        zoom: {                  // Default zoom level
            type: Number, 
            default: 15
        }
    }
    
    /**
     * Initialize the controller
     */
    initialize() {
        this.map = null
        this.marker = null
        this.geocoded = false
    }
    
    /**
     * Connect function - runs when controller connects to DOM
     */
    connect() {
        console.log("GatheringMapController connected")
        
        if (!this.locationValue) {
            this.showError("No location provided")
            return
        }
        
        // Initialize Google Maps
        this.initGoogleMap()
    }
    
    /**
     * Initialize Google Maps
     */
    async initGoogleMap() {
        try {
            // Check if Google Maps API is loaded
            if (typeof google === 'undefined' || !google.maps) {
                // Load Google Maps API dynamically if not present
                await this.loadGoogleMapsScript()
            }
            
            // Initialize the map with required configuration for AdvancedMarkerElement
            const mapOptions = {
                zoom: this.zoomValue,
                center: { lat: 0, lng: 0 }, // Will be updated after geocoding
                mapId: 'GATHERING_MAP', // Required for AdvancedMarkerElement
                mapTypeId: 'roadmap'
            }
            
            this.map = new google.maps.Map(this.mapTarget, mapOptions)
            
            // Geocode the location and add marker
            await this.geocodeAndDisplayGoogle()
            
        } catch (error) {
            console.error("Error initializing Google Maps:", error)
            this.showError("Failed to load map. Please try again later.")
        }
    }
    
    /**
     * Load Google Maps Script dynamically with marker library
     */
    loadGoogleMapsScript() {
        return new Promise((resolve, reject) => {
            if (typeof google !== 'undefined' && google.maps) {
                resolve()
                return
            }
            
            const script = document.createElement('script')
            const apiKey = this.apiKeyValue || ''
            const keyParam = apiKey ? `key=${apiKey}&` : ''
            // Load with marker library for AdvancedMarkerElement and loading=async for performance
            script.src = `https://maps.googleapis.com/maps/api/js?${keyParam}libraries=marker&loading=async&callback=initGoogleMapsCallback`
            script.async = true
            script.defer = true
            
            window.initGoogleMapsCallback = () => {
                delete window.initGoogleMapsCallback
                resolve()
            }
            
            script.onerror = () => reject(new Error('Failed to load Google Maps script'))
            document.head.appendChild(script)
        })
    }
    
    /**
     * Geocode location and display on Google Maps with AdvancedMarkerElement
     */
    async geocodeAndDisplayGoogle() {
        const geocoder = new google.maps.Geocoder()
        
        geocoder.geocode({ address: this.locationValue }, async (results, status) => {
            if (status === 'OK' && results[0]) {
                const location = results[0].geometry.location
                
                // Center map on location
                this.map.setCenter(location)
                
                // Import the AdvancedMarkerElement library
                try {
                    const { AdvancedMarkerElement } = await google.maps.importLibrary("marker")
                    
                    // Create marker using AdvancedMarkerElement
                    this.marker = new AdvancedMarkerElement({
                        map: this.map,
                        position: location,
                        title: this.gatheringNameValue || 'Gathering Location'
                    })
                    
                    // Add info window
                    const infoWindow = new google.maps.InfoWindow({
                        content: `
                            <div style="padding: 8px;">
                                <strong>${this.gatheringNameValue || 'Gathering Location'}</strong><br>
                                <span style="color: #666;">${this.locationValue}</span>
                            </div>
                        `
                    })
                    
                    // Add click listener to marker
                    this.marker.addListener('click', () => {
                        infoWindow.open({
                            anchor: this.marker,
                            map: this.map
                        })
                    })
                    
                    // Open info window by default
                    infoWindow.open({
                        anchor: this.marker,
                        map: this.map
                    })
                    
                    this.geocoded = true
                } catch (error) {
                    console.error('Error loading AdvancedMarkerElement:', error)
                    this.showError('Failed to display marker on map')
                }
            } else {
                console.error('Geocode was not successful:', status)
                this.showError(`Unable to find location: ${this.locationValue}`)
            }
        })
    }
    
    /**
     * Open location in Google Maps (new window/tab)
     */
    openInGoogleMaps(event) {
        event.preventDefault()
        const url = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(this.locationValue)}`
        window.open(url, '_blank')
    }
    
    /**
     * Open location in Apple Maps (works on supported devices)
     */
    openInAppleMaps(event) {
        event.preventDefault()
        const url = `https://maps.apple.com/?q=${encodeURIComponent(this.locationValue)}`
        window.open(url, '_blank')
    }
    
    /**
     * Get directions to location in Google Maps
     */
    getDirections(event) {
        event.preventDefault()
        const url = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(this.locationValue)}`
        window.open(url, '_blank')
    }
    
    /**
     * Show error message
     */
    showError(message) {
        if (this.hasErrorTarget) {
            this.errorTarget.textContent = message
            this.errorTarget.style.display = 'block'
        } else {
            console.error(message)
            // Create error display if target doesn't exist
            const errorDiv = document.createElement('div')
            errorDiv.className = 'alert alert-warning'
            errorDiv.innerHTML = `<i class="bi bi-exclamation-triangle"></i> ${message}`
            this.mapTarget.parentNode.insertBefore(errorDiv, this.mapTarget)
        }
        
        // Hide map container if there's an error
        if (this.hasMapTarget) {
            this.mapTarget.style.display = 'none'
        }
    }
    
    /**
     * Cleanup when controller disconnects
     */
    disconnect() {
        if (this.map) {
            // Clean up map resources
            this.map = null
            this.marker = null
        }
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["gathering-map"] = GatheringMapController;
