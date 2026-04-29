// Frontend toast queue: fetches /get-notifications, shows one at a time, posts impression/click/dismiss to /track-event.
class SocialProof {
    constructor(options = {}) {
        this.options = {
            endpoint: '/actions/social-proof/api/get-notifications',
            trackEndpoint: '/actions/social-proof/api/track-event',
            heartbeatEndpoint: '/actions/social-proof/api/heartbeat',
            heartbeatInterval: 30000, // 30 seconds
            csrfToken: null,
            csrfTokenName: 'CRAFT_CSRF_TOKEN',
            // Consent callback: return false to block tracking.
            // Example: onBeforeTrack: () => window.CookieConsent?.hasConsent('statistics')
            onBeforeTrack: null,
            ...options
        };

        this.queue = [];
        this.isShowing = false;
        this.shownCount = 0;
        this.container = null;
        this.settings = {};
        this.heartbeatTimer = null;
        this._csrfRefreshing = null;
    }

    init() {
        this.createContainer();
        this.fetchNotifications();
        this.startHeartbeat();
        this._bindKeyboard();

        // Pause heartbeat when tab is hidden (save battery/bandwidth)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopHeartbeat();
            } else {
                this.startHeartbeat();
            }
        });

        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            this.stopHeartbeat();
        });
    }

    createContainer() {
        this.container = document.getElementById('social-proof-container');

        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'social-proof-container';
            document.body.appendChild(this.container);
        }

        // Accessibility: screen readers announce new children politely
        this.container.setAttribute('aria-live', 'polite');
        this.container.setAttribute('aria-relevant', 'additions');
    }

    _bindKeyboard() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const visible = this.container?.querySelector('.social-proof-notification');
                if (visible) {
                    const notification = visible._spData;
                    if (notification) {
                        this.trackEvent(notification.id, 'dismiss');
                    }
                    this.hideNotification(visible, notification);
                }
            }
        });
    }

    async fetchNotifications() {
        try {
            const url = new URL(this.options.endpoint, window.location.origin);
            url.searchParams.set('url', window.location.href);

            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                this.settings = data.settings || {};
                this.queue = data.notifications || [];
                this.updateContainerPosition();
                this.processQueue();
            }
        } catch (error) {
            console.error('Social Proof: Failed to fetch notifications', error);
        }
    }

    updateContainerPosition() {
        const position = this.settings.position || 'bottom-left';
        this.container.className = `social-proof-container social-proof-${position}`;
        // Re-apply aria attributes after className replacement
        this.container.setAttribute('aria-live', 'polite');
        this.container.setAttribute('aria-relevant', 'additions');
    }

    processQueue() {
        if (this.queue.length === 0 || this.isShowing) {
            return;
        }

        const notification = this.queue.shift();
        this.showNotification(notification);
    }

    showNotification(notification) {
        this.isShowing = true;
        this.shownCount++;

        // Safety: clear any stale auto-dismiss timer
        if (this._autoDismissTimer) {
            clearTimeout(this._autoDismissTimer);
            this._autoDismissTimer = null;
        }

        // Safety: remove any lingering notification elements
        this.container.querySelectorAll('.social-proof-notification').forEach(el => {
            el.remove();
        });

        const element = this.createNotificationElement(notification);
        this.container.appendChild(element);

        // Track impression
        this.trackEvent(notification.id, 'impression');

        // Animate in
        requestAnimationFrame(() => {
            element.classList.add('social-proof-visible');
            element.classList.add(`social-proof-anim-${this.settings.animationIn || 'slideIn'}`);
        });

        // Auto-dismiss after duration
        const duration = (this.settings.displayDuration || 5) * 1000;
        this._autoDismissTimer = setTimeout(() => {
            this.hideNotification(element, notification);
        }, duration);
    }

    hideNotification(element, notification) {
        if (!element) return;

        // Prevent double-fire: if already hiding, bail out
        if (element._spIsHiding) return;
        element._spIsHiding = true;

        // Clear auto-dismiss timer
        if (this._autoDismissTimer) {
            clearTimeout(this._autoDismissTimer);
            this._autoDismissTimer = null;
        }

        element.classList.remove('social-proof-visible');
        element.classList.add(`social-proof-anim-${this.settings.animationOut || 'fadeOut'}-out`);

        // Remove after animation completes
        setTimeout(() => {
            element.remove();
            this.isShowing = false;

            // Process next notification after delay
            const delay = (this.settings.delayBetween || 10) * 1000;
            setTimeout(() => this.processQueue(), delay);
        }, 300);
    }

    createNotificationElement(notification) {
        const el = document.createElement('div');
        el.className = `social-proof-notification social-proof-type-${notification.type}`;
        el.setAttribute('role', 'status');
        el.innerHTML = this.getTemplate(notification);

        // Store notification data on the element for keyboard dismiss
        el._spData = notification;

        // Click handler for the whole notification
        el.addEventListener('click', (e) => {
            if (e.target.classList.contains('social-proof-dismiss')) {
                return;
            }

            this.trackEvent(notification.id, 'click');

            if (notification.productUrl) {
                if (notification.linkTarget === '_blank') {
                    window.open(notification.productUrl, '_blank');
                } else {
                    window.location.href = notification.productUrl;
                }
            }
        });

        // Dismiss button handler
        const dismissBtn = el.querySelector('.social-proof-dismiss');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.trackEvent(notification.id, 'dismiss');
                this.hideNotification(el, notification);
            });
        }

        return el;
    }

    getTemplate(notification) {
        const showImage = this.settings.showProductImage !== false;
        const showDismiss = this.settings.showDismissButton !== false;

        let html = '';

        switch (notification.type) {
            case 'purchase':
                html = this.getPurchaseTemplate(notification, showImage, showDismiss);
                break;

            case 'viewers':
                html = this.getViewersTemplate(notification, showDismiss);
                break;

            case 'stock':
                html = this.getStockTemplate(notification, showImage, showDismiss);
                break;

            case 'custom':
            default:
                html = this.getCustomTemplate(notification, showDismiss);
                break;
        }

        return html;
    }

    getPurchaseTemplate(notification, showImage, showDismiss) {
        const image = showImage && notification.productImage
            ? `<div class="social-proof-image"><img src="${this.escapeHtml(notification.productImage)}" alt="" loading="lazy"></div>`
            : '';

        const dismiss = showDismiss
            ? '<button class="social-proof-dismiss" aria-label="Dismiss notification">&times;</button>'
            : '';

        return `
            ${image}
            <div class="social-proof-content">
                <div class="social-proof-message">${this.escapeHtml(notification.message)}</div>
                <div class="social-proof-meta">
                    ${notification.timeAgo ? `<span class="social-proof-time">${this.escapeHtml(notification.timeAgo)}</span>` : ''}
                </div>
            </div>
            ${dismiss}
        `;
    }

    getViewersTemplate(notification, showDismiss) {
        const dismiss = showDismiss
            ? '<button class="social-proof-dismiss" aria-label="Dismiss notification">&times;</button>'
            : '';

        return `
            <div class="social-proof-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <div class="social-proof-content">
                <div class="social-proof-message">${this.escapeHtml(notification.message)}</div>
                <div class="social-proof-live-indicator">
                    <span class="social-proof-pulse"></span>
                    <span>Live</span>
                </div>
            </div>
            ${dismiss}
        `;
    }

    getStockTemplate(notification, showImage, showDismiss) {
        const image = showImage && notification.productImage
            ? `<div class="social-proof-image"><img src="${this.escapeHtml(notification.productImage)}" alt="" loading="lazy"></div>`
            : '';

        const dismiss = showDismiss
            ? '<button class="social-proof-dismiss" aria-label="Dismiss notification">&times;</button>'
            : '';

        return `
            ${image}
            <div class="social-proof-content">
                <div class="social-proof-stock-badge">Low Stock</div>
                <div class="social-proof-message">${this.escapeHtml(notification.message)}</div>
                ${notification.productName ? `<div class="social-proof-product">${this.escapeHtml(notification.productName)}</div>` : ''}
            </div>
            ${dismiss}
        `;
    }

    getCustomTemplate(notification, showDismiss) {
        const dismiss = showDismiss
            ? '<button class="social-proof-dismiss" aria-label="Dismiss notification">&times;</button>'
            : '';

        return `
            <div class="social-proof-content">
                <div class="social-proof-message">${this.escapeHtml(notification.message)}</div>
            </div>
            ${dismiss}
        `;
    }

    // Consent-gated POST with CSRF 419 retry; warns and continues on failure (don't break the page).
    async trackEvent(notificationId, eventType) {
        // Check consent before tracking
        if (typeof this.options.onBeforeTrack === 'function') {
            try {
                const allowed = this.options.onBeforeTrack(eventType);
                if (allowed === false) return;
            } catch (e) {
                // Consent check failed — don't track
                return;
            }
        }

        try {
            await this._postWithCsrfRetry(this.options.trackEndpoint, {
                notificationId: notificationId,
                eventType: eventType,
                pageUrl: window.location.href,
            });
        } catch (error) {
            // Silently fail tracking - don't impact user experience
            console.warn('Social Proof: Failed to track event', error);
        }
    }

    startHeartbeat() {
        if (this.heartbeatTimer) {
            return;
        }

        this.heartbeatTimer = setInterval(() => {
            this.sendHeartbeat();
        }, this.options.heartbeatInterval);
    }

    stopHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
    }

    async sendHeartbeat() {
        // Check consent before heartbeat tracking
        if (typeof this.options.onBeforeTrack === 'function') {
            try {
                const allowed = this.options.onBeforeTrack('heartbeat');
                if (allowed === false) return;
            } catch (e) {
                return;
            }
        }

        try {
            await this._postWithCsrfRetry(this.options.heartbeatEndpoint, {
                pageUrl: window.location.href,
            });
        } catch (error) {
            // Silently fail heartbeat
        }
    }

    async _postWithCsrfRetry(url, body, isRetry = false) {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        };

        if (this.options.csrfToken) {
            headers['X-CSRF-Token'] = this.options.csrfToken;
        }

        const response = await fetch(url, {
            method: 'POST',
            headers: headers,
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        // Handle expired CSRF token — refresh and retry once
        if (response.status === 419 && !isRetry) {
            await this._refreshCsrfToken();
            return this._postWithCsrfRetry(url, body, true);
        }

        return response;
    }

    // Deduplicates concurrent refreshes via shared promise.
    async _refreshCsrfToken() {
        // If already refreshing, wait for the in-flight request
        if (this._csrfRefreshing) {
            return this._csrfRefreshing;
        }

        this._csrfRefreshing = (async () => {
            try {
                // Craft exposes a session info endpoint that returns a fresh token
                const response = await fetch('/actions/users/session-info', {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.csrfTokenValue) {
                        this.options.csrfToken = data.csrfTokenValue;
                    }
                }
            } catch (e) {
                console.warn('Social Proof: Failed to refresh CSRF token', e);
            } finally {
                this._csrfRefreshing = null;
            }
        })();

        return this._csrfRefreshing;
    }

    escapeHtml(text) {
        if (!text) return '';

        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Auto-initialize when config is present
document.addEventListener('DOMContentLoaded', () => {
    if (window.socialProofConfig) {
        const sp = new SocialProof(window.socialProofConfig);
        sp.init();

        // Expose instance globally for debugging
        window.socialProof = sp;
    }
});

if (typeof module !== 'undefined' && module.exports) {
    module.exports = SocialProof;
}
