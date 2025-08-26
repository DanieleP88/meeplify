// Checklist Detail View for Meeplify
import { Toast } from '../components/Toast.js';
import { Modal } from '../components/Modal.js';

export class ChecklistDetailView {
    constructor(api, state, router) {
        this.api = api;
        this.state = state;
        this.router = router;
        this.toast = new Toast();
        this.element = null;
        this.checklist = null;
        this.checklistId = null;
    }

    show(params = {}) {
        this.checklistId = params.checklistId;
        this.render();
        this.bindEvents();
        this.loadChecklist();
    }

    hide() {
        if (this.element) {
            this.element.style.display = 'none';
        }
    }

    render() {
        const app = document.getElementById('app');
        
        const html = `
            <div class="app-layout">
                <aside class="sidebar">
                    <div class="sidebar-header">
                        <button class="btn btn-ghost" id="back-btn">← Dashboard</button>
                        <h1 class="logo">Meeplify</h1>
                    </div>
                    <div class="checklist-sidebar">
                        <div class="progress-circle" id="progress-circle">
                            <svg width="80" height="80" viewBox="0 0 80 80">
                                <circle cx="40" cy="40" r="35" fill="none" stroke="var(--color-gray-200)" stroke-width="6"/>
                                <circle cx="40" cy="40" r="35" fill="none" stroke="var(--color-primary)" stroke-width="6" 
                                        stroke-dasharray="220" stroke-dashoffset="220" id="progress-stroke"/>
                            </svg>
                            <div class="progress-text" id="progress-text">0%</div>
                        </div>
                        <div class="checklist-stats" id="checklist-stats">
                            <div class="stat-item">
                                <span class="stat-value" id="total-items">0</span>
                                <span class="stat-label">Elementi</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value" id="completed-items">0</span>
                                <span class="stat-label">Completati</span>
                            </div>
                        </div>
                    </div>
                </aside>
                
                <main class="main-content">
                    <header class="header">
                        <div class="checklist-header-content">
                            <h1 class="checklist-title-edit" id="checklist-title" contenteditable="false">Caricamento...</h1>
                            <p class="checklist-description-edit" id="checklist-description" contenteditable="false"></p>
                        </div>
                        <div class="header-actions">
                            <button class="btn btn-ghost" id="edit-btn">Modifica</button>
                            <button class="btn btn-secondary" id="share-btn">Condividi</button>
                            <button class="btn-icon btn-ghost" id="more-btn">⋮</button>
                        </div>
                    </header>
                    
                    <div class="content-area">
                        <div class="loading-state" id="loading-state">
                            <div class="loading-spinner"></div>
                            <div class="loading-text">Caricamento checklist...</div>
                        </div>
                        
                        <div class="checklist-content hidden" id="checklist-content">
                            <div class="sections" id="sections-container">
                                <!-- Le sezioni verranno inserite qui -->
                            </div>
                            
                            <button class="btn btn-ghost add-section-btn" id="add-section-btn">
                                + Aggiungi sezione
                            </button>
                        </div>
                    </div>
                </main>
            </div>
        `;

        app.innerHTML = html;
        this.element = app.firstElementChild;
    }

    bindEvents() {
        // Back button
        document.getElementById('back-btn')?.addEventListener('click', () => {
            this.router.navigate('dashboard');
        });

        // Edit button
        document.getElementById('edit-btn')?.addEventListener('click', () => {
            this.toggleEdit();
        });

        // Add section button
        document.getElementById('add-section-btn')?.addEventListener('click', () => {
            this.addSection();
        });

        // Title and description editing
        const title = document.getElementById('checklist-title');
        const description = document.getElementById('checklist-description');

        title?.addEventListener('blur', () => this.saveTitle());
        description?.addEventListener('blur', () => this.saveDescription());
        
        title?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                title.blur();
            }
        });
    }

    async loadChecklist() {
        if (!this.checklistId) {
            this.toast.error('ID checklist non valido');
            this.router.navigate('dashboard');
            return;
        }

        try {
            const response = await this.api.getChecklist(this.checklistId);
            
            if (response.success) {
                this.checklist = response.data;
                this.renderChecklist();
            } else {
                throw new Error(response.error || 'Failed to load checklist');
            }
        } catch (error) {
            console.error('Error loading checklist:', error);
            this.toast.error('Errore nel caricamento della checklist');
            this.router.navigate('dashboard');
        }
    }

    renderChecklist() {
        if (!this.checklist) return;

        // Hide loading, show content
        document.getElementById('loading-state')?.classList.add('hidden');
        document.getElementById('checklist-content')?.classList.remove('hidden');

        // Update title and description
        const titleEl = document.getElementById('checklist-title');
        const descEl = document.getElementById('checklist-description');
        
        if (titleEl) titleEl.textContent = this.checklist.title;
        if (descEl) {
            descEl.textContent = this.checklist.description || '';
            descEl.style.display = this.checklist.description ? 'block' : 'none';
        }

        // Render sections
        this.renderSections();
        
        // Update progress
        this.updateProgress();
    }

    renderSections() {
        const container = document.getElementById('sections-container');
        if (!container || !this.checklist.sections) return;

        const sectionsHtml = this.checklist.sections.map((section, index) => `
            <div class="section" data-section-id="${section.id}">
                <div class="section-header">
                    <h3 class="section-title" contenteditable="false">${this.escapeHtml(section.name)}</h3>
                    <div class="section-actions">
                        <button class="btn-icon btn-ghost add-item-btn" data-section-id="${section.id}">+</button>
                        <button class="btn-icon btn-ghost section-menu-btn">⋮</button>
                    </div>
                </div>
                <div class="items" data-section-id="${section.id}">
                    ${section.items ? section.items.map(item => this.renderItem(item)).join('') : ''}
                </div>
            </div>
        `).join('');

        container.innerHTML = sectionsHtml;

        // Bind item events
        this.bindItemEvents();
    }

    renderItem(item) {
        return `
            <div class="item ${item.completed ? 'completed' : ''}" data-item-id="${item.id}">
                <label class="item-checkbox">
                    <input type="checkbox" ${item.completed ? 'checked' : ''} data-item-id="${item.id}">
                    <span class="checkmark"></span>
                </label>
                <div class="item-content">
                    <span class="item-text" contenteditable="false">${this.escapeHtml(item.text)}</span>
                    <div class="item-tags">
                        <!-- Tags will be added here -->
                    </div>
                </div>
                <div class="item-actions">
                    <button class="btn-icon btn-ghost item-menu-btn" data-item-id="${item.id}">⋮</button>
                </div>
            </div>
        `;
    }

    bindItemEvents() {
        // Checkbox changes
        document.querySelectorAll('.item-checkbox input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const itemId = e.target.dataset.itemId;
                this.toggleItem(itemId, e.target.checked);
            });
        });

        // Add item buttons
        document.querySelectorAll('.add-item-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const sectionId = e.target.dataset.sectionId;
                this.addItem(sectionId);
            });
        });
    }

    async toggleItem(itemId, completed) {
        try {
            const response = await this.api.toggleItem(this.checklistId, itemId, completed);
            
            if (response.success) {
                // Update local state
                const item = this.findItemById(itemId);
                if (item) {
                    item.completed = completed;
                }
                
                // Update UI
                const itemEl = document.querySelector(`[data-item-id="${itemId}"]`);
                itemEl?.classList.toggle('completed', completed);
                
                // Update progress
                this.updateProgress();
                
            } else {
                throw new Error(response.error || 'Failed to update item');
            }
        } catch (error) {
            console.error('Error toggling item:', error);
            this.toast.error('Errore nell\'aggiornamento dell\'elemento');
            
            // Revert checkbox
            const checkbox = document.querySelector(`input[data-item-id="${itemId}"]`);
            if (checkbox) checkbox.checked = !completed;
        }
    }

    async addItem(sectionId) {
        const itemText = await Modal.prompt('Testo dell\'elemento:', '', 'Nuovo elemento');
        
        if (!itemText) return;

        try {
            const response = await this.api.createItem(this.checklistId, sectionId, {
                text: itemText
            });
            
            if (response.success) {
                // Reload checklist to get updated data
                this.loadChecklist();
                this.toast.success('Elemento aggiunto');
            } else {
                throw new Error(response.error || 'Failed to create item');
            }
        } catch (error) {
            console.error('Error creating item:', error);
            this.toast.error('Errore nella creazione dell\'elemento');
        }
    }

    async addSection() {
        const sectionName = await Modal.prompt('Nome della sezione:', '', 'Nuova sezione');
        
        if (!sectionName) return;

        try {
            const response = await this.api.createSection(this.checklistId, {
                name: sectionName
            });
            
            if (response.success) {
                // Reload checklist to get updated data
                this.loadChecklist();
                this.toast.success('Sezione aggiunta');
            } else {
                throw new Error(response.error || 'Failed to create section');
            }
        } catch (error) {
            console.error('Error creating section:', error);
            this.toast.error('Errore nella creazione della sezione');
        }
    }

    toggleEdit() {
        const editBtn = document.getElementById('edit-btn');
        const title = document.getElementById('checklist-title');
        const description = document.getElementById('checklist-description');
        
        const isEditing = title?.contentEditable === 'true';
        
        if (isEditing) {
            // Stop editing
            title.contentEditable = 'false';
            description.contentEditable = 'false';
            editBtn.textContent = 'Modifica';
            
            // Save changes
            this.saveTitle();
            this.saveDescription();
            
        } else {
            // Start editing
            title.contentEditable = 'true';
            description.contentEditable = 'true';
            description.style.display = 'block';
            editBtn.textContent = 'Salva';
            
            title.focus();
        }
    }

    async saveTitle() {
        const titleEl = document.getElementById('checklist-title');
        if (!titleEl) return;

        const newTitle = titleEl.textContent.trim();
        if (!newTitle || newTitle === this.checklist.title) return;

        try {
            const response = await this.api.updateChecklist(this.checklistId, {
                title: newTitle,
                description: this.checklist.description
            });
            
            if (response.success) {
                this.checklist.title = newTitle;
                this.toast.success('Titolo aggiornato');
            } else {
                throw new Error(response.error || 'Failed to update title');
            }
        } catch (error) {
            console.error('Error updating title:', error);
            this.toast.error('Errore nell\'aggiornamento del titolo');
            titleEl.textContent = this.checklist.title; // Revert
        }
    }

    async saveDescription() {
        const descEl = document.getElementById('checklist-description');
        if (!descEl) return;

        const newDescription = descEl.textContent.trim();
        if (newDescription === this.checklist.description) return;

        try {
            const response = await this.api.updateChecklist(this.checklistId, {
                title: this.checklist.title,
                description: newDescription
            });
            
            if (response.success) {
                this.checklist.description = newDescription;
                if (!newDescription) {
                    descEl.style.display = 'none';
                }
                this.toast.success('Descrizione aggiornata');
            } else {
                throw new Error(response.error || 'Failed to update description');
            }
        } catch (error) {
            console.error('Error updating description:', error);
            this.toast.error('Errore nell\'aggiornamento della descrizione');
            descEl.textContent = this.checklist.description || ''; // Revert
        }
    }

    updateProgress() {
        if (!this.checklist?.sections) return;

        let totalItems = 0;
        let completedItems = 0;

        this.checklist.sections.forEach(section => {
            if (section.items) {
                totalItems += section.items.length;
                completedItems += section.items.filter(item => item.completed).length;
            }
        });

        const progress = totalItems > 0 ? Math.round((completedItems / totalItems) * 100) : 0;

        // Update progress circle
        const progressStroke = document.getElementById('progress-stroke');
        const progressText = document.getElementById('progress-text');
        
        if (progressStroke) {
            const offset = 220 - (220 * progress / 100);
            progressStroke.style.strokeDashoffset = offset;
        }
        
        if (progressText) {
            progressText.textContent = `${progress}%`;
        }

        // Update stats
        document.getElementById('total-items').textContent = totalItems;
        document.getElementById('completed-items').textContent = completedItems;
    }

    findItemById(itemId) {
        if (!this.checklist?.sections) return null;
        
        for (const section of this.checklist.sections) {
            if (section.items) {
                const item = section.items.find(item => item.id == itemId);
                if (item) return item;
            }
        }
        return null;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
}

// Add CSS for checklist detail view
if (!document.querySelector('#checklist-detail-styles')) {
    const styles = document.createElement('style');
    styles.id = 'checklist-detail-styles';
    styles.textContent = `
        .checklist-sidebar {
            padding: 2rem 1.5rem;
            text-align: center;
        }
        
        .progress-circle {
            position: relative;
            margin-bottom: 2rem;
        }
        
        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--color-primary);
        }
        
        .checklist-stats {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
        }
        
        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--color-gray-900);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--color-gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .checklist-header-content {
            flex: 1;
        }
        
        .checklist-title-edit {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            outline: none;
            border-radius: 0.25rem;
            padding: 0.25rem;
            transition: all 150ms ease;
        }
        
        .checklist-title-edit[contenteditable="true"] {
            background: var(--color-gray-100);
        }
        
        .checklist-description-edit {
            color: var(--color-gray-600);
            outline: none;
            border-radius: 0.25rem;
            padding: 0.25rem;
            transition: all 150ms ease;
        }
        
        .checklist-description-edit[contenteditable="true"] {
            background: var(--color-gray-100);
        }
        
        .section {
            margin-bottom: 2rem;
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--color-gray-200);
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--color-gray-900);
        }
        
        .section-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--color-gray-100);
            transition: all 150ms ease;
        }
        
        .item:last-child {
            border-bottom: none;
        }
        
        .item.completed {
            opacity: 0.6;
        }
        
        .item.completed .item-text {
            text-decoration: line-through;
        }
        
        .item-checkbox {
            position: relative;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        
        .item-checkbox input {
            opacity: 0;
            position: absolute;
        }
        
        .checkmark {
            width: 20px;
            height: 20px;
            border: 2px solid var(--color-gray-300);
            border-radius: 0.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 150ms ease;
        }
        
        .item-checkbox input:checked + .checkmark {
            background: var(--color-primary);
            border-color: var(--color-primary);
        }
        
        .item-checkbox input:checked + .checkmark::after {
            content: '✓';
            color: white;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .item-content {
            flex: 1;
            min-width: 0;
        }
        
        .item-text {
            display: block;
            word-wrap: break-word;
            line-height: 1.5;
        }
        
        .item-tags {
            margin-top: 0.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }
        
        .item-actions {
            opacity: 0;
            transition: opacity 150ms ease;
        }
        
        .item:hover .item-actions {
            opacity: 1;
        }
        
        .add-section-btn {
            width: 100%;
            justify-content: center;
            padding: 1rem;
            border: 2px dashed var(--color-gray-300);
            border-radius: 0.75rem;
            color: var(--color-gray-500);
            transition: all 150ms ease;
        }
        
        .add-section-btn:hover {
            border-color: var(--color-primary);
            color: var(--color-primary);
        }
    `;
    document.head.appendChild(styles);
}