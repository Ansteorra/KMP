import { Controller } from '@hotwired/stimulus';
import { createControllerScope } from '../services/controller-scope-service.js';

/** Bootstrap owns disclosure state; synchronize its decorative namespace icons. */
class PermissionDisclosureController extends Controller {
    connect() {
        this.lifecycle = createControllerScope();
        const sync = () => this.element.querySelectorAll('.namespace-toggle').forEach(button => {
            const icon = button.previousElementSibling;
            if (icon?.classList.contains('collapse-icon')) {
                icon.setAttribute('name', button.getAttribute('aria-expanded') === 'true' ? 'caret-down' : 'caret-right');
            }
        });
        this.lifecycle.listen(this.element, 'shown.bs.collapse', sync);
        this.lifecycle.listen(this.element, 'hidden.bs.collapse', sync);
        sync();
    }

    disconnect() {
        this.lifecycle.dispose();
    }
}
window.Controllers ??= {};
window.Controllers['permission-disclosure'] = PermissionDisclosureController;
export default PermissionDisclosureController;
