// Meeplify - Perfect SPA Application
class MeeplifyApp {
    constructor() {
        this.currentUser = null;
        this.currentView = null;
        this.isAuthenticated = false;
        
        // Initialize app
        this.initializeApp();
    }

    async initializeApp() {
        console.log('🚀 Initializing Meeplify...');
        
        // Show loading
        this.showLoading('Inizializzazione...');
        
        try {
            // Check authentication status
            await this.checkAuthentication();
            
            // Setup routing
            this.setupRouting();
            
            // Handle initial route
            this.handleInitialRoute();
            
        } catch (error) {
            console.error('❌ App initialization failed:', error);
            this.showError('Errore di inizializzazione');
        }
    }

    async checkAuthentication() {
        console.log('🔍 Checking authentication...');
        
        try {
            const response = await fetch('/api/auth/me', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();
            
            if (data.success && data.data) {
                this.currentUser = data.data;
                this.isAuthenticated = true;
                console.log('✅ User authenticated:', this.currentUser.name);
                return true;
            } else {
                this.currentUser = null;
                this.isAuthenticated = false;
                console.log('❌ User not authenticated');
                return false;
            }
        } catch (error) {
            console.error('❌ Authentication check failed:', error);
            this.currentUser = null;
            this.isAuthenticated = false;
            return false;
        }
    }

    setupRouting() {
        // Handle hash changes
        window.addEventListener('hashchange', () => this.handleRouteChange());
        
        // Handle browser back/forward
        window.addEventListener('popstate', () => this.handleRouteChange());
    }

    handleInitialRoute() {
        const hash = window.location.hash.slice(1) || '';
        console.log('🎯 Initial route:', hash);
        
        if (this.isAuthenticated) {
            if (!hash || hash === 'login' || hash === 'home') {
                this.navigateTo('dashboard');
            } else {
                this.handleRouteChange();
            }
        } else {
            if (hash === 'login') {
                this.navigateTo('login');
            } else {
                this.navigateTo('home');
            }
        }
    }

    handleRouteChange() {
        const hash = window.location.hash.slice(1) || '';
        const [route, ...params] = hash.split('/');
        
        console.log('📍 Route change:', route, params);

        // Protected routes
        const protectedRoutes = ['dashboard', 'checklist'];
        if (protectedRoutes.includes(route) && !this.isAuthenticated) {
            this.navigateTo('login');
            return;
        }

        // Public routes
        if ((route === 'login' || route === 'home') && this.isAuthenticated) {
            this.navigateTo('dashboard');
            return;
        }

        // Handle routes
        switch (route) {
            case 'home':
                this.showHomepageView();
                break;
            case 'login':
                this.showLoginView();
                break;
            case 'dashboard':
                this.showDashboardView();
                break;
            case 'checklist':
                const checklistId = params[0];
                if (checklistId) {
                    this.showChecklistView(checklistId);
                } else {
                    this.navigateTo('dashboard');
                }
                break;
            default:
                this.navigateTo(this.isAuthenticated ? 'dashboard' : 'home');
        }
    }

    navigateTo(route) {
        console.log('🧭 Navigating to:', route);
        window.location.hash = '#' + route;
    }

    showHomepageView() {
        console.log('🏠 Showing homepage view');
        this.hideLoading();
        
        const html = `
            <div class="homepage">
                <!-- Header -->
                <header class="homepage-header">
                    <nav class="homepage-nav">
                        <a href="#home" class="homepage-logo">
                            📋 Meeplify
                        </a>
                        <button id="header-login-btn" class="homepage-auth-btn">
                            Accedi
                        </button>
                    </nav>
                </header>

                <!-- Hero Section -->
                <section class="hero">
                    <div class="animate-fade-in-up">
                        <h1 class="hero-title">
                            Le tue checklist, organizzate alla perfezione
                        </h1>
                        <p class="hero-subtitle">
                            Collabora con il tuo team, gestisci progetti complessi e non dimenticare mai più un dettaglio importante con Meeplify.
                        </p>
                        <div class="hero-cta">
                            <button id="hero-cta-btn" class="cta-primary">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                </svg>
                                Inizia gratis con Google
                            </button>
                            <p class="cta-secondary">
                                Gratuito • Nessuna carta di credito necessaria
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Features Section -->
                <section class="features">
                    <div class="features-container">
                        <div class="features-header animate-fade-in-up animate-delay-1">
                            <h2 class="features-title">
                                Tutto quello che ti serve per organizzare meglio
                            </h2>
                            <p class="features-subtitle">
                                Strumenti potenti e semplici per gestire le tue attività quotidiane
                            </p>
                        </div>
                        
                        <div class="features-grid">
                            <div class="feature-card animate-fade-in-up animate-delay-1">
                                <div class="feature-icon">👥</div>
                                <h3 class="feature-title">Collaborazione in tempo reale</h3>
                                <p class="feature-description">
                                    Invita il tuo team e lavora insieme sulle stesse checklist. Tutti vedono gli aggiornamenti in tempo reale.
                                </p>
                            </div>
                            
                            <div class="feature-card animate-fade-in-up animate-delay-2">
                                <div class="feature-icon">🏷️</div>
                                <h3 class="feature-title">Tags personalizzabili</h3>
                                <p class="feature-description">
                                    Organizza le tue attività con tag colorati e emoji. Filtra e trova quello che cerchi in un attimo.
                                </p>
                            </div>
                            
                            <div class="feature-card animate-fade-in-up animate-delay-3">
                                <div class="feature-icon">📱</div>
                                <h3 class="feature-title">Sempre sincronizzato</h3>
                                <p class="feature-description">
                                    Accedi alle tue checklist da qualsiasi dispositivo. Web, mobile, tablet - tutto sempre aggiornato.
                                </p>
                            </div>
                            
                            <div class="feature-card animate-fade-in-up animate-delay-1">
                                <div class="feature-icon">🎯</div>
                                <h3 class="feature-title">Focus sui risultati</h3>
                                <p class="feature-description">
                                    Monitora i progressi con grafici chiari e celebra ogni obiettivo raggiunto dal tuo team.
                                </p>
                            </div>
                            
                            <div class="feature-card animate-fade-in-up animate-delay-2">
                                <div class="feature-icon">⚡</div>
                                <h3 class="feature-title">Veloce e intuitivo</h3>
                                <p class="feature-description">
                                    Interface pulita e reattiva. Meno clic, più lavoro fatto. Tutto è dove te lo aspetti.
                                </p>
                            </div>
                            
                            <div class="feature-card animate-fade-in-up animate-delay-3">
                                <div class="feature-icon">🔒</div>
                                <h3 class="feature-title">Sicuro e affidabile</h3>
                                <p class="feature-description">
                                    I tuoi dati sono protetti e sempre disponibili. Backup automatici e sicurezza enterprise-grade.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- CTA Section -->
                <section class="cta-section">
                    <div class="cta-container animate-fade-in-up">
                        <h2 class="cta-title">
                            Pronto a organizzare meglio il tuo lavoro?
                        </h2>
                        <p class="cta-subtitle">
                            Unisciti a migliaia di team che hanno già scelto Meeplify per i loro progetti
                        </p>
                        <button id="cta-login-btn" class="cta-primary">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                            </svg>
                            Accedi con Google
                        </button>
                    </div>
                </section>

                <!-- Footer -->
                <footer class="homepage-footer">
                    <div class="footer-container">
                        <p class="footer-text">
                            © 2024 Meeplify. Organizza le tue attività con semplicità.
                        </p>
                        <div class="footer-links">
                            <a href="#" class="footer-link">Privacy</a>
                            <a href="#" class="footer-link">Termini di servizio</a>
                            <a href="#" class="footer-link">Supporto</a>
                        </div>
                    </div>
                </footer>
            </div>
        `;

        this.renderContent(html);
        this.bindHomepageEvents();
    }

    bindHomepageEvents() {
        // Header login button
        document.getElementById('header-login-btn')?.addEventListener('click', () => {
            this.navigateTo('login');
        });

        // Hero CTA button
        document.getElementById('hero-cta-btn')?.addEventListener('click', async () => {
            await this.handleGoogleLogin(document.getElementById('hero-cta-btn'));
        });

        // CTA section login button
        document.getElementById('cta-login-btn')?.addEventListener('click', async () => {
            await this.handleGoogleLogin(document.getElementById('cta-login-btn'));
        });

        // Smooth scroll for anchors
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                if (href !== '#home' && href !== '#') {
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }
            });
        });

        // Add intersection observer for animations
        this.setupScrollAnimations();
    }

    async handleGoogleLogin(button) {
        if (!button) return;

        const originalContent = button.innerHTML;
        button.disabled = true;
        button.innerHTML = `
            <div class="button-loading">
                <div class="button-spinner"></div>
                Connessione...
            </div>
        `;

        try {
            // Get Google Auth URL
            const response = await fetch('/api/auth/google', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            // Check if response is OK
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('❌ Non-JSON response:', text);
                throw new Error('Server configuration error. Check .env file.');
            }
            
            const data = await response.json();
            
            if (data.success && data.data && data.data.url) {
                console.log('🔗 Redirecting to Google OAuth');
                window.location.href = data.data.url;
            } else if (data.errors && data.errors.length > 0) {
                throw new Error(data.errors[0]);
            } else {
                throw new Error('Failed to get auth URL');
            }

        } catch (error) {
            console.error('❌ Login error:', error);
            
            let errorMessage = 'Errore durante il login. ';
            if (error.message.includes('500')) {
                errorMessage += 'Controlla la configurazione del server. Visita /test_auth.php per diagnosticare il problema.';
            } else if (error.message.includes('configuration')) {
                errorMessage += 'Configura Google OAuth nel file .env';
            } else {
                errorMessage += 'Riprova.';
            }
            
            this.showToast(errorMessage, 'error');
            
            button.disabled = false;
            button.innerHTML = originalContent;
        }
    }

    setupScrollAnimations() {
        // Only run animations if user prefers them
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all animated elements
        document.querySelectorAll('.animate-fade-in-up').forEach(el => {
            // Set initial state
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
            
            observer.observe(el);
        });
    }

    showLoginView() {
        console.log('📝 Showing login view');
        this.hideLoading();
        
        const html = `
            <div class="login-container">
                <div class="login-card">
                    <a href="#home" class="login-back">← Torna alla home</a>
                    <div class="login-logo">Meeplify</div>
                    <p class="login-subtitle">Organizza le tue attività con semplicità</p>
                    
                    <button id="google-login-btn" class="google-btn">
                        <svg class="google-icon" viewBox="0 0 24 24" width="20" height="20">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Accedi con Google
                    </button>
                    
                    <div id="login-message" class="login-message hidden"></div>
                </div>
            </div>
        `;

        this.renderContent(html);
        this.bindLoginEvents();
    }

    bindLoginEvents() {
        const loginBtn = document.getElementById('google-login-btn');
        const messageEl = document.getElementById('login-message');

        loginBtn?.addEventListener('click', async (e) => {
            e.preventDefault();
            
            loginBtn.disabled = true;
            loginBtn.innerHTML = '⏳ Connessione...';
            
            try {
                // Get Google Auth URL
                const response = await fetch('/api/auth/google', {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                // Check if response is OK
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('❌ Non-JSON response:', text);
                    throw new Error('Server configuration error');
                }
                
                const data = await response.json();
                
                if (data.success && data.data && data.data.url) {
                    console.log('🔗 Redirecting to Google OAuth');
                    window.location.href = data.data.url;
                } else if (data.errors && data.errors.length > 0) {
                    throw new Error(data.errors[0]);
                } else {
                    throw new Error('Failed to get auth URL');
                }

            } catch (error) {
                console.error('❌ Login error:', error);
                
                let errorMessage = 'Errore durante il login. ';
                if (error.message.includes('500')) {
                    errorMessage += 'Controlla la configurazione del server.';
                } else if (error.message.includes('configuration')) {
                    errorMessage += 'Configura Google OAuth nel file .env';
                } else {
                    errorMessage += 'Riprova.';
                }
                
                this.showMessage(messageEl, errorMessage, 'error');
                
                loginBtn.disabled = false;
                loginBtn.innerHTML = `
                    <svg class="google-icon" viewBox="0 0 24 24" width="20" height="20">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Accedi con Google
                `;
            }
        });
    }

    async showDashboardView() {
        console.log('🏠 Showing dashboard view');
        this.hideLoading();

        const html = `
            <div class="app-layout">
                <aside class="sidebar">
                    <div class="sidebar-header">
                        <h1 class="logo">Meeplify</h1>
                    </div>
                    <nav>
                        <ul class="nav-list">
                            <li class="nav-item">
                                <a href="#dashboard" class="nav-link active">
                                    <span class="nav-icon">📋</span>
                                    Le mie liste
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link" data-section="shared">
                                    <span class="nav-icon">👥</span>
                                    Condivise con me
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link" data-section="templates">
                                    <span class="nav-icon">📚</span>
                                    Modelli
                                </a>
                            </li>
                        </ul>
                    </nav>
                </aside>
                
                <main class="main-content">
                    <header class="header">
                        <h2 class="header-title">Le mie liste</h2>
                        <div class="header-actions">
                            <button class="btn btn-primary" id="create-checklist-btn">
                                + Nuova Lista
                            </button>
                            <div class="user-menu" id="user-menu">
                                <div class="user-avatar">${(this.currentUser?.name || 'U').charAt(0).toUpperCase()}</div>
                                <span class="user-name">${this.currentUser?.name || 'Utente'}</span>
                            </div>
                        </div>
                    </header>
                    
                    <div class="content-area">
                        <div id="dashboard-content">
                            <div class="loading-state" id="content-loading">
                                <div class="loading-spinner"></div>
                                <div class="loading-text">Caricamento liste...</div>
                            </div>
                            
                            <div class="empty-state hidden" id="empty-state">
                                <div class="empty-state-icon">📝</div>
                                <h3 class="empty-state-title">Nessuna lista ancora</h3>
                                <p class="empty-state-message">Crea la tua prima checklist per iniziare a organizzare le tue attività</p>
                                <button class="btn btn-primary" id="create-first-checklist">Crea la prima lista</button>
                            </div>
                            
                            <div class="checklist-grid hidden" id="checklist-grid">
                                <!-- Checklist cards will be inserted here -->
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        `;

        this.renderContent(html);
        this.bindDashboardEvents();
        this.loadChecklists();
    }

    bindDashboardEvents() {
        // Create checklist buttons
        document.getElementById('create-checklist-btn')?.addEventListener('click', () => {
            this.showCreateChecklistModal();
        });
        
        document.getElementById('create-first-checklist')?.addEventListener('click', () => {
            this.showCreateChecklistModal();
        });

        // User menu
        document.getElementById('user-menu')?.addEventListener('click', () => {
            this.showUserMenu();
        });

        // Checklist clicks
        document.getElementById('checklist-grid')?.addEventListener('click', (e) => {
            const card = e.target.closest('.checklist-card');
            if (card) {
                const id = card.dataset.id;
                this.navigateTo(`checklist/${id}`);
            }
        });
    }

    async loadChecklists() {
        console.log('📦 Loading checklists...');
        
        try {
            const response = await fetch('/api/checklists', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                this.renderChecklists(data.data.checklists || []);
            } else {
                throw new Error(data.errors?.[0] || 'Failed to load checklists');
            }

        } catch (error) {
            console.error('❌ Failed to load checklists:', error);
            this.showError('Errore nel caricamento delle liste');
        }
    }

    renderChecklists(checklists) {
        const grid = document.getElementById('checklist-grid');
        const emptyState = document.getElementById('empty-state');
        const loading = document.getElementById('content-loading');

        loading?.classList.add('hidden');

        if (!checklists || checklists.length === 0) {
            grid?.classList.add('hidden');
            emptyState?.classList.remove('hidden');
            return;
        }

        emptyState?.classList.add('hidden');
        grid?.classList.remove('hidden');

        const cardsHtml = checklists.map(checklist => {
            const progress = checklist.progress || 0;
            const itemCount = checklist.item_count || 0;
            const completedCount = checklist.completed_count || 0;
            
            return `
                <div class="checklist-card" data-id="${checklist.id}">
                    <h3 class="checklist-title">${this.escapeHtml(checklist.title)}</h3>
                    
                    ${checklist.description ? `
                        <p class="checklist-description">${this.escapeHtml(checklist.description)}</p>
                    ` : ''}
                    
                    <div class="checklist-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${progress}%"></div>
                        </div>
                        <div class="progress-text">${completedCount}/${itemCount} completati</div>
                    </div>
                    
                    <div class="checklist-footer">
                        <div class="updated-time">${this.formatDate(checklist.updated_at || checklist.created_at)}</div>
                    </div>
                </div>
            `;
        }).join('');

        grid.innerHTML = cardsHtml;
    }

    async showCreateChecklistModal() {
        const modal = this.createModal('Nuova Checklist', `
            <div class="form-group">
                <label class="label">Titolo *</label>
                <input type="text" id="checklist-title" class="input" placeholder="Nome della checklist" required>
            </div>
            <div class="form-group">
                <label class="label">Descrizione (opzionale)</label>
                <textarea id="checklist-description" class="input textarea" placeholder="Descrizione della checklist"></textarea>
            </div>
        `, [
            { text: 'Annulla', class: 'btn btn-secondary', action: 'close' },
            { text: 'Crea Lista', class: 'btn btn-primary', action: 'create' }
        ]);

        const titleInput = modal.querySelector('#checklist-title');
        const descriptionInput = modal.querySelector('#checklist-description');

        titleInput?.focus();

        modal.addEventListener('modal-action', async (e) => {
            if (e.detail.action === 'create') {
                const title = titleInput?.value.trim();
                const description = descriptionInput?.value.trim();

                if (!title) {
                    this.showToast('Il titolo è obbligatorio', 'error');
                    titleInput?.focus();
                    return;
                }

                try {
                    const response = await fetch('/api/checklists', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ title, description })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.closeModal();
                        this.showToast('Checklist creata con successo', 'success');
                        this.navigateTo(`checklist/${data.data.id}`);
                    } else {
                        throw new Error(data.errors?.[0] || 'Failed to create checklist');
                    }
                } catch (error) {
                    console.error('❌ Failed to create checklist:', error);
                    this.showToast('Errore nella creazione della checklist', 'error');
                }
            }
        });
    }

    async showChecklistView(checklistId) {
        console.log('📝 Showing checklist view:', checklistId);
        this.showLoading('Caricamento checklist...');

        try {
            const response = await fetch(`/api/checklists/${checklistId}`, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                this.renderChecklistDetail(data.data);
            } else {
                throw new Error(data.errors?.[0] || 'Failed to load checklist');
            }

        } catch (error) {
            console.error('❌ Failed to load checklist:', error);
            this.showError('Errore nel caricamento della checklist');
            setTimeout(() => this.navigateTo('dashboard'), 2000);
        }
    }

    renderChecklistDetail(checklist) {
        this.hideLoading();

        const html = `
            <div class="app-layout">
                <aside class="sidebar">
                    <div class="sidebar-header">
                        <button class="btn btn-ghost" id="back-btn">← Dashboard</button>
                        <h1 class="logo">Meeplify</h1>
                    </div>
                    <div class="checklist-sidebar">
                        <div class="checklist-info">
                            <h3>${this.escapeHtml(checklist.title)}</h3>
                            ${checklist.description ? `<p>${this.escapeHtml(checklist.description)}</p>` : ''}
                        </div>
                    </div>
                </aside>
                
                <main class="main-content">
                    <header class="header">
                        <h1 class="checklist-title">${this.escapeHtml(checklist.title)}</h1>
                        <div class="header-actions">
                            <button class="btn btn-secondary" id="share-btn">Condividi</button>
                        </div>
                    </header>
                    
                    <div class="content-area">
                        <div class="checklist-sections" id="sections-container">
                            ${this.renderSections(checklist.sections || [])}
                        </div>
                    </div>
                </main>
            </div>
        `;

        this.renderContent(html);
        this.bindChecklistEvents(checklist);
    }

    renderSections(sections) {
        if (!sections || sections.length === 0) {
            return `
                <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <h3 class="empty-state-title">Nessuna sezione ancora</h3>
                    <p class="empty-state-message">Questa checklist è vuota</p>
                </div>
            `;
        }

        return sections.map(section => `
            <div class="section" data-section-id="${section.id}">
                <div class="section-header">
                    <h3 class="section-title">${this.escapeHtml(section.name)}</h3>
                </div>
                <div class="items">
                    ${this.renderItems(section.items || [])}
                </div>
            </div>
        `).join('');
    }

    renderItems(items) {
        if (!items || items.length === 0) {
            return '<div class="no-items">Nessun elemento</div>';
        }

        return items.map(item => `
            <div class="item ${item.completed ? 'completed' : ''}" data-item-id="${item.id}">
                <label class="item-checkbox">
                    <input type="checkbox" ${item.completed ? 'checked' : ''}>
                    <span class="checkmark"></span>
                </label>
                <span class="item-text">${this.escapeHtml(item.text)}</span>
            </div>
        `).join('');
    }

    bindChecklistEvents(checklist) {
        document.getElementById('back-btn')?.addEventListener('click', () => {
            this.navigateTo('dashboard');
        });
    }

    async showUserMenu() {
        const modal = this.createModal('Account', `
            <div class="user-info">
                <div class="user-avatar-large">${(this.currentUser?.name || 'U').charAt(0).toUpperCase()}</div>
                <h4>${this.currentUser?.name || 'Utente'}</h4>
                <p class="text-gray-600">${this.currentUser?.email || ''}</p>
            </div>
        `, [
            { text: 'Logout', class: 'btn btn-secondary', action: 'logout' },
            { text: 'Chiudi', class: 'btn btn-ghost', action: 'close' }
        ]);

        modal.addEventListener('modal-action', async (e) => {
            if (e.detail.action === 'logout') {
                await this.logout();
            }
        });
    }

    async logout() {
        try {
            await fetch('/api/auth/logout', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            this.currentUser = null;
            this.isAuthenticated = false;
            this.closeModal();
            this.showToast('Disconnesso con successo', 'success');
            this.navigateTo('login');

        } catch (error) {
            console.error('❌ Logout failed:', error);
            this.showToast('Errore durante la disconnessione', 'error');
        }
    }

    // UI Utilities
    showLoading(message = 'Caricamento...') {
        const html = `
            <div class="loading-screen">
                <div class="loading-spinner"></div>
                <div class="loading-text">${message}</div>
            </div>
        `;
        this.renderContent(html);
    }

    hideLoading() {
        const loading = document.querySelector('.loading-screen');
        loading?.remove();
    }

    showError(message) {
        const html = `
            <div class="error-screen">
                <div class="error-icon">⚠️</div>
                <div class="error-message">${message}</div>
            </div>
        `;
        this.renderContent(html);
    }

    renderContent(html) {
        const app = document.getElementById('app');
        if (app) {
            app.innerHTML = html;
        }
    }

    showMessage(element, message, type = 'info') {
        if (element) {
            element.textContent = message;
            element.className = `login-message ${type}`;
            element.classList.remove('hidden');
        }
    }

    showToast(message, type = 'info') {
        // Simple toast implementation
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        
        toast.style.position = 'fixed';
        toast.style.top = '20px';
        toast.style.right = '20px';
        toast.style.padding = '12px 24px';
        toast.style.borderRadius = '6px';
        toast.style.zIndex = '10000';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'transform 0.3s ease';
        
        // Colors based on type
        const colors = {
            success: { bg: '#059669', color: 'white' },
            error: { bg: '#dc2626', color: 'white' },
            info: { bg: '#3182ce', color: 'white' }
        };
        
        toast.style.backgroundColor = colors[type]?.bg || colors.info.bg;
        toast.style.color = colors[type]?.color || colors.info.color;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 10);
        
        // Remove after delay
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    createModal(title, body, actions = []) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">${title}</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body">${body}</div>
                <div class="modal-footer">
                    ${actions.map(action => 
                        `<button class="${action.class}" data-action="${action.action}">${action.text}</button>`
                    ).join('')}
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        modal.style.display = 'flex';

        // Bind events
        modal.querySelector('.modal-close')?.addEventListener('click', () => this.closeModal());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) this.closeModal();
        });

        modal.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                if (action === 'close') {
                    this.closeModal();
                } else {
                    modal.dispatchEvent(new CustomEvent('modal-action', { detail: { action } }));
                }
            });
        });

        return modal;
    }

    closeModal() {
        const modal = document.querySelector('.modal-overlay');
        modal?.remove();
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    formatDate(dateString) {
        if (!dateString) return '';
        
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

        if (diffDays === 0) return 'Oggi';
        if (diffDays === 1) return 'Ieri';
        if (diffDays < 7) return `${diffDays} giorni fa`;
        return date.toLocaleDateString('it-IT');
    }
}

// Initialize app when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.meeplifyApp = new MeeplifyApp();
    });
} else {
    window.meeplifyApp = new MeeplifyApp();
}