// Toast Notification Component for Meeplify
export class Toast {
    constructor() {
        this.container = null;
        this.toasts = [];
        this.maxToasts = 5;
        this.createContainer();
    }

    createContainer() {
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        this.container.setAttribute('role', 'region');
        this.container.setAttribute('aria-label', 'Notifications');
        document.body.appendChild(this.container);
    }

    show(message, type = 'info', duration = 4000) {
        if (typeof message === 'object') {
            const { title, text } = message;
            return this.showComplex(title, text, type, duration);
        }

        const toast = this.createToast({
            message,
            type,
            duration,
            id: Date.now().toString()
        });

        this.addToast(toast);
        return this.createPromise(toast);
    }

    showComplex(title, message, type = 'info', duration = 4000) {
        const toast = this.createToast({
            title,
            message,
            type,
            duration,
            id: Date.now().toString()
        });

        this.addToast(toast);
        return this.createPromise(toast);
    }

    createToast({ title, message, type, duration, id }) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'polite');
        toast.dataset.id = id;

        const iconMap = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ⓘ'
        };

        const icon = iconMap[type] || iconMap.info;

        const html = title ? `
            <div class="toast-icon">${icon}</div>
            <div class="toast-content">
                <div class="toast-title">${this.escapeHtml(title)}</div>
                <div class="toast-message">${this.escapeHtml(message)}</div>
            </div>
            <button class="toast-close" aria-label="Close notification">×</button>
        ` : `
            <div class="toast-icon">${icon}</div>
            <div class="toast-content">
                <div class="toast-message">${this.escapeHtml(message)}</div>
            </div>
            <button class="toast-close" aria-label="Close notification">×</button>
        `;

        toast.innerHTML = html;

        // Add close button functionality
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => this.remove(id));

        // Auto-remove after duration
        if (duration > 0) {
            setTimeout(() => this.remove(id), duration);
        }

        return {
            element: toast,
            id,
            type,
            promise: null
        };
    }

    createPromise(toast) {
        return new Promise((resolve, reject) => {
            toast.promise = { resolve, reject };
            
            // Add action buttons if needed
            const closeBtn = toast.element.querySelector('.toast-close');
            closeBtn.addEventListener('click', () => {
                resolve('dismissed');
            });

            // Auto-resolve on timeout
            const duration = parseInt(toast.element.style.getPropertyValue('--duration')) || 4000;
            if (duration > 0) {
                setTimeout(() => resolve('timeout'), duration);
            }
        });
    }

    addToast(toast) {
        // Remove excess toasts
        while (this.toasts.length >= this.maxToasts) {
            const oldestToast = this.toasts.shift();
            this.removeElement(oldestToast.element);
        }

        this.toasts.push(toast);
        this.container.appendChild(toast.element);

        // Trigger show animation
        requestAnimationFrame(() => {
            toast.element.classList.add('show');
        });
    }

    remove(id) {
        const toastIndex = this.toasts.findIndex(t => t.id === id);
        if (toastIndex === -1) return;

        const toast = this.toasts[toastIndex];
        this.toasts.splice(toastIndex, 1);
        
        // Resolve promise if exists
        if (toast.promise) {
            toast.promise.resolve('removed');
        }

        this.removeElement(toast.element);
    }

    removeElement(element) {
        element.classList.remove('show');
        
        // Wait for animation to complete
        setTimeout(() => {
            if (element.parentNode) {
                element.parentNode.removeChild(element);
            }
        }, 300);
    }

    clear() {
        this.toasts.forEach(toast => {
            if (toast.promise) {
                toast.promise.resolve('cleared');
            }
            this.removeElement(toast.element);
        });
        this.toasts = [];
    }

    // Utility methods
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Convenience methods
    success(message, duration = 3000) {
        return this.show(message, 'success', duration);
    }

    error(message, duration = 5000) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration = 4000) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration = 4000) {
        return this.show(message, 'info', duration);
    }

    loading(message, duration = 0) {
        return this.show(message, 'info', duration);
    }
}