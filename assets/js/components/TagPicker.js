// TagPicker Component with Responsive Design for Meeplify
export class TagPicker {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            allowCreate: true,
            maxTags: 10,
            placeholder: 'Add tags...',
            colors: [
                '#3182ce', '#059669', '#d97706', '#dc2626', 
                '#7c3aed', '#be185d', '#0891b2', '#65a30d'
            ],
            emojis: [
                '🏷️', '⭐', '🎯', '🔥', '💡', '📌', '✅', '⚠️',
                '🚀', '💼', '📝', '🔧', '🎨', '📊', '💬', '🔔'
            ],
            ...options
        };
        
        this.tags = [];
        this.selectedTags = [];
        this.isOpen = false;
        this.input = null;
        this.dropdown = null;
        
        this.init();
    }

    init() {
        this.createStructure();
        this.bindEvents();
        this.loadTags();
    }

    createStructure() {
        this.container.className = 'tag-picker';
        this.container.innerHTML = `
            <div class="tag-picker-selected">
                <div class="selected-tags"></div>
                <input type="text" class="tag-input" placeholder="${this.options.placeholder}" />
            </div>
            <div class="tag-dropdown hidden">
                <div class="tag-dropdown-header">
                    <input type="text" class="tag-search" placeholder="Search or create tag..." />
                </div>
                <div class="tag-dropdown-content">
                    <div class="available-tags"></div>
                    <div class="tag-creator hidden">
                        <div class="tag-creator-preview"></div>
                        <div class="tag-creator-controls">
                            <div class="emoji-selector">
                                <label class="form-label">Emoji:</label>
                                <div class="emoji-grid"></div>
                            </div>
                            <div class="color-selector">
                                <label class="form-label">Color:</label>
                                <div class="color-grid"></div>
                            </div>
                            <button class="btn btn-primary btn-sm create-tag-btn">Create Tag</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.input = this.container.querySelector('.tag-input');
        this.dropdown = this.container.querySelector('.tag-dropdown');
        this.searchInput = this.container.querySelector('.tag-search');
        
        this.createEmojiSelector();
        this.createColorSelector();
    }

    createEmojiSelector() {
        const emojiGrid = this.container.querySelector('.emoji-grid');
        this.options.emojis.forEach(emoji => {
            const btn = document.createElement('button');
            btn.className = 'emoji-btn';
            btn.textContent = emoji;
            btn.addEventListener('click', () => this.selectEmoji(emoji));
            emojiGrid.appendChild(btn);
        });
    }

    createColorSelector() {
        const colorGrid = this.container.querySelector('.color-grid');
        this.options.colors.forEach(color => {
            const btn = document.createElement('button');
            btn.className = 'color-btn';
            btn.style.backgroundColor = color;
            btn.addEventListener('click', () => this.selectColor(color));
            colorGrid.appendChild(btn);
        });
    }

    bindEvents() {
        // Input focus/blur
        this.input.addEventListener('focus', () => this.open());
        this.input.addEventListener('blur', (e) => {
            // Delay to allow clicking in dropdown
            setTimeout(() => {
                if (!this.container.contains(document.activeElement)) {
                    this.close();
                }
            }, 150);
        });

        // Search input
        this.searchInput.addEventListener('input', (e) => {
            this.handleSearch(e.target.value);
        });

        // Create tag button
        const createBtn = this.container.querySelector('.create-tag-btn');
        createBtn.addEventListener('click', () => this.createNewTag());

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target)) {
                this.close();
            }
        });

        // Keyboard navigation
        this.container.addEventListener('keydown', (e) => this.handleKeyboard(e));
    }

    async loadTags() {
        try {
            // This would typically load from API
            // For now, use demo data
            this.tags = [
                { id: 1, name: 'Important', emoji: '⭐', color: '#dc2626' },
                { id: 2, name: 'Work', emoji: '💼', color: '#3182ce' },
                { id: 3, name: 'Personal', emoji: '🏠', color: '#059669' },
                { id: 4, name: 'Urgent', emoji: '🔥', color: '#d97706' }
            ];
            
            this.renderAvailableTags();
        } catch (error) {
            console.error('Failed to load tags:', error);
        }
    }

    renderAvailableTags(filter = '') {
        const container = this.container.querySelector('.available-tags');
        const filteredTags = this.tags.filter(tag => 
            tag.name.toLowerCase().includes(filter.toLowerCase()) &&
            !this.selectedTags.find(selected => selected.id === tag.id)
        );

        container.innerHTML = '';

        if (filteredTags.length === 0 && !filter) {
            container.innerHTML = '<div class="no-tags">No tags available</div>';
            return;
        }

        filteredTags.forEach(tag => {
            const element = this.createTagElement(tag, true);
            element.addEventListener('click', () => this.selectTag(tag));
            container.appendChild(element);
        });

        // Show create option if search term doesn't match existing tags
        if (filter && !this.tags.find(tag => 
            tag.name.toLowerCase() === filter.toLowerCase()
        )) {
            this.showTagCreator(filter);
        } else {
            this.hideTagCreator();
        }
    }

    createTagElement(tag, selectable = false) {
        const element = document.createElement('div');
        element.className = `tag ${selectable ? 'tag-selectable' : ''}`;
        element.style.setProperty('--tag-color', tag.color);
        
        const isDesktop = window.innerWidth >= 768;
        
        if (isDesktop) {
            // Desktop: full display with emoji, color, and text
            element.innerHTML = `
                <span class="tag-emoji">${tag.emoji}</span>
                <span class="tag-text">${tag.name}</span>
                ${!selectable ? '<button class="tag-remove" aria-label="Remove tag">×</button>' : ''}
            `;
        } else {
            // Mobile: minimal display with emoji and color only
            element.innerHTML = `
                <span class="tag-emoji">${tag.emoji}</span>
                ${!selectable ? '<button class="tag-remove" aria-label="Remove tag">×</button>' : ''}
            `;
            element.title = tag.name; // Show name on hover/long press
        }

        if (!selectable) {
            const removeBtn = element.querySelector('.tag-remove');
            removeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.removeTag(tag);
            });
        }

        return element;
    }

    showTagCreator(name) {
        const creator = this.container.querySelector('.tag-creator');
        const preview = this.container.querySelector('.tag-creator-preview');
        
        creator.classList.remove('hidden');
        
        // Reset selections
        this.selectedEmoji = this.options.emojis[0];
        this.selectedColor = this.options.colors[0];
        this.tagName = name;
        
        this.updateTagPreview();
    }

    hideTagCreator() {
        const creator = this.container.querySelector('.tag-creator');
        creator.classList.add('hidden');
    }

    selectEmoji(emoji) {
        this.selectedEmoji = emoji;
        
        // Update UI
        this.container.querySelectorAll('.emoji-btn').forEach(btn => {
            btn.classList.toggle('selected', btn.textContent === emoji);
        });
        
        this.updateTagPreview();
    }

    selectColor(color) {
        this.selectedColor = color;
        
        // Update UI
        this.container.querySelectorAll('.color-btn').forEach(btn => {
            btn.classList.toggle('selected', btn.style.backgroundColor === color);
        });
        
        this.updateTagPreview();
    }

    updateTagPreview() {
        const preview = this.container.querySelector('.tag-creator-preview');
        const tempTag = {
            name: this.tagName,
            emoji: this.selectedEmoji,
            color: this.selectedColor
        };
        
        const element = this.createTagElement(tempTag, true);
        element.classList.add('tag-preview');
        
        preview.innerHTML = '';
        preview.appendChild(element);
    }

    async createNewTag() {
        if (!this.tagName || !this.selectedEmoji || !this.selectedColor) {
            return;
        }

        try {
            // This would typically create via API
            const newTag = {
                id: Date.now(), // Temporary ID
                name: this.tagName,
                emoji: this.selectedEmoji,
                color: this.selectedColor
            };

            this.tags.push(newTag);
            this.selectTag(newTag);
            this.hideTagCreator();
            this.searchInput.value = '';
            
        } catch (error) {
            console.error('Failed to create tag:', error);
        }
    }

    selectTag(tag) {
        if (this.selectedTags.find(selected => selected.id === tag.id)) {
            return; // Already selected
        }

        if (this.selectedTags.length >= this.options.maxTags) {
            return; // Max tags reached
        }

        this.selectedTags.push(tag);
        this.renderSelectedTags();
        this.renderAvailableTags(this.searchInput.value);
        this.triggerChange();
    }

    removeTag(tag) {
        this.selectedTags = this.selectedTags.filter(selected => selected.id !== tag.id);
        this.renderSelectedTags();
        this.renderAvailableTags(this.searchInput.value);
        this.triggerChange();
    }

    renderSelectedTags() {
        const container = this.container.querySelector('.selected-tags');
        container.innerHTML = '';

        this.selectedTags.forEach(tag => {
            const element = this.createTagElement(tag, false);
            container.appendChild(element);
        });
    }

    handleSearch(query) {
        this.renderAvailableTags(query);
        if (query.trim()) {
            this.tagName = query.trim();
        }
    }

    handleKeyboard(e) {
        if (e.key === 'Escape') {
            this.close();
        } else if (e.key === 'Enter' && this.searchInput.value.trim()) {
            e.preventDefault();
            
            // Try to select first available tag or create new one
            const available = this.container.querySelector('.available-tags .tag-selectable');
            if (available) {
                available.click();
            } else if (this.options.allowCreate) {
                this.showTagCreator(this.searchInput.value.trim());
            }
        }
    }

    open() {
        if (this.isOpen) return;
        
        this.dropdown.classList.remove('hidden');
        this.searchInput.focus();
        this.isOpen = true;
        
        this.triggerEvent('open');
    }

    close() {
        if (!this.isOpen) return;
        
        this.dropdown.classList.add('hidden');
        this.hideTagCreator();
        this.searchInput.value = '';
        this.isOpen = false;
        
        this.triggerEvent('close');
    }

    triggerChange() {
        this.triggerEvent('change', { tags: this.selectedTags });
    }

    triggerEvent(type, data = {}) {
        const event = new CustomEvent(`tagpicker:${type}`, {
            detail: { ...data, picker: this }
        });
        this.container.dispatchEvent(event);
    }

    // Public API
    getSelectedTags() {
        return [...this.selectedTags];
    }

    setSelectedTags(tags) {
        this.selectedTags = tags.filter(tag => 
            this.tags.find(existing => existing.id === tag.id)
        );
        this.renderSelectedTags();
        this.renderAvailableTags();
        this.triggerChange();
    }

    addTag(tag) {
        this.selectTag(tag);
    }

    removeTagById(id) {
        const tag = this.selectedTags.find(tag => tag.id === id);
        if (tag) {
            this.removeTag(tag);
        }
    }

    clear() {
        this.selectedTags = [];
        this.renderSelectedTags();
        this.renderAvailableTags();
        this.triggerChange();
    }
}

// Add responsive tag rendering utility
export class TagRenderer {
    static render(tag, options = {}) {
        const {
            removable = false,
            selectable = false,
            onClick = null,
            onRemove = null
        } = options;

        const element = document.createElement('div');
        element.className = `tag ${selectable ? 'tag-selectable' : ''}`;
        element.style.setProperty('--tag-color', tag.color);
        
        const isDesktop = window.innerWidth >= 768;
        
        if (isDesktop) {
            element.innerHTML = `
                <span class="tag-emoji">${tag.emoji}</span>
                <span class="tag-text">${tag.name}</span>
                ${removable ? '<button class="tag-remove" aria-label="Remove tag">×</button>' : ''}
            `;
        } else {
            element.innerHTML = `
                <span class="tag-emoji">${tag.emoji}</span>
                ${removable ? '<button class="tag-remove" aria-label="Remove tag">×</button>' : ''}
            `;
            element.title = tag.name;
        }

        if (onClick) {
            element.addEventListener('click', () => onClick(tag));
        }

        if (removable && onRemove) {
            const removeBtn = element.querySelector('.tag-remove');
            removeBtn?.addEventListener('click', (e) => {
                e.stopPropagation();
                onRemove(tag);
            });
        }

        return element;
    }

    static renderList(tags, container, options = {}) {
        container.innerHTML = '';
        tags.forEach(tag => {
            const element = TagRenderer.render(tag, options);
            container.appendChild(element);
        });
    }
}

// CSS for TagPicker (to be added to main.css)
const tagPickerStyles = `
.tag-picker {
    position: relative;
    width: 100%;
}

.tag-picker-selected {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--border-radius);
    min-height: 42px;
    background: white;
}

.tag-picker-selected:focus-within {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(var(--color-primary), 0.1);
}

.selected-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
}

.tag-input {
    border: none;
    outline: none;
    flex: 1;
    min-width: 120px;
    font-size: 0.875rem;
}

.tag-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1000;
    background: white;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-lg);
    max-height: 300px;
    overflow-y: auto;
}

.tag-dropdown-header {
    padding: 0.75rem;
    border-bottom: 1px solid var(--color-gray-200);
}

.tag-search {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--border-radius-sm);
    font-size: 0.875rem;
}

.tag-dropdown-content {
    padding: 0.5rem;
}

.available-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    margin-bottom: 0.75rem;
}

.tag-selectable {
    cursor: pointer;
    transition: all var(--transition-fast);
}

.tag-selectable:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}

.tag-creator {
    padding: 0.75rem;
    border-top: 1px solid var(--color-gray-200);
}

.tag-creator-preview {
    margin-bottom: 0.75rem;
}

.emoji-grid, .color-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    margin-top: 0.5rem;
    margin-bottom: 0.75rem;
}

.emoji-btn, .color-btn {
    width: 32px;
    height: 32px;
    border: 2px solid transparent;
    border-radius: var(--border-radius-sm);
    cursor: pointer;
    transition: all var(--transition-fast);
}

.emoji-btn {
    background: var(--color-gray-100);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.emoji-btn:hover, .color-btn:hover {
    transform: scale(1.1);
}

.emoji-btn.selected, .color-btn.selected {
    border-color: var(--color-primary);
}

.tag-remove {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.8);
    font-size: 1rem;
    line-height: 1;
    padding: 0;
    margin-left: 0.25rem;
    cursor: pointer;
    transition: color var(--transition-fast);
}

.tag-remove:hover {
    color: white;
}

.no-tags {
    text-align: center;
    color: var(--color-gray-500);
    font-size: 0.875rem;
    padding: 1rem;
}

@media (max-width: 767px) {
    .tag-dropdown {
        position: fixed;
        top: 50%;
        left: 1rem;
        right: 1rem;
        transform: translateY(-50%);
        max-height: 80vh;
    }
}
`;