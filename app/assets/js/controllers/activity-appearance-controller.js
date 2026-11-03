import { Controller } from '@hotwired/stimulus';
import { createIcon, loadCatalog, loadIcon, resolveIcon, searchCatalog } from '../services/icon-service.js';

class ActivityAppearanceController extends Controller {
    static targets = ['foreground', 'background', 'source', 'iconName', 'search', 'icons', 'iconCount', 'more',
        'file', 'compact', 'full', 'contrast'];
    static values = { iconUrl: String };

    connect() {
        this.limit = 48;
        this.generation = 0;
        this.catalog = null;
        this.connected = true;
        this.iconCountTarget.textContent = 'Open the catalog to browse or search icons.';
        this.moreTarget.hidden = true;
        this.preview();
    }

    disconnect() {
        this.connected = false;
        this.generation += 1;
        if (this.reader?.readyState === 1) this.reader.abort();
        this.uploadUrl = null;
    }

    searchIcons() {
        this.limit = 48;
        this.renderIcons();
    }

    showMore() {
        const previousLimit = this.limit;
        this.limit += 48;
        this.renderIcons();
        this.iconsTarget.children[previousLimit]?.focus();
    }

    async openCatalog() {
        if (this.catalog) return;
        this.iconCountTarget.textContent = 'Loading icons…';
        this.iconsTarget.setAttribute('aria-busy', 'true');
        try {
            const catalog = await loadCatalog();
            if (!this.connected) return;
            this.catalog = catalog;
            await loadIcon(this.iconNameTarget.value);
            const selected = resolveIcon(this.iconNameTarget.value);
            if (selected && catalog.some(entry => entry.id === selected.id)) this.iconNameTarget.value = selected.id;
            this.renderIcons();
            this.preview();
        } catch (error) {
            if (this.connected) this.iconCountTarget.textContent = 'Icons could not be loaded. Open the catalog to try again.';
        } finally {
            if (this.connected) this.iconsTarget.removeAttribute('aria-busy');
        }
    }

    renderIcons() {
        if (!this.catalog) { this.openCatalog(); return; }
        const matches = searchCatalog(this.catalog, this.searchTarget.value);
        const selected = resolveIcon(this.iconNameTarget.value)?.id || this.iconNameTarget.value;
        const buttons = matches.slice(0, this.limit).map(entry => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-secondary';
            button.title = entry.label;
            button.setAttribute('aria-label', entry.label);
            button.setAttribute('aria-pressed', String(entry.id === selected));
            button.classList.toggle('active', entry.id === selected);
            button.append(createIcon(entry.id, { size: 24 }));
            button.dataset.iconName = entry.id;
            button.dataset.action = 'activity-appearance#chooseIcon';
            return button;
        });
        this.iconsTarget.replaceChildren(...buttons);
        this.iconCountTarget.textContent = matches.length
            ? `Showing ${buttons.length} of ${matches.length} icons.` : 'No icons match. Try another name or activity.';
        this.moreTarget.hidden = matches.length <= this.limit;
    }

    chooseIcon(event) {
        this.iconNameTarget.value = event.currentTarget.dataset.iconName;
        this.sourceTarget.value = 'catalog';
        this.sourceChanged();
    }

    sourceChanged() {
        if (this.sourceTarget.value !== 'upload') {
            this.generation += 1;
            this.fileTarget.value = '';
            this.fileTarget.setCustomValidity('');
            if (this.reader?.readyState === 1) this.reader.abort();
            this.uploadUrl = null;
        }
        this.preview();
    }

    fileChanged() {
        const generation = ++this.generation;
        if (this.reader?.readyState === 1) this.reader.abort();
        this.uploadUrl = null;
        this.fileTarget.setCustomValidity('');
        const file = this.fileTarget.files[0];
        if (!file) {
            this.preview();
            return;
        }
        if (!['image/png', 'image/webp'].includes(file.type) || file.size > 524288) {
            this.fileTarget.setCustomValidity('Choose a PNG or WebP image up to 128 × 128 pixels and 512 KB.');
            this.fileTarget.reportValidity();
            return;
        }
        const reader = this.reader = new FileReader();
        const image = new Image();
        image.onload = () => {
            if (generation !== this.generation) return;
            if (image.width > 128 || image.height > 128) {
                this.fileTarget.setCustomValidity('The icon must be no larger than 128 × 128 pixels.');
                this.fileTarget.reportValidity();
                return;
            }
            this.uploadUrl = image.src;
            this.sourceTarget.value = 'upload';
            this.preview();
        };
        reader.onerror = image.onerror = () => {
            if (generation !== this.generation) return;
            this.fileTarget.setCustomValidity('This image could not be read. Choose a valid PNG or WebP image.');
            this.fileTarget.reportValidity();
        };
        reader.onload = () => {
            if (generation === this.generation) image.src = reader.result;
        };
        reader.readAsDataURL(file);
    }

    contrastRatio(first, second) {
        const luminance = color => {
            const channels = color.slice(1).match(/../g).map(channel => {
                const value = parseInt(channel, 16) / 255;
                return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
            });
            return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
        };
        const one = luminance(first);
        const two = luminance(second);
        return (Math.max(one, two) + 0.05) / (Math.min(one, two) + 0.05);
    }

    preview() {
        const ratio = this.contrastRatio(this.foregroundTarget.value, this.backgroundTarget.value);
        this.contrastTarget.textContent = `Contrast ${ratio.toFixed(2)}:1. Activity labels need at least 4.5:1.`;
        this.foregroundTarget.setCustomValidity(ratio >= 4.5 ? '' : 'Choose colors with at least 4.5:1 contrast.');
        const known = Boolean(resolveIcon(this.iconNameTarget.value)) || this.catalog?.some(entry => entry.id === this.iconNameTarget.value);
        // Saved uncommon IDs resolve asynchronously; the server remains the authority for validation.
        if (!known && this.iconNameTarget.value) loadIcon(this.iconNameTarget.value);
        this.iconNameTarget.setCustomValidity('');
        this.iconsTarget.querySelectorAll('button').forEach(button => {
            const selected = button.dataset.iconName === this.iconNameTarget.value;
            button.setAttribute('aria-pressed', String(selected));
            button.classList.toggle('active', selected);
        });
        for (const [target, compact] of [[this.compactTarget, true], [this.fullTarget, false]]) {
            target.style.color = this.foregroundTarget.value;
            target.style.backgroundColor = this.backgroundTarget.value;
            const url = this.sourceTarget.value === 'upload' && (this.uploadUrl || this.iconUrlValue);
            let icon;
            if (url) {
                icon = document.createElement(compact ? 'span' : 'img');
                icon.className = compact ? 'gathering-activity-mask' : 'gathering-activity-image';
                if (compact) {
                    icon.style.maskImage = `url("${url}")`;
                    icon.style.webkitMaskImage = `url("${url}")`;
                } else {
                    icon.src = url;
                    icon.alt = '';
                }
            } else {
                icon = createIcon(this.iconNameTarget.value || 'activity', { size: compact ? 16 : 24 });
            }
            icon.setAttribute('aria-hidden', 'true');
            target.replaceChildren(icon, ...(compact ? [] : [document.createTextNode(' Activity')]));
            target.setAttribute('aria-label', compact ? 'Compact activity icon preview' : 'Full color activity preview');
            target.setAttribute('role', 'img');
        }
    }
}

window.Controllers = window.Controllers || {};
window.Controllers['activity-appearance'] = ActivityAppearanceController;
