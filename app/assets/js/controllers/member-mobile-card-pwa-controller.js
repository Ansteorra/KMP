import { Controller } from "@hotwired/stimulus"

class MemberMobileCardPWA extends Controller {
    static targets = ["urlCache", "status", "refreshBtn"]
    static values = {
        swUrl: String
    }

    urlCacheTargetConnected() {
        this.urlCacheValue = JSON.parse(this.urlCacheTarget.textContent);
    }

    updateOnlineStatus() {
        const statusDiv = this.statusTarget;
        const refreshButton = this.refreshBtnTarget;

        if (navigator.onLine) {
            statusDiv.textContent = 'Online';
            statusDiv.classList.remove('bg-danger');
            statusDiv.classList.add('bg-success');
            refreshButton.hidden = false;
            if (this.sw) {
                this.sw.active.postMessage({
                    type: 'ONLINE'
                });
            }
            refreshButton.click();
        } else {
            statusDiv.textContent = 'Offline';
            statusDiv.classList.remove('bg-success');
            statusDiv.classList.add('bg-danger');
            refreshButton.hidden = true;
            if (this.sw) {
                this.sw.active.postMessage({
                    type: 'OFFLINE'
                });
            }
        }
    }

    manageOnlineStatus() {
        this.updateOnlineStatus();
        window.addEventListener('online', this.updateOnlineStatus.bind(this));
        window.addEventListener('offline', this.updateOnlineStatus.bind(this));
        navigator.serviceWorker.register(this.swUrlValue)
            .then(registration => {
                registration.update();
                this.sw = registration;
                console.log('Service Worker registered with scope:', registration.scope);
                registration.active.postMessage({
                    type: 'CACHE_URLS',
                    payload: this.urlCacheValue
                });
                this.element.attributes['data-member-mobile-card-profile-pwa-ready-value'].value = true;
            }, error => {
                console.log('Service Worker registration failed:', error);
            });
    }

    refreshPageIfOnline() {
        if (navigator.onLine) {
            window.location.reload();
        }
    }

    connect() {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', this.manageOnlineStatus.bind(this));
        }
        setInterval(this.refreshPageIfOnline, 300000);
    }

    disconnect() {
        window.addEventListener('load', this.manageOnlineStatus.bind(this));
        window.removeEventListener('online', this.updateOnlineStatus.bind(this));
        window.removeEventListener('offline', this.updateOnlineStatus.bind(this));
    }

}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["member-mobile-card-pwa"] = MemberMobileCardPWA;