# Law CRM - Law Firm Lead Management

## Overview
Lead management CRM for law firms. Structured PHP application with Alpine.js reactive frontend, proper auth, CSRF protection, and separated concerns.

## Tech Stack
- **Backend:** PHP 7.x, MySQL/MariaDB via PDO
- **Frontend:** Tailwind CSS (CDN), Alpine.js 3.x (CDN)
- **Auth:** Email/password with `password_hash`/`password_verify`, PHP sessions
- **Security:** CSRF tokens, prepared statements, input validation, XSS sanitization
- **Deployment:** FTP to Plesk-hosted VPS

## File Structure
```
law-crm.com/
├── index.php               # Entry point — routes to login or dashboard
├── .env                    # Credentials (gitignored)
├── .env.example            # Template for .env
├── .htaccess               # URL rewriting + file protection
├── config/
│   └── database.php        # PDO connection + .env loader
├── api/
│   ├── router.php          # API request router
│   ├── auth.php            # Login/logout endpoints
│   └── leads.php           # Lead CRUD (GET/POST/PUT/DELETE)
├── views/
│   ├── login.php           # Login page (Tailwind)
│   └── dashboard.php       # Main CRM UI (Alpine.js)
├── includes/
│   └── functions.php       # Shared helpers (sanitize, validate, CSRF, auth)
├── public/
│   └── js/app.js           # Alpine.js crmApp() component
├── migrations/
│   ├── 001_schema.sql      # Database schema (users + leads tables)
│   └── setup.php           # One-time setup script (creates admin user)
└── dbconnect.php           # LEGACY — kept for reference, gitignored
```

## Database
- **Name:** `admin_law_firm_crm`
- **Host:** localhost
- **Tables:**
  - `users` — id, name, email, password_hash, created_at, updated_at
  - `leads` — id, name, email, phone, practice_area, status, score, created_at, updated_at

### Practice Areas
Criminal Defense, Personal Injury, Family Law, Estate Planning, Business Law, Real Estate Law

### Lead Statuses
New, In Progress, Closed

## API Endpoints
All API routes go through `/api/router.php` via `.htaccess` rewrite.

| Method | Route | Auth | Purpose |
|--------|-------|------|---------|
| POST | `/api/auth/login` | No | Login (returns CSRF token) |
| POST | `/api/auth/logout` | No | Destroy session |
| GET | `/api/leads` | Yes | List leads (paginated, filterable) |
| POST | `/api/leads` | Yes | Create lead |
| PUT | `/api/leads` | Yes | Update lead |
| DELETE | `/api/leads` | Yes | Delete lead |

### Query Parameters (GET /api/leads)
- `page` — Page number (default: 1)
- `limit` — Results per page (default: 50, max: 100)
- `search` — Search name/email/phone
- `status` — Filter by status
- `practice_area` — Filter by practice area

## Setup
1. Copy `.env.example` to `.env` and fill in database credentials
2. Run `php migrations/setup.php` to create tables and admin user
3. Login with `admin@law-crm.com` / `changeme123`
4. Change the password immediately
5. Delete `migrations/setup.php` from the server

## Security
- Passwords hashed with `password_hash(PASSWORD_DEFAULT)`
- CSRF tokens on all state-changing API calls
- All SQL uses prepared statements
- Input validated and sanitized before storage
- `.htaccess` blocks direct access to config/, includes/, migrations/
- `.env` blocked by `.htaccess` and `.gitignore`
