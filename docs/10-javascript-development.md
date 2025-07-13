---
layout: default
---
[← Back to Table of Contents](index.md)

# 10. JavaScript Development with Stimulus

## 10.1 Introduction to Stimulus

KMP uses the Stimulus JavaScript framework to enhance the user interface with dynamic behavior. Stimulus is a modest framework designed to augment your HTML with just enough JavaScript to make it interactive.

Stimulus works by automatically connecting DOM elements to JavaScript objects. When you add `data-controller` attributes to your HTML, Stimulus automatically instantiates the corresponding controller class and keeps it connected to the element.


## 10.2 Controller Organization

In KMP, all Stimulus controllers for the main application should be stored in the `app/assets/js/controllers` directory with filenames that follow the pattern `{name}-controller.js`. For example:

- `app/assets/js/controllers/nav-bar-controller.js`
- `app/assets/js/controllers/member-card-profile-controller.js`
- `app/assets/js/controllers/auto-complete-controller.js`

For plugin-specific controllers, use:
- `plugins/PluginName/assets/js/controllers/{name}-controller.js`

Each controller follows the Stimulus naming convention, where the filename corresponds to the controller identifier used in the HTML. This ensures consistency and automatic registration in the build process.

Here's an example of a typical Stimulus controller:

```javascript
// app/assets/js/controllers/example-controller.js
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
  static targets = ["output"]

  connect() {
    console.log("Controller connected to element")
  }

  greet() {
    this.outputTarget.textContent = "Hello, Stimulus!"
  }
}
```

In your HTML, you would connect this controller like this:

```html
<div data-controller="example">
  <button data-action="click->example#greet">Greet</button>
  <span data-example-target="output"></span>
</div>
```

## 10.3 Development Workflow

KMP uses Laravel Mix (based on webpack) to compile JavaScript and CSS assets. The workflow for developing JavaScript in KMP is as follows:

1. Make sure you have Node.js installed and run `npm install` in the `app` directory to install dependencies
2. Start the development server using `npm run watch`
3. Make changes to your JavaScript files in the `app/assets/js` directory
4. The watch process will automatically detect changes, recompile assets, and refresh your browser

The `npm run watch` command keeps running and watches for changes in your JavaScript and CSS files. It will automatically recompile and update your browser when you save changes to any asset file.

```bash
# Navigate to app directory
cd app

# Install dependencies (if not already installed)
npm install

# Start the watch process
npm run watch
```

## 10.4 Asset Management

KMP uses Laravel Mix and webpack to manage frontend assets. All source JavaScript and CSS files should be stored in the following directories:

- JavaScript: `app/assets/js/`
- CSS: `app/assets/css/`

These files are then compiled and output to the public directory for deployment:

- JavaScript: compiled to `app/webroot/js/`
- CSS: compiled to `app/webroot/css/`

Webpack automatically bundles your JavaScript modules and their dependencies into optimized packages. The build process is configured in `app/webpack.mix.js`.


### Adding a New Stimulus Controller

To add a new Stimulus controller:

1. For the main app, create a new file in `app/assets/js/controllers/` named `your-feature-controller.js`.
2. For a plugin, create it in `plugins/PluginName/assets/js/controllers/`.
3. Implement your controller using the Stimulus pattern.
4. The controller will be automatically included in the build process.

The webpack configuration automatically detects all files ending with `-controller.js` in the appropriate controllers directory and includes them in the build.

### Main JavaScript Entry Points

The main JavaScript entry points are:

- `app/assets/js/index.js`: The main application JavaScript
- `app/assets/js/controllers/*-controller.js`: Individual Stimulus controllers

These files are compiled into the final JavaScript bundles that are loaded by the application.

## 10.5 Examples from the Codebase

KMP includes several Stimulus controllers that serve as good examples:

- `app-setting-form-controller.js`: Manages app settings forms
- `auto-complete-controller.js`: Implements autocomplete functionality 
- `member-card-profile-controller.js`: Manages member profile cards
- `modal-opener-controller.js`: Handles modal dialogs


Examining these controllers can provide insight into how to implement common patterns in Stimulus.

For more best practices and coding standards, see the [KMP CakePHP & Stimulus.JS Best Practices](../.github/copilot-instructions.md).