/**
 * Waivers Plugin JavaScript Entry Point
 * 
 * This file imports and registers all Stimulus controllers for the Waivers plugin.
 * Controllers are automatically registered with the global Stimulus application.
 */

// Import plugin styles
import '../css/waivers.css';

// Import controllers
import './controllers/retention-policy-input-controller.js';
import './controllers/waiver-template-controller.js';

// Controllers are automatically registered via their individual files
// No additional registration needed here
