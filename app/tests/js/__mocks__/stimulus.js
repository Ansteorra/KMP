// Mock Stimulus framework for testing

export class Controller {
  constructor() {
    this.element = null;
    this.data = new Map();
    this.targets = new Map();
  }

  initialize() {}
  connect() {}
  disconnect() {}

  get application() {
    return {
      register: jest.fn()
    };
  }

  // Helper methods for testing
  dispatch(eventName, options = {}) {
    const event = new CustomEvent(eventName, {
      bubbles: true,
      cancelable: true,
      ...options
    });
    
    if (this.element) {
      this.element.dispatchEvent(event);
    }
    
    return event;
  }
}

export class Application {
  static start() {
    return {
      register: jest.fn()
    };
  }

  register() {}
}

// Make Controller available as default export as well
export default Controller;
