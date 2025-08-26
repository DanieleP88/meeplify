// Modal Component for Meeplify
export class Modal {
    constructor(options = {}) {
        this.options = {
            className: '',
            closable: true,
            closeOnBackdrop: true,
            closeOnEscape: true,
            ...options
        };
        
        this.overlay = null;
        this.modal = null;
        this.isOpen = false;
        this.previousFocus = null;
        
        this.create();
    }

    create() {
        // Create overlay
        this.overlay = document.createElement('div');
        this.overlay.className = `modal-overlay ${this.options.className}`;
        this.overlay.setAttribute('role', 'dialog');
        this.overlay.setAttribute('aria-modal', 'true');
        this.overlay.setAttribute('aria-hidden', 'true');
        
        // Create modal container
        this.modal = document.createElement('div');
        this.modal.className = 'modal';
        
        this.overlay.appendChild(this.modal);
        
        // Bind events
        this.bindEvents();
        
        // Append to body
        document.body.appendChild(this.overlay);
    }

    bindEvents() {
        if (this.options.closeOnBackdrop) {
            this.overlay.addEventListener('click', (e) => {
                if (e.target === this.overlay) {
                    this.close();
                }
            });
        }
        
        if (this.options.closeOnEscape) {
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });
        }
    }

    setContent(content) {
        if (typeof content === 'string') {
            this.modal.innerHTML = content;
        } else if (content instanceof HTMLElement) {
            this.modal.innerHTML = '';
            this.modal.appendChild(content);
        }

        // Add close button if closable
        if (this.options.closable) {
            this.addCloseButton();
        }

        return this;
    }

    addCloseButton() {
        const existingClose = this.modal.querySelector('.modal-close');
        if (existingClose) return;

        const header = this.modal.querySelector('.modal-header');
        if (header && !header.querySelector('.modal-close')) {
            const closeBtn = document.createElement('button');
            closeBtn.className = 'modal-close';
            closeBtn.innerHTML = '×';
            closeBtn.setAttribute('aria-label', 'Close modal');
            closeBtn.addEventListener('click', () => this.close());
            header.appendChild(closeBtn);
        }
    }

    open() {
        if (this.isOpen) return this;

        // Store current focus
        this.previousFocus = document.activeElement;
        
        // Show modal
        this.overlay.style.display = 'flex';
        this.overlay.setAttribute('aria-hidden', 'false');
        
        // Force reflow and add show class
        requestAnimationFrame(() => {
            this.overlay.classList.add('show');
        });
        
        // Trap focus
        this.trapFocus();
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        this.isOpen = true;
        this.trigger('open');
        
        return this;
    }

    close() {
        if (!this.isOpen) return this;

        // Remove show class
        this.overlay.classList.remove('show');
        
        // Wait for animation and hide
        setTimeout(() => {
            this.overlay.style.display = 'none';
            this.overlay.setAttribute('aria-hidden', 'true');
        }, 250);
        
        // Restore body scroll
        document.body.style.overflow = '';
        
        // Restore focus
        if (this.previousFocus) {
            this.previousFocus.focus();
        }
        
        this.isOpen = false;
        this.trigger('close');
        
        return this;
    }

    destroy() {
        this.close();
        setTimeout(() => {
            if (this.overlay && this.overlay.parentNode) {
                this.overlay.parentNode.removeChild(this.overlay);
            }
        }, 250);
        
        return this;
    }

    trapFocus() {
        const focusableElements = this.modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (firstElement) {
            firstElement.focus();
        }

        const handleTabKey = (e) => {
            if (e.key !== 'Tab') return;

            if (e.shiftKey) {
                if (document.activeElement === firstElement) {
                    e.preventDefault();
                    lastElement.focus();
                }
            } else {
                if (document.activeElement === lastElement) {
                    e.preventDefault();
                    firstElement.focus();
                }
            }
        };

        document.addEventListener('keydown', handleTabKey);
        
        // Store handler to remove later
        this.tabHandler = handleTabKey;
    }

    trigger(event) {
        const customEvent = new CustomEvent(`modal:${event}`, {
            detail: { modal: this }
        });
        document.dispatchEvent(customEvent);
    }

    // Static methods for quick modals
    static alert(message, title = 'Alert') {
        const content = `
            <div class="modal-header">
                <h3 class="modal-title">${title}</h3>
            </div>
            <div class="modal-body">
                <p>${message}</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary modal-ok">OK</button>
            </div>
        `;

        const modal = new Modal({ closable: true });
        modal.setContent(content).open();

        const okBtn = modal.modal.querySelector('.modal-ok');
        okBtn.addEventListener('click', () => modal.close());

        return modal;
    }

    static confirm(message, title = 'Confirm') {
        return new Promise((resolve) => {
            const content = `
                <div class="modal-header">
                    <h3 class="modal-title">${title}</h3>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary modal-cancel">Cancel</button>
                    <button class="btn btn-primary modal-confirm">Confirm</button>
                </div>
            `;

            const modal = new Modal({ closable: true, closeOnBackdrop: false });
            modal.setContent(content).open();

            const confirmBtn = modal.modal.querySelector('.modal-confirm');
            const cancelBtn = modal.modal.querySelector('.modal-cancel');

            confirmBtn.addEventListener('click', () => {
                modal.close();
                resolve(true);
            });

            cancelBtn.addEventListener('click', () => {
                modal.close();
                resolve(false);
            });

            // Handle escape/backdrop clicks as cancel
            modal.overlay.addEventListener('click', (e) => {
                if (e.target === modal.overlay) {
                    modal.close();
                    resolve(false);
                }
            });
        });
    }

    static prompt(message, defaultValue = '', title = 'Input') {
        return new Promise((resolve) => {
            const content = `
                <div class="modal-header">
                    <h3 class="modal-title">${title}</h3>
                </div>
                <div class="modal-body">
                    <p class="mb-4">${message}</p>
                    <input type="text" class="input modal-input" value="${defaultValue}" />
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary modal-cancel">Cancel</button>
                    <button class="btn btn-primary modal-submit">Submit</button>
                </div>
            `;

            const modal = new Modal({ closable: true });
            modal.setContent(content).open();

            const input = modal.modal.querySelector('.modal-input');
            const submitBtn = modal.modal.querySelector('.modal-submit');
            const cancelBtn = modal.modal.querySelector('.modal-cancel');

            input.select();

            const submitValue = () => {
                const value = input.value.trim();
                modal.close();
                resolve(value || null);
            };

            submitBtn.addEventListener('click', submitValue);
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    submitValue();
                }
            });

            cancelBtn.addEventListener('click', () => {
                modal.close();
                resolve(null);
            });
        });
    }
}

// CSS for animation (if not already in main.css)
if (!document.querySelector('#modal-styles')) {
    const styles = document.createElement('style');
    styles.id = 'modal-styles';
    styles.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: scale(0.9) translateY(-20px); }
            to { transform: scale(1) translateY(0); }
        }
        
        .modal-overlay {
            animation: fadeIn 0.25s ease;
        }
        
        .modal-overlay.show .modal {
            animation: slideIn 0.25s ease;
        }
    `;
    document.head.appendChild(styles);
}