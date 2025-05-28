import { test as base, createBdd } from 'playwright-bdd';

export const test = base;
export const { Given, When, Then } = createBdd(test);

// Export test for bddgen
export { test as default };
