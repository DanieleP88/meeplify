// Login View for Meeplify
import { Toast } from '../components/Toast.js';

export class LoginView {
    constructor(api, state) {
        this.api = api;
        this.state = state;
        this.toast = new Toast();
        this.element = null;
    }

    show() {
        this.render();
        this.bindEvents();
    }

    hide() {
        if (this.element) {
            this.element.style.display = 'none';
        }
    }

    render() {
        const app = document.getElementById('app');
        
        const html = `
            <div class="login-container">
                <div class="login-card">
                    <div class="login-logo">Meeplify</div>
                    <p class="login-subtitle">Organizza le tue attività con semplicità</p>
                    
                    <button id="google-login-btn" class="google-btn">
                        <svg class="google-icon" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Accedi con Google
                    </button>

                    <div id="login-loading" class="hidden" style="margin-top: 1rem; text-align: center;">
                        <div class="text-sm text-gray-500">Reindirizzamento in corso...</div>
                    </div>
                </div>
            </div>
        `;

        app.innerHTML = html;
        this.element = app.firstElementChild;
    }

    bindEvents() {
        const loginBtn = document.getElementById('google-login-btn');
        const loadingEl = document.getElementById('login-loading');

        loginBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            
            try {
                // Show loading state
                loginBtn.disabled = true;
                loginBtn.innerHTML = `
                    <svg class="google-icon animate-spin" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25"/>
                        <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                    </svg>
                    Connecting...
                `;
                loadingEl.classList.remove('hidden');

                // Get Google Auth URL
                const response = await this.api.getGoogleAuthUrl();
                
                if (response.success && response.data.url) {
                    // Redirect to Google OAuth
                    window.location.href = response.data.url;
                } else {
                    throw new Error('Failed to get authentication URL');
                }

            } catch (error) {
                console.error('Login error:', error);
                this.toast.show('Login failed. Please try again.', 'error');
                
                // Reset button state
                loginBtn.disabled = false;
                loginBtn.innerHTML = `
                    <svg class="google-icon" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Accedi con Google
                `;
                loadingEl.classList.add('hidden');
            }
        });

        // Handle keyboard accessibility
        loginBtn.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                loginBtn.click();
            }
        });
    }
}