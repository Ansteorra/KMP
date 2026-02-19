import { Controller } from "@hotwired/stimulus"

/**
 * Checks for KMP updates via GitHub API and shows a dismissible banner to admins.
 * Caches results in sessionStorage for 1 hour to avoid API spam.
 */
class VersionCheckController extends Controller {
    static values = {
        current: String,
        repo: { type: String, default: "jhandel/KMP" }
    }

    static targets = ["banner"]

    connect() {
        this.checkForUpdates()
    }

    async checkForUpdates() {
        const cached = sessionStorage.getItem('kmp-version-check')
        if (cached) {
            const data = JSON.parse(cached)
            if (Date.now() - data.timestamp < 3600000) {
                if (data.updateAvailable) {
                    this.showBanner(data.latestVersion, data.releaseUrl)
                }
                return
            }
        }

        try {
            const response = await fetch(`https://api.github.com/repos/${this.repoValue}/releases/latest`)
            if (!response.ok) return

            const release = await response.json()
            const latestVersion = release.tag_name.replace(/^v/, '')
            const currentVersion = this.currentValue.trim()

            const updateAvailable = latestVersion !== currentVersion

            sessionStorage.setItem('kmp-version-check', JSON.stringify({
                timestamp: Date.now(),
                updateAvailable,
                latestVersion,
                releaseUrl: release.html_url
            }))

            if (updateAvailable) {
                this.showBanner(latestVersion, release.html_url)
            }
        } catch (e) {
            console.debug('Version check failed:', e)
        }
    }

    showBanner(latestVersion, releaseUrl) {
        if (this.hasBannerTarget) {
            const wrapper = document.createElement('div')
            wrapper.className = 'alert alert-info alert-dismissible fade show d-flex align-items-center'
            wrapper.setAttribute('role', 'alert')

            const icon = document.createElement('i')
            icon.className = 'fa-solid fa-circle-info me-2'
            wrapper.appendChild(icon)

            const content = document.createElement('div')
            const strong = document.createElement('strong')
            strong.textContent = 'Update available: '
            content.appendChild(strong)
            content.appendChild(document.createTextNode(
                `KMP ${latestVersion} is available (you have ${this.currentValue}). Run `
            ))
            const code = document.createElement('code')
            code.textContent = 'kmp update'
            content.appendChild(code)
            content.appendChild(document.createTextNode(' on your server to upgrade. '))

            const link = document.createElement('a')
            link.href = releaseUrl
            link.target = '_blank'
            link.className = 'alert-link ms-1'
            link.textContent = 'View changelog →'
            // Only allow GitHub URLs
            if (releaseUrl && releaseUrl.startsWith('https://github.com/')) {
                content.appendChild(link)
            }
            wrapper.appendChild(content)

            const closeBtn = document.createElement('button')
            closeBtn.type = 'button'
            closeBtn.className = 'btn-close'
            closeBtn.setAttribute('data-bs-dismiss', 'alert')
            closeBtn.setAttribute('aria-label', 'Close')
            wrapper.appendChild(closeBtn)

            this.bannerTarget.innerHTML = ''
            this.bannerTarget.appendChild(wrapper)
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["version-check"] = VersionCheckController;
