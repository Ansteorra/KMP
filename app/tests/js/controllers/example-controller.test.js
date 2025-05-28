/**
 * Example test for a Stimulus controller
 * This demonstrates how to test your Stimulus controllers in isolation
 */

import { Controller } from '@hotwired/stimulus';

// Example controller for testing purposes
class ExampleController extends Controller {
  static targets = ['output'];
  static values = { message: String };

  connect() {
    this.element.setAttribute('data-connected', 'true');
  }

  greet() {
    this.outputTarget.textContent = this.messageValue || 'Hello World';
  }

  handleClick(event) {
    event.preventDefault();
    this.greet();
  }
}

describe('ExampleController', () => {
  let controller;
  let element;

  beforeEach(() => {
    // Set up DOM element with required structure
    document.body.innerHTML = `
      <div data-controller="example" 
           data-example-message-value="Test Message">
        <button data-action="click->example#handleClick">Click me</button>
        <div data-example-target="output"></div>
      </div>
    `;

    element = document.querySelector('[data-controller="example"]');
    controller = new ExampleController();
    controller.element = element;
    
    // Set up targets and values as Stimulus would
    controller.outputTarget = element.querySelector('[data-example-target="output"]');
    controller.messageValue = element.getAttribute('data-example-message-value');
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  test('should connect and set connected attribute', () => {
    controller.connect();
    expect(element.getAttribute('data-connected')).toBe('true');
  });

  test('should display message when greet is called', () => {
    controller.greet();
    expect(controller.outputTarget.textContent).toBe('Test Message');
  });

  test('should handle click events', () => {
    const button = element.querySelector('button');
    const clickEvent = new Event('click', { bubbles: true });
    
    // Spy on preventDefault
    const preventDefaultSpy = jest.spyOn(clickEvent, 'preventDefault');
    
    controller.handleClick(clickEvent);
    
    expect(preventDefaultSpy).toHaveBeenCalled();
    expect(controller.outputTarget.textContent).toBe('Test Message');
  });

  test('should use default message when no value is set', () => {
    controller.messageValue = '';
    controller.greet();
    expect(controller.outputTarget.textContent).toBe('Hello World');
  });
});
