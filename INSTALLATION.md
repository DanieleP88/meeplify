# 🚀 Meeplify - Installation Guide

Collaborative checklist application with Google OAuth authentication and Notion-style design.

## 📋 Requirements

- PHP 8.0+
- MySQL/MariaDB 5.7+
- Web server (Apache/Nginx)
- Google Cloud Console project (for OAuth)

## ⚡ Quick Installation

### 1. Database Setup

```sql
-- Create database
CREATE DATABASE meeplify CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Import schema
SOURCE database_schema.sql;
```

### 2. Environment Configuration

Create `.env` file in the root directory:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=meeplify
DB_USER=your_db_user
DB_PASS=your_db_password

# Google OAuth (Get from Google Cloud Console)
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=http://localhost/api/?callback=google

# Application
APP_URL=http://localhost
APP_ENV=development
```

### 3. Google OAuth Setup

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create new project or select existing
3. Enable Google+ API
4. Create OAuth 2.0 credentials (Web application)
5. Add authorized redirect URI: `http://localhost/api/?callback=google`
6. Copy Client ID and Secret to `.env`

### 4. Web Server Configuration

#### Apache (.htaccess already included)

Point document root to the project folder.

#### Nginx

```nginx
server {
    listen 80;
    server_name localhost;
    root /path/to/meeplify;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /app/views/fo_shell.php;
    }

    location /api/ {
        try_files $uri $uri/ /app/api/index.php;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 5. File Permissions

```bash
chmod -R 755 assets/
chmod -R 755 app/
chmod 644 database_schema.sql
```

## 🧪 Testing

Visit `/test_app.php` to run automated tests:

- ✅ Homepage loading
- ✅ Authentication endpoints
- ✅ API structure
- ✅ Frontend assets
- ✅ Database schema

## 🎯 Usage

### First Login

1. Visit your domain (e.g., `http://localhost`)
2. Click "Accedi con Google"
3. Complete Google OAuth flow
4. You're redirected to the dashboard

### Creating Your First Admin

The first user to register automatically becomes an admin. To manually set admin role:

```sql
UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
```

## 🏗️ Architecture

### Backend Structure

```
app/
├── api/
│   ├── index.php           # Main API router
│   └── handlers/           # Request handlers
│       ├── ChecklistHandler.php
│       ├── SectionHandler.php
│       ├── ItemHandler.php
│       ├── CollaborationHandler.php
│       ├── TagHandler.php
│       ├── TemplateHandler.php
│       └── AdminHandler.php
├── lib/
│   ├── Config.php          # Configuration
│   ├── DB.php              # Database connection
│   └── Utils.php           # Utilities
├── views/
│   └── fo_shell.php        # Main HTML template
└── admin/                  # Admin panel (future)
```

### Frontend Structure

```
assets/
├── css/
│   └── main.css           # Notion-style design system
├── js/
│   └── app.js             # SPA application
└── img/                   # Images (future)
```

### Database Tables

- **users** - User accounts with Google OAuth
- **checklists** - Main checklist entities
- **sections** - Checklist sections
- **items** - Checklist items
- **collaborators** - Sharing permissions
- **tags** - Item tagging system
- **templates** - Admin-managed templates
- **audit_log** - Security audit trail

## 🔒 Security Features

- ✅ Google OAuth2 authentication
- ✅ CSRF protection
- ✅ SQL injection prevention (PDO prepared statements)
- ✅ Rate limiting
- ✅ Input validation and sanitization
- ✅ Audit logging
- ✅ Role-based access control

## 🌟 Key Features

### For Users
- **Collaborative Checklists** - Real-time collaboration
- **Notion-style Interface** - Clean, minimal design
- **Google OAuth Login** - Secure, easy authentication
- **Responsive Design** - Works on all devices
- **Tag System** - Organize items with colors and emojis
- **Public Sharing** - Share read-only checklist links
- **Import/Export** - JSON format support
- **Progress Tracking** - Visual completion indicators

### For Admins
- **User Management** - Enable/disable accounts
- **Template Creation** - Pre-built checklist templates
- **Statistics Dashboard** - Usage analytics
- **Audit Logging** - Security event tracking
- **GDPR Tools** - Data export/deletion

## 🚨 Troubleshooting

### White Page Error
- Check PHP error logs
- Verify database connection in `.env`
- Ensure proper file permissions

### Authentication Loop
- Verify Google OAuth configuration
- Check redirect URI matches exactly
- Clear browser cookies and session

### Database Errors
- Import `database_schema.sql` completely
- Verify MySQL user permissions
- Check database name and credentials

### API Errors
- Enable PHP error display in development
- Check web server URL rewriting
- Verify handler files are included

## 📈 Performance

### Optimization Tips
- Enable PHP OPCache
- Use MySQL query caching
- Implement Redis for sessions (production)
- Enable gzip compression
- Use CDN for assets (production)

### Scaling
- Database indexing is optimized for queries
- Rate limiting prevents abuse
- Soft delete with cleanup policies
- Audit log rotation recommended

## 🔧 Development

### Running Tests
```bash
php tests/smoke.php
```

### API Documentation
All endpoints use JSON and follow REST conventions:

- `GET /api/checklists` - List user checklists
- `POST /api/checklists` - Create new checklist
- `GET /api/checklists/{id}` - Get specific checklist
- `PUT /api/checklists/{id}` - Update checklist
- `DELETE /api/checklists/{id}` - Soft delete checklist

See handler files for complete API documentation.

### Contributing
1. Fork the repository
2. Create feature branch
3. Follow existing code style
4. Add appropriate tests
5. Submit pull request

---

**🎉 You're all set!** Visit your domain to start using Meeplify.

For support or feature requests, check the repository issues.