/**
 * Auto-imports all Stimulus controllers and services via Vite glob.
 *
 * Each controller self-registers on window.Controllers via its module body.
 * Eager loading ensures all side effects execute immediately.
 */

// Import all Stimulus controllers from app and plugins
import.meta.glob([
    './controllers/**/*-controller.js',
    '../../plugins/*/assets/js/controllers/**/*-controller.js',
    '../../plugins/*/Assets/js/controllers/**/*-controller.js',
], { eager: true });

// Import all service files
import.meta.glob([
    './services/**/*-service.js',
], { eager: true });
