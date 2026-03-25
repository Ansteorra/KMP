const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');

const { Given, When, Then } = createBdd();

// Award-specific steps can be added here as needed
// Most award steps use SharedSteps.js
