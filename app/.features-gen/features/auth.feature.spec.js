// Generated from: tests/ui/bdd/features/auth.feature
import { test } from "playwright-bdd";

test.describe('User Authentication', () => {

  test.beforeEach('Background', async ({ Given, page }) => {
    await Given('I am on the login page', null, { page }); 
  });
  
  test('Display login form when not authenticated', async ({ When, page, Then, And }) => { 
    await When('I navigate to a protected route "/roles"', null, { page }); 
    await Then('I should see the login form', null, { page }); 
    await And('I should see the email address field', null, { page }); 
    await And('I should see the password field', null, { page }); 
  });

  test('Show validation errors for empty form', async ({ When, page, Then }) => { 
    await When('I submit the login form without entering credentials', null, { page }); 
    await Then('I should see validation error messages', null, { page }); 
  });

  test('Show error for invalid credentials', async ({ When, page, And, Then }) => { 
    await When('I enter invalid credentials', {"dataTable":{"rows":[{"cells":[{"value":"email"},{"value":"invalid@example.com"}]},{"cells":[{"value":"password"},{"value":"wrongpassword"}]}]}}, { page }); 
    await And('I submit the login form', null, { page }); 
    await Then('I should see an authentication error message', null, { page }); 
  });

  test('Successfully log in with valid credentials', async ({ When, page, And, Then }) => { 
    await When('I enter valid admin credentials', {"dataTable":{"rows":[{"cells":[{"value":"email"},{"value":"admin@test.com"}]},{"cells":[{"value":"password"},{"value":"Password123"}]}]}}, { page }); 
    await And('I submit the login form', null, { page }); 
    await Then('I should be successfully logged in', null, { page }); 
    await And('I should see the welcome message "Welcome Admin von Admin!"', null, { page }); 
  });

  test('When I am logged in I can log out', async ({ Given, page, When, Then }) => { 
    await Given('I am logged in as "admin@test.com"', null, { page }); 
    await When('I logout', null, { page }); 
    await Then('I should see the login form', null, { page }); 
  });

});

// == technical section ==

test.use({
  $test: ({}, use) => use(test),
  $uri: ({}, use) => use('tests/ui/bdd/features/auth.feature'),
  $bddFileData: ({}, use) => use(bddFileData),
});

const bddFileData = [ // bdd-data-start
  {"pwTestLine":10,"pickleLine":9,"tags":[],"steps":[{"pwStepLine":7,"gherkinStepLine":7,"keywordType":"Context","textWithKeyword":"Given I am on the login page","isBg":true,"stepMatchArguments":[]},{"pwStepLine":11,"gherkinStepLine":10,"keywordType":"Action","textWithKeyword":"When I navigate to a protected route \"/roles\"","stepMatchArguments":[{"group":{"start":32,"value":"\"/roles\"","children":[{"start":33,"value":"/roles","children":[{"children":[]}]},{"children":[{"children":[]}]}]},"parameterTypeName":"string"}]},{"pwStepLine":12,"gherkinStepLine":11,"keywordType":"Outcome","textWithKeyword":"Then I should see the login form","stepMatchArguments":[]},{"pwStepLine":13,"gherkinStepLine":12,"keywordType":"Outcome","textWithKeyword":"And I should see the email address field","stepMatchArguments":[]},{"pwStepLine":14,"gherkinStepLine":13,"keywordType":"Outcome","textWithKeyword":"And I should see the password field","stepMatchArguments":[]}]},
  {"pwTestLine":17,"pickleLine":15,"tags":[],"steps":[{"pwStepLine":7,"gherkinStepLine":7,"keywordType":"Context","textWithKeyword":"Given I am on the login page","isBg":true,"stepMatchArguments":[]},{"pwStepLine":18,"gherkinStepLine":16,"keywordType":"Action","textWithKeyword":"When I submit the login form without entering credentials","stepMatchArguments":[]},{"pwStepLine":19,"gherkinStepLine":17,"keywordType":"Outcome","textWithKeyword":"Then I should see validation error messages","stepMatchArguments":[]}]},
  {"pwTestLine":22,"pickleLine":19,"tags":[],"steps":[{"pwStepLine":7,"gherkinStepLine":7,"keywordType":"Context","textWithKeyword":"Given I am on the login page","isBg":true,"stepMatchArguments":[]},{"pwStepLine":23,"gherkinStepLine":20,"keywordType":"Action","textWithKeyword":"When I enter invalid credentials","stepMatchArguments":[]},{"pwStepLine":24,"gherkinStepLine":23,"keywordType":"Action","textWithKeyword":"And I submit the login form","stepMatchArguments":[]},{"pwStepLine":25,"gherkinStepLine":24,"keywordType":"Outcome","textWithKeyword":"Then I should see an authentication error message","stepMatchArguments":[]}]},
  {"pwTestLine":28,"pickleLine":26,"tags":[],"steps":[{"pwStepLine":7,"gherkinStepLine":7,"keywordType":"Context","textWithKeyword":"Given I am on the login page","isBg":true,"stepMatchArguments":[]},{"pwStepLine":29,"gherkinStepLine":27,"keywordType":"Action","textWithKeyword":"When I enter valid admin credentials","stepMatchArguments":[]},{"pwStepLine":30,"gherkinStepLine":30,"keywordType":"Action","textWithKeyword":"And I submit the login form","stepMatchArguments":[]},{"pwStepLine":31,"gherkinStepLine":31,"keywordType":"Outcome","textWithKeyword":"Then I should be successfully logged in","stepMatchArguments":[]},{"pwStepLine":32,"gherkinStepLine":32,"keywordType":"Outcome","textWithKeyword":"And I should see the welcome message \"Welcome Admin von Admin!\"","stepMatchArguments":[{"group":{"start":33,"value":"\"Welcome Admin von Admin!\"","children":[{"start":34,"value":"Welcome Admin von Admin!","children":[{"children":[]}]},{"children":[{"children":[]}]}]},"parameterTypeName":"string"}]}]},
  {"pwTestLine":35,"pickleLine":34,"tags":[],"steps":[{"pwStepLine":7,"gherkinStepLine":7,"keywordType":"Context","textWithKeyword":"Given I am on the login page","isBg":true,"stepMatchArguments":[]},{"pwStepLine":36,"gherkinStepLine":35,"keywordType":"Context","textWithKeyword":"Given I am logged in as \"admin@test.com\"","stepMatchArguments":[{"group":{"start":18,"value":"\"admin@test.com\"","children":[{"start":19,"value":"admin@test.com","children":[{"children":[]}]},{"children":[{"children":[]}]}]},"parameterTypeName":"string"}]},{"pwStepLine":37,"gherkinStepLine":36,"keywordType":"Action","textWithKeyword":"When I logout","stepMatchArguments":[]},{"pwStepLine":38,"gherkinStepLine":37,"keywordType":"Outcome","textWithKeyword":"Then I should see the login form","stepMatchArguments":[]}]},
]; // bdd-data-end