import { Controller } from "@hotwired/stimulus";

/**
 * Hello World Stimulus Controller
 * 
 * This controller demonstrates the Stimulus.js pattern used in KMP plugins.
 * Stimulus controllers provide interactive behavior for frontend components
 * without requiring a full JavaScript framework.
 * 
 * Key Concepts:
 * - Targets: DOM elements the controller interacts with
 * - Values: Properties that can be set from HTML attributes
 * - Actions: Event handlers triggered by user interaction
 * - Outlets: Connections to other Stimulus controllers
 * 
 * Usage in HTML:
 * <div data-controller="hello-world"
 *      data-hello-world-message-value="Hello from Stimulus!">
 *   <input data-hello-world-target="input" type="text">
 *   <button data-action="click->hello-world#greet">Greet</button>
 *   <div data-hello-world-target="output"></div>
 * </div>
 */
class HelloWorldController extends Controller {
    // Define targets - elements this controller interacts with
    static targets = ["input", "output", "counter"]
    
    // Define values - properties that can be set from HTML data attributes
    static values = {
        message: { type: String, default: "Hello, World!" },
        count: { type: Number, default: 0 }
    }
    
    /**
     * Initialize the controller
     * Called once when the controller is first instantiated
     */
    initialize() {
        console.log("HelloWorld controller initialized");
    }
    
    /**
     * Connect the controller to the DOM
     * Called when the controller is connected to the DOM
     */
    connect() {
        console.log("HelloWorld controller connected to:", this.element);
        this.updateCounter();
    }
    
    /**
     * Disconnect the controller from the DOM
     * Called when the controller is disconnected from the DOM
     * Use for cleanup (removing event listeners, timers, etc.)
     */
    disconnect() {
        console.log("HelloWorld controller disconnected");
    }
    
    /**
     * Greet action - Display a greeting message
     * Triggered by: data-action="click->hello-world#greet"
     */
    greet(event) {
        event.preventDefault();
        
        // Get the input value if available
        const name = this.hasInputTarget ? this.inputTarget.value : "World";
        
        // Create greeting message
        const greeting = name ? `${this.messageValue}, ${name}!` : this.messageValue;
        
        // Display in output target
        if (this.hasOutputTarget) {
            this.outputTarget.textContent = greeting;
            this.outputTarget.classList.add("alert", "alert-success", "mt-3");
        }
        
        // Increment counter
        this.countValue++;
    }
    
    /**
     * Clear action - Clear the output
     * Triggered by: data-action="click->hello-world#clear"
     */
    clear(event) {
        event.preventDefault();
        
        if (this.hasInputTarget) {
            this.inputTarget.value = "";
        }
        
        if (this.hasOutputTarget) {
            this.outputTarget.textContent = "";
            this.outputTarget.className = "";
        }
    }
    
    /**
     * Value changed callback - Called when message value changes
     * Automatically called when messageValue is updated
     */
    messageValueChanged() {
        console.log("Message value changed to:", this.messageValue);
    }
    
    /**
     * Value changed callback - Called when count value changes
     * Automatically called when countValue is updated
     */
    countValueChanged() {
        this.updateCounter();
    }
    
    /**
     * Update the counter display
     */
    updateCounter() {
        if (this.hasCounterTarget) {
            this.counterTarget.textContent = this.countValue;
        }
    }
    
    /**
     * Example of a method that could be called from other controllers
     * or JavaScript code
     */
    showMessage(message) {
        if (this.hasOutputTarget) {
            this.outputTarget.textContent = message;
            this.outputTarget.classList.add("alert", "alert-info", "mt-3");
        }
    }
    
    /**
     * Example of an async method - fetch data from server
     */
    async fetchData(url) {
        try {
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            return data;
        } catch (error) {
            console.error("Error fetching data:", error);
            if (this.hasOutputTarget) {
                this.outputTarget.textContent = "Error loading data";
                this.outputTarget.classList.add("alert", "alert-danger", "mt-3");
            }
            return null;
        }
    }
}

// Register the controller globally
// This makes it available to the Stimulus application
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["hello-world"] = HelloWorldController;

export default HelloWorldController;
