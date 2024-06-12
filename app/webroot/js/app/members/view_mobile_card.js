class memberViewMobileCard {
    constructor() {
        this.ac = null;
    };
    updateOnlineStatus() {
        const statusDiv = document.getElementById('status');
        if (navigator.onLine) {
            statusDiv.textContent = 'Online';
            statusDiv.classList.remove('bg-danger');
            statusDiv.classList.add('bg-success');
        } else {
            statusDiv.textContent = 'Offline';
            statusDiv.classList.remove('bg-success');
            statusDiv.classList.add('bg-danger');
        }
    }
    refreshPageIfOnline() {
        if (navigator.onLine) {
            window.location.reload();
        }
    }
    run(urlsToCache, swPath) {
        var me = this;
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                me.updateOnlineStatus();
                window.addEventListener('online', me.updateOnlineStatus);
                window.addEventListener('offline', me.updateOnlineStatus);
                navigator.serviceWorker.register(swPath)
                    .then(registration => {
                        console.log('Service Worker registered with scope:', registration.scope);
                        registration.active.postMessage({ type: 'CACHE_URLS', payload: urlsToCache });
                    }, error => {
                        console.log('Service Worker registration failed:', error);
                    });
            });
        }
        setInterval(me.refreshPageIfOnline, 30000);
    }
}