// Dashboard View for Meeplify
import { Toast } from '../components/Toast.js';
import { Modal } from '../components/Modal.js';

export class DashboardView {
    constructor(api, state, router) {
        this.api = api;
        this.state = state;
        this.router = router;
        this.toast = new Toast();
        this.element = null;
        this.currentSection = 'my-checklists';
        this.checklists = [];
        this.loading = false;
    }

    show(params = {}) {
        this.render();
        this.bindEvents();
        this.loadChecklists();
    }

    hide() {
        if (this.element) {
            this.element.style.display = 'none';
        }
    }

    render() {
        const app = document.getElementById('app');
        const user = this.state.get('user');
        
        const html = `
            <div class="app-layout">
                <aside class="sidebar">
                    <div class="sidebar-header">
                        <h1 class="logo">Meeplify</h1>
                    </div>
                    <nav>
                        <ul class="nav-list">
                            <li class="nav-item">
                                <a href="#" class="nav-link active" data-section="my-checklists">
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
                            <li class="nav-item">
                                <a href="#" class="nav-link" data-section="trash">
                                    <span class="nav-icon">🗑️</span>
                                    Cestino
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
                                <span>+ Nuova Lista</span>
                            </button>
                            <div class="user-menu" id="user-menu">
                                <div class="user-avatar">${(user?.name || 'U').charAt(0).toUpperCase()}</div>
                                <span class="user-name">${user?.name || 'Utente'}</span>
                            </div>
                        </div>
                    </header>
                    
                    <div class="content-area">
                        <div id="dashboard-content">
                            <div class="loading-state hidden" id="loading-state">
                                <div class="skeleton-cards">
                                    <div class="skeleton-card"></div>
                                    <div class="skeleton-card"></div>
                                    <div class="skeleton-card"></div>
                                </div>
                            </div>
                            
                            <div class="empty-state hidden" id="empty-state">
                                <div class="empty-state-icon">📝</div>
                                <h3 class="empty-state-title">Nessuna lista ancora</h3>
                                <p class="empty-state-message">Crea la tua prima checklist per iniziare a organizzare le tue attività</p>
                                <button class="btn btn-primary" id="create-first-checklist">Crea la prima lista</button>
                            </div>
                            
                            <div class="checklist-grid" id="checklist-grid">
                                <!-- Le card delle checklist verranno inserite qui -->
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        `;

        app.innerHTML = html;
        this.element = app.firstElementChild;
    }

    bindEvents() {
        // Navigation
        this.element.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = e.currentTarget.dataset.section;
                this.switchSection(section);
            });
        });

        // Create checklist buttons
        document.getElementById('create-checklist-btn')?.addEventListener('click', () => {
            this.showCreateModal();
        });
        
        document.getElementById('create-first-checklist')?.addEventListener('click', () => {
            this.showCreateModal();
        });

        // User menu
        document.getElementById('user-menu')?.addEventListener('click', () => {
            this.showUserMenu();
        });

        // Checklist card clicks (delegated)
        document.getElementById('checklist-grid')?.addEventListener('click', (e) => {
            const card = e.target.closest('.checklist-card');
            if (card && !e.target.closest('.card-actions')) {
                const id = card.dataset.id;
                this.router.navigate(`checklist/${id}`);
            }
        });
    }

    switchSection(section) {
        this.currentSection = section;
        
        // Update active nav
        this.element.querySelectorAll('.nav-link').forEach(link => {
            link.classList.toggle('active', link.dataset.section === section);
        });

        // Update header title
        const titles = {
            'my-checklists': 'Le mie liste',
            'shared': 'Condivise con me',
            'templates': 'Modelli',
            'trash': 'Cestino'
        };
        
        const headerTitle = this.element.querySelector('.header-title');
        headerTitle.textContent = titles[section] || 'Dashboard';

        // Hide/show create button for some sections
        const createBtn = document.getElementById('create-checklist-btn');
        createBtn.style.display = ['trash', 'shared'].includes(section) ? 'none' : 'inline-flex';

        // Load appropriate content
        this.loadChecklists();
    }

    async loadChecklists() {
        if (this.loading) return;
        
        this.showLoading();
        this.loading = true;

        try {
            let response;
            
            switch (this.currentSection) {
                case 'shared':
                    response = await this.api.getSharedChecklists();
                    break;
                case 'trash':
                    response = await this.api.get('/checklists/trash');
                    break;
                case 'templates':
                    response = await this.api.getTemplates();
                    break;
                default:
                    response = await this.api.getChecklists();
            }

            if (response.success) {
                this.checklists = response.data.checklists || response.data || [];
                this.renderChecklists();
            } else {
                throw new Error(response.error || 'Failed to load checklists');
            }

        } catch (error) {
            console.error('Error loading checklists:', error);
            this.toast.error('Errore nel caricamento delle liste');
            this.checklists = [];
            this.renderChecklists();
        } finally {
            this.hideLoading();
            this.loading = false;
        }
    }

    renderChecklists() {
        const grid = document.getElementById('checklist-grid');
        const emptyState = document.getElementById('empty-state');

        if (!this.checklists || this.checklists.length === 0) {
            grid.innerHTML = '';
            emptyState.classList.remove('hidden');
            return;
        }

        emptyState.classList.add('hidden');
        
        const cardsHtml = this.checklists.map(checklist => 
            this.renderChecklistCard(checklist)
        ).join('');
        
        grid.innerHTML = cardsHtml;
    }

    renderChecklistCard(checklist) {
        const progress = checklist.progress || 0;
        const itemCount = checklist.item_count || 0;
        const completedCount = checklist.completed_count || 0;
        const isTrash = this.currentSection === 'trash';
        const isTemplate = this.currentSection === 'templates';
        
        return `
            <div class="checklist-card" data-id="${checklist.id}">
                <div class="checklist-header">
                    <h3 class="checklist-title">${this.escapeHtml(checklist.title)}</h3>
                    <div class="card-actions">
                        <button class="btn-icon btn-ghost card-menu" data-id="${checklist.id}">⋮</button>
                    </div>
                </div>
                
                ${checklist.description ? `
                    <p class="checklist-description">${this.escapeHtml(checklist.description)}</p>
                ` : ''}
                
                ${!isTemplate ? `
                    <div class="checklist-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${progress}%"></div>
                        </div>
                        <div class="progress-text">${completedCount}/${itemCount} completati</div>
                    </div>
                ` : ''}
                
                <div class="checklist-tags">
                    <!-- Tags will be added here if available -->
                </div>
                
                <div class="checklist-footer">
                    <div class="collaborators">
                        ${checklist.owner_name ? `
                            <div class="collaborator-avatar" title="${checklist.owner_name}">
                                ${checklist.owner_name.charAt(0).toUpperCase()}
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="updated-time">
                        ${isTrash ? 
                            `Eliminata ${this.formatDate(checklist.deleted_at)}` : 
                            this.formatDate(checklist.updated_at || checklist.created_at)
                        }
                    </div>
                </div>
            </div>
        `;
    }

    showLoading() {
        const loadingState = document.getElementById('loading-state');
        const grid = document.getElementById('checklist-grid');
        const emptyState = document.getElementById('empty-state');
        
        loadingState?.classList.remove('hidden');
        grid?.classList.add('hidden');
        emptyState?.classList.add('hidden');
    }

    hideLoading() {
        const loadingState = document.getElementById('loading-state');
        const grid = document.getElementById('checklist-grid');
        
        loadingState?.classList.add('hidden');
        grid?.classList.remove('hidden');
    }

    async showCreateModal() {
        const content = `
            <div class="modal-header">
                <h3 class="modal-title">Nuova Checklist</h3>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="label" for="checklist-title">Titolo *</label>
                    <input type="text" id="checklist-title" class="input" placeholder="Nome della checklist" required>
                </div>
                <div class="form-group">
                    <label class="label" for="checklist-description">Descrizione (opzionale)</label>
                    <textarea id="checklist-description" class="input textarea" placeholder="Descrizione della checklist"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary modal-cancel">Annulla</button>
                <button class="btn btn-primary modal-create">Crea Lista</button>
            </div>
        `;

        const modal = new Modal({ closable: true });
        modal.setContent(content).open();

        const titleInput = modal.modal.querySelector('#checklist-title');
        const descriptionInput = modal.modal.querySelector('#checklist-description');
        const createBtn = modal.modal.querySelector('.modal-create');
        const cancelBtn = modal.modal.querySelector('.modal-cancel');

        titleInput.focus();

        const createChecklist = async () => {
            const title = titleInput.value.trim();
            const description = descriptionInput.value.trim();

            if (!title) {
                this.toast.error('Il titolo è obbligatorio');
                titleInput.focus();
                return;
            }

            createBtn.disabled = true;
            createBtn.textContent = 'Creazione...';

            try {
                const response = await this.api.createChecklist({
                    title,
                    description
                });

                if (response.success) {
                    modal.close();
                    this.toast.success('Checklist creata con successo');
                    
                    // Navigate to the new checklist
                    this.router.navigate(`checklist/${response.data.id}`);
                } else {
                    throw new Error(response.error || 'Failed to create checklist');
                }
            } catch (error) {
                console.error('Error creating checklist:', error);
                this.toast.error('Errore nella creazione della checklist');
                createBtn.disabled = false;
                createBtn.textContent = 'Crea Lista';
            }
        };

        createBtn.addEventListener('click', createChecklist);
        cancelBtn.addEventListener('click', () => modal.close());

        // Handle Enter key
        titleInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                createChecklist();
            }
        });
    }

    showUserMenu() {
        const user = this.state.get('user');
        
        const content = `
            <div class="modal-header">
                <h3 class="modal-title">Account</h3>
            </div>
            <div class="modal-body">
                <div class="user-info">
                    <div class="user-avatar-large">${(user?.name || 'U').charAt(0).toUpperCase()}</div>
                    <h4>${user?.name || 'Utente'}</h4>
                    <p class="text-gray-600">${user?.email || ''}</p>
                    <span class="badge ${user?.role === 'admin' ? 'badge-primary' : 'badge-secondary'}">
                        ${user?.role === 'admin' ? 'Amministratore' : 'Utente'}
                    </span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary modal-logout">Logout</button>
                <button class="btn btn-ghost modal-close">Chiudi</button>
            </div>
        `;

        const modal = new Modal({ closable: true });
        modal.setContent(content).open();

        const logoutBtn = modal.modal.querySelector('.modal-logout');
        const closeBtn = modal.modal.querySelector('.modal-close');

        logoutBtn.addEventListener('click', async () => {
            try {
                await this.api.logout();
                modal.close();
                this.state.set('user', null);
                this.toast.success('Disconnesso con successo');
            } catch (error) {
                console.error('Logout error:', error);
                this.toast.error('Errore durante la disconnessione');
            }
        });

        closeBtn.addEventListener('click', () => modal.close());
    }

    // Utility methods
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

        if (diffDays === 0) {
            return 'Oggi';
        } else if (diffDays === 1) {
            return 'Ieri';
        } else if (diffDays < 7) {
            return `${diffDays} giorni fa`;
        } else {
            return date.toLocaleDateString('it-IT');
        }
    }
}

// Add CSS for skeleton loading
if (!document.querySelector('#dashboard-styles')) {
    const styles = document.createElement('style');
    styles.id = 'dashboard-styles';
    styles.textContent = `
        .skeleton-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        
        .skeleton-card {
            height: 200px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 2s infinite;
            border-radius: 0.75rem;
        }
        
        .user-info {
            text-align: center;
            padding: 1rem;
        }
        
        .user-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--color-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 600;
            margin: 0 auto 1rem;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }
        
        .badge-primary {
            background: var(--color-primary);
            color: white;
        }
        
        .badge-secondary {
            background: var(--color-gray-200);
            color: var(--color-gray-700);
        }
        
        .checklist-description {
            color: var(--color-gray-600);
            font-size: var(--font-size-sm);
            margin-bottom: var(--space-4);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .checklist-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-3);
        }
        
        .card-actions {
            opacity: 0;
            transition: opacity var(--transition-fast);
        }
        
        .checklist-card:hover .card-actions {
            opacity: 1;
        }
        
        .card-menu {
            font-size: 1.2rem;
            color: var(--color-gray-500);
        }
        
        .card-menu:hover {
            color: var(--color-gray-700);
        }
    `;
    document.head.appendChild(styles);
}