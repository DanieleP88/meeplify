// API Service for Meeplify
export class ApiService {
    constructor() {
        this.baseUrl = '/api';
        this.defaultHeaders = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const config = {
            headers: { ...this.defaultHeaders, ...options.headers },
            ...options
        };

        try {
            const response = await fetch(url, config);
            
            // Handle non-JSON responses (like redirects for OAuth)
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                if (response.ok) {
                    return { success: true, data: await response.text() };
                } else {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
            }

            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || `HTTP ${response.status}: ${response.statusText}`);
            }

            return data;
        } catch (error) {
            console.error('API Request failed:', {
                endpoint,
                error: error.message,
                options
            });
            throw error;
        }
    }

    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url, { method: 'GET' });
    }

    async post(endpoint, data = null) {
        const options = { method: 'POST' };
        if (data) {
            options.body = JSON.stringify(data);
        }
        return this.request(endpoint, options);
    }

    async put(endpoint, data = null) {
        const options = { method: 'PUT' };
        if (data) {
            options.body = JSON.stringify(data);
        }
        return this.request(endpoint, options);
    }

    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }

    // Authentication methods
    async getGoogleAuthUrl() {
        return this.get('/auth/google');
    }

    async checkAuth() {
        return this.get('/auth/me');
    }

    async logout() {
        return this.post('/auth/logout');
    }

    // Checklist methods
    async getChecklists(page = 1, search = '') {
        return this.get('/checklists', { page, search });
    }

    async getChecklist(id) {
        return this.get(`/checklists/${id}`);
    }

    async createChecklist(data) {
        return this.post('/checklists', data);
    }

    async updateChecklist(id, data) {
        return this.put(`/checklists/${id}`, data);
    }

    async deleteChecklist(id) {
        return this.delete(`/checklists/${id}`);
    }

    async restoreChecklist(id) {
        return this.post(`/checklists/${id}/restore`);
    }

    async getSharedChecklists() {
        return this.get('/checklists/shared');
    }

    // Section methods
    async createSection(checklistId, data) {
        return this.post(`/checklists/${checklistId}/sections`, data);
    }

    async updateSection(checklistId, sectionId, data) {
        return this.put(`/checklists/${checklistId}/sections/${sectionId}`, data);
    }

    async deleteSection(checklistId, sectionId) {
        return this.delete(`/checklists/${checklistId}/sections/${sectionId}`);
    }

    async reorderSections(checklistId, sectionIds) {
        return this.post(`/checklists/${checklistId}/sections/reorder`, { section_ids: sectionIds });
    }

    // Item methods
    async createItem(checklistId, sectionId, data) {
        return this.post(`/checklists/${checklistId}/sections/${sectionId}/items`, data);
    }

    async updateItem(checklistId, itemId, data) {
        return this.put(`/checklists/${checklistId}/items/${itemId}`, data);
    }

    async deleteItem(checklistId, itemId) {
        return this.delete(`/checklists/${checklistId}/items/${itemId}`);
    }

    async toggleItem(checklistId, itemId, completed) {
        return this.put(`/checklists/${checklistId}/items/${itemId}`, { completed });
    }

    async reorderItems(checklistId, sectionId, itemIds) {
        return this.post(`/checklists/${checklistId}/sections/${sectionId}/items/reorder`, { item_ids: itemIds });
    }

    // Tag methods
    async getTags() {
        return this.get('/tags');
    }

    async createTag(data) {
        return this.post('/tags', data);
    }

    async updateTag(id, data) {
        return this.put(`/tags/${id}`, data);
    }

    async deleteTag(id) {
        return this.delete(`/tags/${id}`);
    }

    async addTagToItem(checklistId, itemId, tagId) {
        return this.post(`/checklists/${checklistId}/items/${itemId}/tags`, { tag_id: tagId });
    }

    async removeTagFromItem(checklistId, itemId, tagId) {
        return this.delete(`/checklists/${checklistId}/items/${itemId}/tags/${tagId}`);
    }

    // Collaboration methods
    async getCollaborators(checklistId) {
        return this.get(`/checklists/${checklistId}/collaborators`);
    }

    async inviteCollaborator(checklistId, email, role = 'viewer') {
        return this.post(`/checklists/${checklistId}/collaborators`, { email, role });
    }

    async updateCollaborator(checklistId, collaboratorId, role) {
        return this.put(`/checklists/${checklistId}/collaborators/${collaboratorId}`, { role });
    }

    async removeCollaborator(checklistId, collaboratorId) {
        return this.delete(`/checklists/${checklistId}/collaborators/${collaboratorId}`);
    }

    async generatePublicLink(checklistId) {
        return this.post(`/checklists/${checklistId}/public-link`);
    }

    async revokePublicLink(checklistId) {
        return this.delete(`/checklists/${checklistId}/public-link`);
    }

    // Template methods
    async getTemplates() {
        return this.get('/templates');
    }

    async createFromTemplate(templateId, title) {
        return this.post(`/templates/${templateId}/create`, { title });
    }

    // Import/Export methods
    async exportChecklist(checklistId, format = 'json') {
        return this.get(`/checklists/${checklistId}/export`, { format });
    }

    async importChecklist(data, format = 'json') {
        return this.post('/checklists/import', { data, format });
    }

    // Admin methods (admin users only)
    async getUsers(page = 1, search = '') {
        return this.get('/admin/users', { page, search });
    }

    async updateUser(userId, data) {
        return this.put(`/admin/users/${userId}`, data);
    }

    async deleteUser(userId) {
        return this.delete(`/admin/users/${userId}`);
    }

    async getStats() {
        return this.get('/admin/stats');
    }

    async getAuditLogs(page = 1) {
        return this.get('/admin/audit-logs', { page });
    }

    async createTemplate(data) {
        return this.post('/admin/templates', data);
    }

    async updateTemplate(id, data) {
        return this.put(`/admin/templates/${id}`, data);
    }

    async deleteTemplate(id) {
        return this.delete(`/admin/templates/${id}`);
    }
}