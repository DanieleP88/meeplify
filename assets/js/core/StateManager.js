// Simple State Manager for Meeplify
export class StateManager {
    constructor() {
        this.state = {};
        this.listeners = {};
    }

    // Set a value in the state
    set(key, value) {
        const oldValue = this.state[key];
        this.state[key] = value;
        
        // Notify listeners if value changed
        if (oldValue !== value) {
            this.notify(key, value, oldValue);
        }
    }

    // Get a value from the state
    get(key) {
        return this.state[key];
    }

    // Subscribe to state changes
    subscribe(key, callback) {
        if (!this.listeners[key]) {
            this.listeners[key] = [];
        }
        this.listeners[key].push(callback);

        // Return unsubscribe function
        return () => {
            const index = this.listeners[key].indexOf(callback);
            if (index > -1) {
                this.listeners[key].splice(index, 1);
            }
        };
    }

    // Notify all listeners for a key
    notify(key, value, oldValue) {
        if (this.listeners[key]) {
            this.listeners[key].forEach(callback => {
                try {
                    callback(value, oldValue);
                } catch (error) {
                    console.error('State listener error:', error);
                }
            });
        }
    }

    // Update nested object properties
    update(key, updates) {
        const current = this.get(key) || {};
        const updated = { ...current, ...updates };
        this.set(key, updated);
    }

    // Update array by adding/removing items
    updateArray(key, action, item, predicate = null) {
        const array = this.get(key) || [];
        let newArray = [...array];

        switch (action) {
            case 'add':
                newArray.push(item);
                break;
            case 'remove':
                if (predicate) {
                    newArray = newArray.filter(predicate);
                } else {
                    const index = newArray.indexOf(item);
                    if (index > -1) {
                        newArray.splice(index, 1);
                    }
                }
                break;
            case 'update':
                if (predicate) {
                    const index = newArray.findIndex(predicate);
                    if (index > -1) {
                        newArray[index] = { ...newArray[index], ...item };
                    }
                } else {
                    newArray = newArray.map(existing => 
                        existing.id === item.id ? { ...existing, ...item } : existing
                    );
                }
                break;
            case 'replace':
                newArray = Array.isArray(item) ? item : [item];
                break;
        }

        this.set(key, newArray);
    }

    // Get all state (for debugging)
    getAll() {
        return { ...this.state };
    }

    // Clear all state
    clear() {
        this.state = {};
        this.listeners = {};
    }

    // Clear specific key
    clearKey(key) {
        delete this.state[key];
        this.notify(key, undefined, this.state[key]);
    }
}