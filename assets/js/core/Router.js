// Simple Hash Router for Meeplify SPA
export class Router {
    constructor() {
        this.routes = {};
        this.currentRoute = '';
        this.params = {};
        this.isStarted = false;
    }

    // Add a route
    addRoute(path, handler) {
        this.routes[path] = handler;
    }

    // Start the router
    start() {
        if (this.isStarted) return;
        
        this.isStarted = true;
        window.addEventListener('hashchange', () => this.handleHashChange());
        window.addEventListener('load', () => this.handleHashChange());
        
        // Handle initial route
        this.handleHashChange();
    }

    // Handle hash change events
    handleHashChange() {
        const hash = window.location.hash.slice(1) || '';
        this.navigate(hash, false);
    }

    // Navigate to a route
    navigate(path, updateHash = true) {
        if (updateHash) {
            window.location.hash = `#${path}`;
            return;
        }

        // Find matching route
        const { route, params } = this.matchRoute(path);
        
        if (route && this.routes[route]) {
            this.currentRoute = route;
            this.params = params;
            
            try {
                this.routes[route](params);
            } catch (error) {
                console.error('Route handler error:', error);
            }
        } else {
            console.warn('Route not found:', path);
            // Fallback to default route if exists
            if (this.routes['']) {
                this.routes['']();
            }
        }
    }

    // Match a path to a route pattern
    matchRoute(path) {
        // First try exact match
        if (this.routes[path]) {
            return { route: path, params: {} };
        }

        // Then try pattern matching
        const routeKeys = Object.keys(this.routes);
        
        for (const routePattern of routeKeys) {
            if (!routePattern.includes(':')) continue;
            
            const params = this.extractParams(routePattern, path);
            if (params !== null) {
                return { route: routePattern, params };
            }
        }

        return { route: null, params: {} };
    }

    // Extract parameters from route pattern
    extractParams(pattern, path) {
        const patternParts = pattern.split('/');
        const pathParts = path.split('/');
        
        if (patternParts.length !== pathParts.length) {
            return null;
        }

        const params = {};
        
        for (let i = 0; i < patternParts.length; i++) {
            const patternPart = patternParts[i];
            const pathPart = pathParts[i];
            
            if (patternPart.startsWith(':')) {
                const paramName = patternPart.slice(1);
                params[paramName] = decodeURIComponent(pathPart);
            } else if (patternPart !== pathPart) {
                return null;
            }
        }

        return params;
    }

    // Get current route
    getCurrentRoute() {
        return this.currentRoute;
    }

    // Get current params
    getParams() {
        return { ...this.params };
    }

    // Get param by key
    getParam(key) {
        return this.params[key];
    }

    // Go back in history
    back() {
        window.history.back();
    }

    // Replace current route without adding to history
    replace(path) {
        const newUrl = window.location.href.split('#')[0] + `#${path}`;
        window.location.replace(newUrl);
    }

    // Build URL with parameters
    buildUrl(route, params = {}) {
        let url = route;
        
        // Replace parameter placeholders
        for (const [key, value] of Object.entries(params)) {
            url = url.replace(`:${key}`, encodeURIComponent(value));
        }
        
        return url;
    }

    // Generate navigation function for a route
    link(route, params = {}) {
        return () => this.navigate(this.buildUrl(route, params));
    }

    // Stop the router
    stop() {
        if (!this.isStarted) return;
        
        this.isStarted = false;
        window.removeEventListener('hashchange', this.handleHashChange);
        window.removeEventListener('load', this.handleHashChange);
    }
}