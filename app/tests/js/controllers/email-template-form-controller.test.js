import '../../../assets/js/controllers/email-template-form-controller.js';

const EmailTemplateFormController = window.Controllers['email-template-form'];

describe('EmailTemplateFormController', () => {
    let controller;

    const mailersData = [
        {
            class: 'App\\Mailer\\KMPMailer',
            shortName: 'KMPMailer',
            methods: [
                { name: 'welcome', availableVars: ['userName', 'siteName'], defaultSubject: 'Welcome!' },
                { name: 'resetPassword', availableVars: ['resetLink'], defaultSubject: 'Reset Password' }
            ]
        },
        {
            class: 'Awards\\Mailer\\AwardsMailer',
            shortName: 'AwardsMailer',
            methods: [
                { name: 'notify', availableVars: ['awardName'], defaultSubject: 'Award Notification' }
            ]
        }
    ];

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="email-template-form">
                <select data-email-template-form-target="mailerSelect">
                    <option value="">-- Select Mailer --</option>
                    <option value="App\\Mailer\\KMPMailer">KMPMailer</option>
                    <option value="Awards\\Mailer\\AwardsMailer">AwardsMailer</option>
                </select>
                <select data-email-template-form-target="actionSelect">
                    <option value="">-- Select Action --</option>
                </select>
                <input type="text" data-email-template-form-target="availableVars" value="">
                <input type="text" data-email-template-form-target="subjectTemplate" value="">
            </div>
        `;

        controller = new EmailTemplateFormController();
        controller.element = document.querySelector('[data-controller="email-template-form"]');
        controller.mailerSelectTarget = document.querySelector('[data-email-template-form-target="mailerSelect"]');
        controller.actionSelectTarget = document.querySelector('[data-email-template-form-target="actionSelect"]');
        controller.availableVarsTarget = document.querySelector('[data-email-template-form-target="availableVars"]');
        controller.subjectTemplateTarget = document.querySelector('[data-email-template-form-target="subjectTemplate"]');
        controller.hasAvailableVarsTarget = true;
        controller.hasSubjectTemplateTarget = true;
        controller.mailersValue = mailersData;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['email-template-form']).toBe(EmailTemplateFormController);
    });

    test('has correct static targets', () => {
        expect(EmailTemplateFormController.targets).toEqual(
            expect.arrayContaining(['mailerSelect', 'actionSelect', 'availableVars', 'subjectTemplate'])
        );
    });

    test('has correct static values', () => {
        expect(EmailTemplateFormController.values).toHaveProperty('mailers');
    });

    test('mailerChanged populates action methods for selected mailer', () => {
        controller.mailerSelectTarget.value = 'App\\Mailer\\KMPMailer';
        controller.mailerChanged();

        const options = controller.actionSelectTarget.querySelectorAll('option');
        expect(options.length).toBe(3); // default + 2 methods
        expect(options[1].value).toBe('welcome');
        expect(options[1].textContent).toBe('welcome');
        expect(options[2].value).toBe('resetPassword');
    });

    test('mailerChanged stores vars and subject in option dataset', () => {
        controller.mailerSelectTarget.value = 'App\\Mailer\\KMPMailer';
        controller.mailerChanged();

        const welcomeOption = controller.actionSelectTarget.querySelector('option[value="welcome"]');
        expect(JSON.parse(welcomeOption.dataset.vars)).toEqual(['userName', 'siteName']);
        expect(welcomeOption.dataset.subject).toBe('Welcome!');
    });

    test('mailerChanged clears actions when no mailer selected', () => {
        // First populate
        controller.mailerSelectTarget.value = 'App\\Mailer\\KMPMailer';
        controller.mailerChanged();

        // Then clear
        controller.mailerSelectTarget.value = '';
        controller.mailerChanged();

        const options = controller.actionSelectTarget.querySelectorAll('option');
        expect(options.length).toBe(1); // Only default
    });

    test('mailerChanged handles unknown mailer gracefully', () => {
        controller.mailerSelectTarget.value = 'Unknown\\Mailer';
        controller.mailerChanged();

        const options = controller.actionSelectTarget.querySelectorAll('option');
        expect(options.length).toBe(1); // Only default
    });

    test('actionChanged updates available vars and subject from selected option', () => {
        // Set up options first
        controller.mailerSelectTarget.value = 'App\\Mailer\\KMPMailer';
        controller.mailerChanged();

        // Select the welcome action
        controller.actionSelectTarget.value = 'welcome';
        controller.actionChanged();

        expect(controller.availableVarsTarget.value).toBe(JSON.stringify(['userName', 'siteName']));
        expect(controller.subjectTemplateTarget.value).toBe('Welcome!');
    });

    test('actionChanged clears values when no option selected', () => {
        controller.availableVarsTarget.value = 'old';
        controller.subjectTemplateTarget.value = 'old';

        // selectedOptions[0] will be the default "-- Select Action --"
        controller.actionSelectTarget.value = '';
        controller.actionChanged();

        expect(controller.availableVarsTarget.value).toBe('');
        expect(controller.subjectTemplateTarget.value).toBe('');
    });

    test('actionChanged works without availableVars target', () => {
        controller.hasAvailableVarsTarget = false;
        controller.mailerSelectTarget.value = 'App\\Mailer\\KMPMailer';
        controller.mailerChanged();
        controller.actionSelectTarget.value = 'welcome';

        expect(() => controller.actionChanged()).not.toThrow();
    });

    test('actionChanged works without subjectTemplate target', () => {
        controller.hasSubjectTemplateTarget = false;
        controller.mailerSelectTarget.value = 'App\\Mailer\\KMPMailer';
        controller.mailerChanged();
        controller.actionSelectTarget.value = 'welcome';

        expect(() => controller.actionChanged()).not.toThrow();
    });
});
