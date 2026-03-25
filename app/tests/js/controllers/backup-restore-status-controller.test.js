import '../../../assets/js/controllers/backup-restore-status-controller.js';
const BackupRestoreStatusController = window.Controllers['backup-restore-status'];

// Mock bootstrap.Modal
const mockModalInstance = {
    show: jest.fn(),
    hide: jest.fn(),
    dispose: jest.fn()
};

describe('BackupRestoreStatusController', () => {
    let controller;

    beforeEach(() => {
        jest.clearAllMocks();
        jest.useFakeTimers();

        window.bootstrap.Modal = jest.fn().mockImplementation(() => mockModalInstance);

        document.body.innerHTML = `
            <div data-controller="backup-restore-status"
                 data-backup-restore-status-url-value="/api/restore/status"
                 data-backup-restore-status-interval-value="1000">
                <div data-backup-restore-status-target="panel" class="alert alert-secondary mb-3"></div>
                <span data-backup-restore-status-target="badge" class="badge bg-secondary">idle</span>
                <span data-backup-restore-status-target="message">No restore running.</span>
                <span data-backup-restore-status-target="details"></span>
                <div data-backup-restore-status-target="modal" class="modal">
                    <span data-backup-restore-status-target="modalBadge" class="badge"></span>
                    <span data-backup-restore-status-target="modalMessage"></span>
                    <span data-backup-restore-status-target="modalDetails"></span>
                    <div data-backup-restore-status-target="modalSpinner" class="d-none"></div>
                    <button data-backup-restore-status-target="modalClose">Close</button>
                </div>
            </div>
            <meta name="csrf-token" content="test-csrf">
        `;

        controller = new BackupRestoreStatusController();
        controller.element = document.querySelector('[data-controller="backup-restore-status"]');
        controller.urlValue = '/api/restore/status';
        controller.hasUrlValue = true;
        controller.intervalValue = 1000;
        controller.autoReloadValue = true;
        controller.terminalWindowValue = 30;

        // Wire targets
        controller.panelTarget = document.querySelector('[data-backup-restore-status-target="panel"]');
        controller.hasPanelTarget = true;
        controller.badgeTarget = document.querySelector('[data-backup-restore-status-target="badge"]');
        controller.hasBadgeTarget = true;
        controller.messageTarget = document.querySelector('[data-backup-restore-status-target="message"]');
        controller.hasMessageTarget = true;
        controller.detailsTarget = document.querySelector('[data-backup-restore-status-target="details"]');
        controller.hasDetailsTarget = true;
        controller.modalTarget = document.querySelector('[data-backup-restore-status-target="modal"]');
        controller.hasModalTarget = true;
        controller.modalBadgeTarget = document.querySelector('[data-backup-restore-status-target="modalBadge"]');
        controller.hasModalBadgeTarget = true;
        controller.modalMessageTarget = document.querySelector('[data-backup-restore-status-target="modalMessage"]');
        controller.hasModalMessageTarget = true;
        controller.modalDetailsTarget = document.querySelector('[data-backup-restore-status-target="modalDetails"]');
        controller.hasModalDetailsTarget = true;
        controller.modalSpinnerTarget = document.querySelector('[data-backup-restore-status-target="modalSpinner"]');
        controller.hasModalSpinnerTarget = true;
        controller.modalCloseTargets = Array.from(document.querySelectorAll('[data-backup-restore-status-target="modalClose"]'));
        controller.hasModalCloseTarget = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        jest.useRealTimers();
        if (global.fetch) delete global.fetch;
    });

    // --- Static properties ---

    test('has correct static values', () => {
        expect(BackupRestoreStatusController.values).toHaveProperty('url', String);
        expect(BackupRestoreStatusController.values).toHaveProperty('interval');
        expect(BackupRestoreStatusController.values).toHaveProperty('autoReload');
        expect(BackupRestoreStatusController.values).toHaveProperty('terminalWindow');
    });

    test('has correct static targets', () => {
        expect(BackupRestoreStatusController.targets).toEqual(expect.arrayContaining([
            'panel', 'badge', 'message', 'details', 'modal',
            'modalBadge', 'modalMessage', 'modalDetails', 'modalSpinner', 'modalClose'
        ]));
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['backup-restore-status']).toBe(BackupRestoreStatusController);
    });

    // --- normalizeStatus ---

    test('normalizeStatus returns idle for no status', () => {
        const result = controller.normalizeStatus({});
        expect(result.state).toBe('idle');
        expect(result.message).toBe('No restore currently running.');
    });

    test('normalizeStatus returns running state when locked', () => {
        const result = controller.normalizeStatus({ locked: true, status: 'running', phase: 'importing' });
        expect(result.locked).toBe(true);
        expect(result.badgeLabel).toBe('importing');
        expect(result.showSpinner).toBe(true);
    });

    test('normalizeStatus returns completed state with badge class', () => {
        const result = controller.normalizeStatus({
            status: 'completed',
            completed_at: new Date().toISOString(),
            message: 'Done!'
        });
        expect(result.state).toBe('completed');
        expect(result.badgeClass).toBe('bg-success');
        expect(result.panelClass).toBe('alert-success');
    });

    test('normalizeStatus returns failed state', () => {
        const result = controller.normalizeStatus({
            status: 'failed',
            completed_at: new Date().toISOString(),
            message: 'Failed!'
        });
        expect(result.state).toBe('failed');
        expect(result.badgeClass).toBe('bg-danger');
        expect(result.panelClass).toBe('alert-danger');
    });

    test('normalizeStatus includes source and table details', () => {
        const result = controller.normalizeStatus({
            status: 'running',
            locked: true,
            source: 'backup.sql',
            table_count: 10,
            tables_processed: 5,
            rows_processed: 1000,
            current_table: 'members'
        });
        expect(result.details).toContain('Source: backup.sql');
        expect(result.details).toContain('Tables: 5/10');
        expect(result.details).toContain('Current: members');
    });

    // --- isTerminalState ---

    test('isTerminalState identifies terminal states', () => {
        expect(controller.isTerminalState('completed')).toBe(true);
        expect(controller.isTerminalState('failed')).toBe(true);
        expect(controller.isTerminalState('interrupted')).toBe(true);
        expect(controller.isTerminalState('running')).toBe(false);
        expect(controller.isTerminalState('idle')).toBe(false);
    });

    // --- isRecentTerminalState ---

    test('isRecentTerminalState returns true for recent completion', () => {
        const status = { completed_at: new Date().toISOString() };
        expect(controller.isRecentTerminalState(status)).toBe(true);
    });

    test('isRecentTerminalState returns false for old completion', () => {
        const oldDate = new Date(Date.now() - 60000).toISOString();
        const status = { completed_at: oldDate };
        expect(controller.isRecentTerminalState(status)).toBe(false);
    });

    test('isRecentTerminalState returns false when no completed_at', () => {
        expect(controller.isRecentTerminalState({})).toBe(false);
    });

    // --- badgeClass / panelClass ---

    test('badgeClass returns correct classes', () => {
        expect(controller.badgeClass(true, 'running')).toBe('bg-info');
        expect(controller.badgeClass(false, 'running')).toBe('bg-info');
        expect(controller.badgeClass(false, 'completed')).toBe('bg-success');
        expect(controller.badgeClass(false, 'failed')).toBe('bg-danger');
        expect(controller.badgeClass(false, 'idle')).toBe('bg-secondary');
    });

    test('panelClass returns correct classes', () => {
        expect(controller.panelClass(true, 'running')).toBe('alert-warning');
        expect(controller.panelClass(false, 'completed')).toBe('alert-success');
        expect(controller.panelClass(false, 'failed')).toBe('alert-danger');
        expect(controller.panelClass(false, 'idle')).toBe('alert-secondary');
    });

    // --- render ---

    test('render updates DOM targets', () => {
        controller.modalInstance = mockModalInstance;
        controller.restoreRequestInFlight = false;

        controller.render({
            status: 'completed',
            completed_at: new Date().toISOString(),
            message: 'Import complete.',
            table_count: 5,
            tables_processed: 5,
            rows_processed: 100
        });

        expect(controller.badgeTarget.textContent).toBe('completed');
        expect(controller.badgeTarget.className).toContain('bg-success');
        expect(controller.messageTarget.textContent).toBe('Import complete.');
        expect(controller.panelTarget.className).toContain('alert-success');
    });

    // --- requestHeaders ---

    test('requestHeaders includes CSRF token', () => {
        const headers = controller.requestHeaders();
        expect(headers['X-CSRF-Token']).toBe('test-csrf');
        expect(headers['X-Requested-With']).toBe('XMLHttpRequest');
    });

    // --- parseJson ---

    test('parseJson returns parsed JSON', async () => {
        const response = { text: () => Promise.resolve('{"key":"val"}') };
        const result = await controller.parseJson(response);
        expect(result).toEqual({ key: 'val' });
    });

    test('parseJson returns null for empty response', async () => {
        const response = { text: () => Promise.resolve('') };
        const result = await controller.parseJson(response);
        expect(result).toBeNull();
    });

    test('parseJson returns null for invalid JSON', async () => {
        const response = { text: () => Promise.resolve('not json') };
        const result = await controller.parseJson(response);
        expect(result).toBeNull();
    });

    // --- setModalClosable ---

    test('setModalClosable enables/disables close buttons', () => {
        controller.setModalClosable(false);
        controller.modalCloseTargets.forEach(btn => {
            expect(btn.disabled).toBe(true);
        });

        controller.setModalClosable(true);
        controller.modalCloseTargets.forEach(btn => {
            expect(btn.disabled).toBe(false);
        });
    });

    // --- pollStatus ---

    test('pollStatus fetches status and renders', async () => {
        controller.statusRequestInFlight = false;
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ status: 'idle' })
        });
        const renderSpy = jest.spyOn(controller, 'render').mockImplementation(() => {});

        await controller.pollStatus();

        expect(global.fetch).toHaveBeenCalledWith('/api/restore/status', expect.any(Object));
        expect(renderSpy).toHaveBeenCalled();
    });

    test('pollStatus does nothing when no URL value', async () => {
        controller.hasUrlValue = false;
        global.fetch = jest.fn();

        await controller.pollStatus();

        expect(global.fetch).not.toHaveBeenCalled();
    });

    test('pollStatus does nothing when request already in flight', async () => {
        controller.statusRequestInFlight = true;
        global.fetch = jest.fn();

        await controller.pollStatus();

        expect(global.fetch).not.toHaveBeenCalled();
    });

    // --- startPolling / stopPolling ---

    test('startPolling creates interval timer', () => {
        controller.startPolling();
        expect(controller.timer).toBeDefined();
        controller.stopPolling();
    });

    test('stopPolling clears interval timer', () => {
        controller.timer = setInterval(() => {}, 1000);
        controller.stopPolling();
        expect(controller.timer).toBeNull();
    });

    // --- scheduleReload ---

    test('scheduleReload sets reloadScheduled flag', () => {
        controller.reloadScheduled = false;
        controller.scheduleReload();
        expect(controller.reloadScheduled).toBe(true);
    });

    test('scheduleReload does nothing when already scheduled', () => {
        controller.reloadScheduled = true;
        controller.scheduleReload();
        // Should not throw or double-schedule
    });
});
