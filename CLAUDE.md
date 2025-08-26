# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview
Meeplify is a collaborative checklist web application built with PHP 8.x, MySQL, and vanilla JavaScript. It features Google OAuth authentication, role-based access control, and admin panel functionality.

## Commands

### Testing
- `php tests/smoke.php` - Run basic smoke tests

### Development
- Point web server to project root with PHP 8.2+
- Configure .env file with database and Google OAuth credentials
- Database setup requires manual MySQL schema creation (see DATA_MODEL documentation)
- Access application at root URL, API available at /api/*

## Architecture

### Backend Structure
- **Entry Point**: `app/api/index.php` - Main REST API router with session management and CSRF protection
- **Handlers**: `app/api/handlers/` - Request handlers organized by resource (Checklist, Auth, Admin, etc.)
- **Core Libraries**: 
  - `app/lib/DB.php` - PDO database singleton
  - `app/lib/Utils.php` - Authentication, authorization, rate limiting, and utility functions
  - `app/lib/GoogleOAuth.php` - OAuth integration
- **Admin Panel**: `app/admin/` - PHP views for user management, statistics, and thematic lists
- **Views**: `app/views/` - Shell templates for frontend and admin interfaces

### Frontend Structure
- **Assets**: Organized in `assets/css/`, `assets/js/`, `assets/img/`
- **Components**: Modular JavaScript components in `assets/js/components/`
- **Technology**: Vanilla HTML5, CSS3, and JavaScript (no frameworks)

### Database Integration
- Uses PDO with prepared statements throughout
- Role-based access: owner/collaborator/viewer permissions per checklist
- Admin-only functions for user management and system oversight
- Soft delete with 30-day recovery for checklists
- Rate limiting and CSRF protection on all mutating operations

### Authentication & Security
- Google OAuth2 for user authentication
- Session-based authentication with CSRF tokens
- Role-based access control (user/admin, owner/collaborator/viewer)
- Rate limiting per endpoint
- Audit logging for admin actions

### Business Logic
- Checklists support sections, items, tags, and collaborators
- Import/export functionality in JSON format
- Public sharing with view-only access
- Template system for admin-created checklist templates
- Limits: 100 sections and 1000 items per checklist

### Configuration
- Environment variables in `.env` for database, OAuth, and SMTP settings
- Configuration constants in `app/lib/Config.php`
- No package managers (composer/npm) - pure PHP implementation

## Key Files to Reference
- `app/api/index.php` - API routing and middleware
- `app/lib/Utils.php` - Core utility functions
- `README.md` - Setup instructions and API examples
- `tests/smoke.php` - Basic testing approach