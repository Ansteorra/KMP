import { createIcon, setIcon } from '../services/icon-service.js';
import vault from '../services/offline-vault-service.js';
import MobileControllerBase from "./mobile-controller-base";
import rsvpCacheService from "../services/rsvp-cache-service.js";

/**
 * My RSVPs Controller
 * 
 * Handles RSVP editing using the attendance modal on the My RSVPs page.
 */
class MyRsvpsController extends MobileControllerBase {
    static targets = ["modal", "modalBody", "actionButtons", "upcomingList", "pastList", "pastEmptyState", "upcomingCount", "pastCount", "status"];
    static values = { dataUrl: String };

    onConnect() {
        this.onAttendanceChanged = event => {
            if (event.detail.topic === 'attendance' && event.detail.context === this.element) {
                event.detail.waitUntil(this.refreshAttendance(event.detail.source));
            }
        };
        window.addEventListener('kmp:changed', this.onAttendanceChanged);
        this.onSavedOpen = () => this.loadRsvps();
        window.addEventListener('kmp:offline-unlocked', this.onSavedOpen);
        this.onSavedClear = () => { this.upcomingListTarget.replaceChildren(); this.pastListTarget.replaceChildren(); };
        window.addEventListener('kmp:offline-revoked', this.onSavedClear);
        this.loadRsvps();
        this.modal = null;
        this.currentGatheringId = null;
        
        // Initialize RSVP cache service
        rsvpCacheService.init().catch(err => {
            console.warn('[MyRsvps] Failed to init RSVP cache:', err);
        });
        
        // Filter out past events on the client side (safety net for timezone edge cases)
        this.filterPastEvents();
        
        // Update button states based on online status
        this.updateOnlineButtons();
        
        // Set up modal event listener
        if (this.hasModalTarget) {
            this.onHidden = () => this.onModalHidden();
            this.modalTarget.addEventListener('hidden.bs.modal', this.onHidden);
        }
    }

    async loadRsvps() {
        if (!this.hasDataUrlValue) return false;
        if (this.rsvpLoadPromise) return this.rsvpLoadPromise;
        this.rsvpLoadPromise = this.fetchRsvps();
        try { return await this.rsvpLoadPromise; }
        finally { this.rsvpLoadPromise = null; }
    }

    async fetchRsvps() {
        this.loadingRsvps = true;
        const generation = vault.generation;
        try {
            const response = await this.fetchWithRetry(this.dataUrlValue);
            const body = await response.json();
            if (generation !== vault.generation) return;
            if (!body.success || !body.data) throw new Error('Unable to load your RSVPs.');
            this.upcomingListTarget.replaceChildren(...body.data.upcoming.map(row => this.rsvpCard(row, false)));
            this.pastListTarget.replaceChildren(...body.data.past.map(row => this.rsvpCard(row, true)));
            for (const [target, title, message] of [
                [this.upcomingListTarget, 'No Upcoming RSVPs', "You haven't RSVPed to any upcoming gatherings yet."],
                [this.pastListTarget, 'No Past RSVPs', 'No past gatherings in the last 90 days.']
            ]) {
                if (!target.childElementCount) {
                    const card = document.createElement('div'); card.className = 'card empty-state-card';
                    const body = document.createElement('div'); body.className = 'card-body text-center py-4';
                    const heading = document.createElement('h3'); heading.className = 'h5'; heading.textContent = title;
                    const text = document.createElement('p'); text.className = 'text-muted'; text.textContent = message;
                    body.append(heading, text); card.append(body); target.append(card);
                }
            }
            this.updateBadgeCounts();
            this.updateOnlineButtons();
            if (this.hasStatusTarget) this.statusTarget.textContent = response.headers?.get('X-KMP-Offline-Saved') ? 'Showing your last saved update.' : '';
            return true;
        } catch (error) {
            if (this.hasStatusTarget) this.statusTarget.textContent = 'Connect and sign in to load your RSVPs.';
            return false;
        } finally { this.loadingRsvps = false; }
    }

    /** One event-card renderer for both the live API and the saved response. */
    rsvpCard(row, past) {
        const event = row.gathering;
        const add = (parent, tag, cls, text) => {
            const element = document.createElement(tag); element.className = cls; element.textContent = text;
            parent.append(element); return element;
        };
        const card = document.createElement('article');
        card.className = `mobile-event-card ${past ? 'past' : 'attending'} ${event.is_cancelled ? 'cancelled' : ''}`;
        card.dataset.endDate = event.end_date;
        if (event.is_cancelled) add(card, 'div', 'mobile-event-cancelled-banner', 'CANCELLED');
        const header = add(card, 'div', 'mobile-event-header', '');
        const info = add(header, 'div', 'mobile-event-info', '');
        if (event.type) {
            const badge = add(info, 'span', 'mobile-event-type-badge mb-1', event.type.name);
            const color = /^#[a-f0-9]{6}$/i.test(event.type.color || '') ? event.type.color : '#6c757d';
            const rgb = color.slice(1).match(/../g).map(hex => parseInt(hex, 16) / 255)
                .map(value => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
            badge.style.backgroundColor = color;
            badge.style.color = rgb[0] * 0.2126 + rgb[1] * 0.7152 + rgb[2] * 0.0722 > 0.179 ? '#000' : '#fff';
        }
        const name = add(info, 'a', 'mobile-event-name text-decoration-none online-only-btn', event.name);
        name.href = `/gatherings/view/${encodeURIComponent(event.public_id)}`;
        const date = new Date(event.start_date + 'T00:00:00');
        add(info, 'div', 'mobile-event-meta', `${date.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', ...(past ? { year: 'numeric' } : {}) })} ${past ? '' : event.start_time || ''}`);
        add(info, 'div', 'mobile-event-meta', [event.branch, event.location].filter(Boolean).join(' · '));
        const sharing = [row.sharing?.kingdom && 'Kingdom', row.sharing?.hosting_group && 'hosting group', row.sharing?.crown && 'Crown'].filter(Boolean);
        if (sharing.length) add(info, 'div', 'mobile-event-meta', `Shared: ${sharing.join(', ')}`);
        if (row.note) add(info, 'p', 'small mb-1', row.note);
        if (row.pending_id) {
            add(info, 'p', 'small mb-1', 'Waiting to send — saved on this device.');
            const cancel = add(info, 'button', 'btn btn-outline-secondary btn-sm', 'Cancel waiting RSVP');
            cancel.type = 'button'; cancel.dataset.requestId = row.pending_id; cancel.dataset.action = 'my-rsvps#cancelPending';
        } else if (!past && !event.is_cancelled) {
            const actions = add(info, 'div', 'mobile-event-actions-row mt-2', '');
            if (event.public_page_enabled) {
                const details = add(actions, 'a', 'btn btn-sm btn-outline-secondary online-only-btn', 'Details');
                details.href = `/gatherings/public-landing/${encodeURIComponent(event.public_id)}?from=mobile`;
            }
            const edit = add(actions, 'button', 'btn btn-sm btn-outline-success online-only-btn', 'Edit');
            edit.type = 'button'; edit.dataset.action = 'my-rsvps#editRsvp';
            edit.dataset.gatheringId = event.id; edit.dataset.attendanceId = row.attendance_id;
        }
        if (!event.is_cancelled && !row.pending_id) {
            const mark = createIcon(past ? 'history' : 'circle-check', { class: past ? 'text-muted' : 'text-success' });
            header.append(mark);
            mark.setAttribute('aria-hidden', 'true');
        }
        return card;
    }

    async cancelPending(event) {
        await rsvpCacheService.removePendingRsvp(event.currentTarget.dataset.requestId);
        await this.loadRsvps();
        window.KMP_accessibility.announce('Waiting RSVP cancelled.');
    }

    /**
     * Filter out events that have ended based on client's local date.
     * Moves them to the past tab and updates badge counts.
     */
    filterPastEvents() {
        if (!this.hasUpcomingListTarget) return;
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const todayStr = today.toISOString().split('T')[0]; // YYYY-MM-DD
        
        const cards = this.upcomingListTarget.querySelectorAll('.mobile-event-card[data-end-date]');
        let movedCount = 0;
        
        cards.forEach(card => {
            const endDate = card.dataset.endDate;
            if (endDate && endDate < todayStr) {
                // This event has ended - move it to past tab
                movedCount++;
                console.log(`[MyRsvps] Moving past event to Past tab (end: ${endDate}, today: ${todayStr})`);
                
                // Transform the card for past display
                this.transformCardForPast(card);
                
                // Move to past list
                if (this.hasPastListTarget) {
                    // Remove empty state if present
                    if (this.hasPastEmptyStateTarget) {
                        this.pastEmptyStateTarget.remove();
                    }
                    // Insert at beginning of past list (most recent first)
                    this.pastListTarget.insertBefore(card, this.pastListTarget.firstChild);
                } else {
                    // No past list target, just hide it
                    card.style.display = 'none';
                }
            }
        });
        
        if (movedCount > 0) {
            console.log(`[MyRsvps] Moved ${movedCount} events from Upcoming to Past`);
            
            // Update badge counts
            this.updateBadgeCounts();
            
            // Check if all upcoming events are now gone
            const remainingUpcoming = this.upcomingListTarget.querySelectorAll('.mobile-event-card');
            if (remainingUpcoming.length === 0) {
                // Show empty state message
                this.upcomingListTarget.innerHTML = `
                    <div class="card empty-state-card">
                        <div class="card-body text-center py-5">
                            <kmp-icon name="calendar-check" class="d-block fs-1 mb-3" style="color: var(--section-rsvps);" aria-hidden="true"></kmp-icon>
                            <h3 class="h5 mb-2">No Upcoming RSVPs</h3>
                            <p class="text-muted mb-4">
                                You haven't RSVPed to any upcoming gatherings yet.
                            </p>
                            <a href="/gatherings/mobile-calendar" class="btn btn-primary online-only-btn">
                                <kmp-icon name="calendar" class="me-2" aria-hidden="true"></kmp-icon>Browse Calendar
                            </a>
                        </div>
                    </div>
                `;
            }
        }
    }

    /**
     * Transform a card from upcoming style to past style
     */
    transformCardForPast(card) {
        // Remove 'attending' class and add 'past' class
        card.classList.remove('attending');
        card.classList.add('past');
        
        // Change the success check icon to muted
        const checkIcon = card.querySelector('kmp-icon[name="circle-check"].text-success');
        if (checkIcon) {
            setIcon(checkIcon, 'history');
            checkIcon.classList.remove('text-success');
            checkIcon.classList.add('text-muted');
        }
        
        // Remove action buttons row
        const actionsRow = card.querySelector('.mobile-event-actions-row');
        if (actionsRow) {
            actionsRow.remove();
        }
    }

    /**
     * Update the badge counts for both tabs
     */
    updateBadgeCounts() {
        // Count visible upcoming cards
        if (this.hasUpcomingCountTarget && this.hasUpcomingListTarget) {
            const upcomingCards = this.upcomingListTarget.querySelectorAll('.mobile-event-card:not(.past)');
            const count = upcomingCards.length;
            this.upcomingCountTarget.textContent = count;
            this.upcomingCountTarget.hidden = count === 0;
        }
        
        // Count past cards
        if (this.hasPastCountTarget && this.hasPastListTarget) {
            const pastCards = this.pastListTarget.querySelectorAll('.mobile-event-card');
            const count = pastCards.length;
            this.pastCountTarget.textContent = count;
            this.pastCountTarget.hidden = count === 0;
        }
    }

    /**
     * Called when connection state changes
     */
    onConnectionStateChanged(isOnline) {
        this.updateOnlineButtons();
        if (isOnline) this.loadRsvps();
    }

    /**
     * Update online-only buttons based on connection state
     */
    updateOnlineButtons() {
        const buttons = this.element.querySelectorAll('.online-only-btn, #past-tab');
        buttons.forEach(btn => {
            if (this.online) {
                btn.classList.remove('disabled');
                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
                btn.removeAttribute('aria-disabled');
                if (btn.tagName === 'BUTTON') btn.disabled = false;
                btn.removeAttribute('tabindex');
            } else {
                btn.classList.add('disabled');
                btn.style.opacity = '0.5';
                btn.style.pointerEvents = 'none';
                btn.setAttribute('aria-disabled', 'true');
                if (btn.tagName === 'BUTTON') btn.disabled = true;
                btn.tabIndex = -1;
            }
        });
    }

    /**
     * Edit RSVP - load attendance modal
     */
    async editRsvp(event) {
        // Don't allow editing when offline
        if (!this.online) {
            window.KMP_accessibility.announce('You need to be online to edit RSVPs.', { assertive: true });
            return;
        }
        
        const button = event.currentTarget;
        const gatheringId = button.dataset.gatheringId;
        const attendanceId = button.dataset.attendanceId;
        
        this.currentGatheringId = gatheringId;
        this.editTrigger = button;
        
        // Show modal with loading state
        this.modalBodyTarget.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        
        // Initialize and show modal
        if (!this.modal) {
            this.modal = new bootstrap.Modal(this.modalTarget);
        }
        this.modal.show();
        
        try {
            // Load attendance modal content
            const url = `/gatherings/attendance-modal/${gatheringId}?attendance_id=${attendanceId}`;
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                throw new Error(`Failed to load: ${response.status}`);
            }
            
            const html = await response.text();
            this.modalBodyTarget.innerHTML = html;
            
            // The shared turbo-modal controller owns submission and validation.
            
        } catch (error) {
            console.error('Error loading RSVP form:', error);
            this.modalBodyTarget.innerHTML = `
                <div class="alert alert-danger">
                    <kmp-icon name="alert-triangle" class="me-2" aria-hidden="true"></kmp-icon>
                    Failed to load RSVP form. Please try again.
                </div>
            `;
        }
    }

    /** Refresh live lists/counts and the offline projection after a committed change. */
    async refreshAttendance(form) {
        const data = new FormData(form);
        const gatheringId = data.get('gathering_id');
        const consent = name => data.getAll(name).at(-1) === '1';
        const deleted = new URL(form.action).pathname.includes('/delete/');
        if (gatheringId) {
            try {
                if (deleted) await rsvpCacheService.removeCachedRsvp(gatheringId);
                else await rsvpCacheService.updateCachedRsvp(gatheringId, {
                    share_with_kingdom: consent('share_with_kingdom'),
                    share_with_hosting_group: consent('share_with_hosting_group'),
                    share_with_crown: consent('share_with_crown'),
                    public_note: data.get('public_note') || '',
                });
            } catch (error) {
                console.warn('Unable to update the saved RSVP projection:', error);
            }
        }
        // A read started before the write cannot serve as its refresh.
        if (this.rsvpLoadPromise) await this.rsvpLoadPromise;
        if (!await this.loadRsvps()) throw new Error('Unable to refresh your RSVPs.');
        const target = [...this.element.querySelectorAll('[data-attendance-id]')]
            .find(button => button.dataset.gatheringId === String(gatheringId))
            || this.upcomingListTarget.querySelector('button, a') || document.getElementById('upcoming-pane');
        target?.focus({ preventScroll: true });
    }

    /**
     * Handle modal hidden event
     */
    onModalHidden() {
        // Reset modal content
        if (this.hasModalBodyTarget) {
            this.modalBodyTarget.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
        }
        this.currentGatheringId = null;
    }

    onDisconnect() {
        window.removeEventListener('kmp:changed', this.onAttendanceChanged);
        window.removeEventListener('kmp:offline-unlocked', this.onSavedOpen);
        if (this.hasModalTarget && this.onHidden) this.modalTarget.removeEventListener('hidden.bs.modal', this.onHidden);
        window.removeEventListener('kmp:offline-revoked', this.onSavedClear);
        if (this.modal) {
            this.modal.dispose();
            this.modal = null;
        }
    }
}

// Register controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["my-rsvps"] = MyRsvpsController;
