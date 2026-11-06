import AttendanceDialogController from '../../../assets/js/controllers/attendance-dialog-controller.js';
import ApprovalDialogController from '../../../assets/js/controllers/approval-dialog-controller.js';
import PermissionDisclosureController from '../../../assets/js/controllers/permission-disclosure-controller.js';
import { createControllerScope } from '../../../assets/js/services/controller-scope-service.js';
import fs from 'node:fs';
import path from 'node:path';

const settle = async () => { for (let i = 0; i < 10; i++) await Promise.resolve(); };
const modalEvent = (name, button) => {
    const event = new Event(name, { bubbles: true });
    event.relatedTarget = button;
    return event;
};

describe('dialogs across document, frame, and cached visits', () => {
    let controller;
    afterEach(() => {
        controller?.disconnect(); controller = null;
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('an obsolete RSVP read cannot replace a newer form or a closed dialog', async () => {
        document.body.innerHTML = '<div id="rsvp" class="modal show"><div id="content"></div></div>';
        const reads = [];
        window.fetch = jest.fn((url, options) => new Promise(resolve => reads.push({ resolve, options })));
        controller = new AttendanceDialogController();
        controller.element = document.getElementById('rsvp');
        controller.contentTarget = document.getElementById('content');
        controller.loadingValue = 'Loading'; controller.errorValue = 'Unavailable'; controller.closeValue = 'Close';
        controller.connect();
        const button = document.createElement('button');
        button.dataset.attendanceId = '1'; button.dataset.gatheringId = '2';
        controller.element.dispatchEvent(modalEvent('show.bs.modal', button));
        button.dataset.attendanceId = '3';
        controller.element.dispatchEvent(modalEvent('show.bs.modal', button));
        await settle();
        expect(reads).toHaveLength(2);
        expect(reads[0].options.signal.aborted).toBe(true);
        reads[1].resolve({ ok: true, text: async () => '<h5 class="modal-title">Current RSVP</h5><textarea>draft</textarea>' });
        await settle();
        expect(controller.contentTarget).toHaveTextContent('Current RSVP');
        expect(document.activeElement.tagName).toBe('TEXTAREA');
        reads[0].resolve({ ok: true, text: async () => 'Obsolete RSVP' });
        await settle();
        expect(controller.contentTarget).not.toHaveTextContent('Obsolete');
        expect(controller.element).toHaveAttribute('aria-labelledby', 'rsvpLabel');
        controller.element.dispatchEvent(modalEvent('show.bs.modal', button));
        controller.element.dispatchEvent(new Event('hide.bs.modal'));
        expect(reads[2].options.signal.aborted).toBe(true);
        reads[2].resolve({ ok: true, text: async () => 'Closed dialog response' });
        await settle();
        expect(controller.contentTarget).not.toHaveTextContent('Closed dialog response');
    });

    test('reconnecting an approval dialog configures exactly once per opening and releases deep-link timers', () => {
        jest.useFakeTimers();
        try {
            document.body.innerHTML = `<div id="approvalResponseModal"><h5 id="approvalResponseModalLabel"></h5>
                <form id="approvalResponseForm"><input id="approvalResponseApprovalId"><input id="approvalResponseApprovalIds"></form></div>`;
            const configure = jest.fn();
            window.Stimulus.getControllerForElementAndIdentifier = jest.fn(() => ({ configure }));
            controller = new ApprovalDialogController();
            controller.element = document.getElementById('approvalResponseModal');
            controller.bindingsValue = { focusedApprovalId: 7 };
            const button = document.createElement('button');
            button.dataset.outletBtnBtnDataValue = '{"id":7}';
            document.body.append(button);
            const click = jest.spyOn(button, 'click');
            controller.connect(); controller.disconnect(); controller.connect();
            controller.element.dispatchEvent(modalEvent('show.bs.modal', button));
            expect(configure).toHaveBeenCalledTimes(1);
            expect(configure.mock.calls[0][0].id).toBe(7);
            controller.disconnect();
            jest.advanceTimersByTime(1001);
            expect(click).not.toHaveBeenCalled();
        } finally { jest.useRealTimers(); }
    });

    test('namespace icons reflect completed Bootstrap disclosure state after every reconnect', () => {
        document.body.innerHTML = '<div id="policies"><kmp-icon class="collapse-icon"></kmp-icon><button class="namespace-toggle" aria-expanded="false"></button></div>';
        controller = new PermissionDisclosureController(); controller.element = document.getElementById('policies');
        controller.connect(); controller.disconnect(); controller.connect();
        const button = controller.element.querySelector('button');
        button.setAttribute('aria-expanded', 'true');
        button.dispatchEvent(new Event('shown.bs.collapse', { bubbles: true }));
        expect(button.previousElementSibling).toHaveAttribute('name', 'caret-down');
        button.setAttribute('aria-expanded', 'false');
        button.dispatchEvent(new Event('hidden.bs.collapse', { bubbles: true }));
        expect(button.previousElementSibling).toHaveAttribute('name', 'caret-right');
    });

    test('disposing a scope cancels delayed work and pending fetches', () => {
        jest.useFakeTimers();
        try {
            const scope = createControllerScope();
            window.fetch = jest.fn(() => Promise.resolve({ ok: true }));
            const callback = jest.fn();
            scope.setTimeout(callback, 100);
            scope.fetch('/authorized-form');
            const signal = window.fetch.mock.calls[0][1].signal;
            scope.dispose(); jest.advanceTimersByTime(101);
            expect(signal.aborted).toBe(true);
            expect(callback).not.toHaveBeenCalled();
        } finally { jest.useRealTimers(); }
    });

    test('runtime templates cannot introduce page-load-only initialization', () => {
        const app = path.resolve(__dirname, '../../..');
        const roots = ['templates', ...['Activities', 'Officers', 'Awards', 'Waivers'].map(plugin => `plugins/${plugin}/templates`)];
        const exceptions = new Set(['templates/TableAdmin/index.php', 'templates/element/timezone_examples.php']);
        const violations = [];
        for (const root of roots) {
            const directory = path.join(app, root);
            for (const file of fs.readdirSync(directory, { recursive: true })) {
                if (!file.endsWith('.php')) continue;
                const relative = `${root}/${file}`;
                if (exceptions.has(relative)) continue;
                if (/DOMContentLoaded/.test(fs.readFileSync(path.join(app, relative), 'utf8'))) violations.push(relative);
            }
        }
        expect(violations).toEqual([]);
    });
});
