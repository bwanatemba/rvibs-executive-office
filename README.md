# RVIBS & ATS Executive Office Platform

A shared operating system for the RVIBS, ATS and ATS Kindergarten Executive Office, covering executive office management and the full funding lifecycle.

## Features

**Executive Office**
- 90-day outcomes with accountable owners and measurable progress
- Commitments with owner, deadline, status, blocker and evidence of completion
- Deadline change tracking with preserved original deadlines and reasons
- Ideas pipeline separating new ideas from approved assignments
- Delegated authority for routine decisions with escalation to Director
- Material exception escalations
- Weekly review and daily brief (coordinated by Executive Coordinator)
- CEO dashboard: What must I decide? What have I committed to? What material problems need my intervention?

**Funding & Resource Mobilisation**
- Institutional readiness, legal applicant routes and supporting evidence
- Opportunity discovery and verification against official sources
- Eligibility checks (eligibility, strategic fit, readiness, evidence confidence)
- Pursue, monitor or decline decisions
- Preparation capacity with named owners, resources and dated next actions
- Application preparation with technical review, financial review and approval of submission package
- Submission receipts, follow-up and outcome tracking
- Award conditions, acceptance and financial obligations
- Cash disbursements and in-kind support recorded separately
- Delivery milestones, reporting, reconciliation and closeout
- Partner relationships, stewardship and renewal opportunities

**Executive Communications Agent**
- Consolidates pending matters from email and WhatsApp
- Drafts replies and tracks follow-ups
- Suggests updates to Executive Office and funding registers
- Links related conversations to avoid duplication
- Reading, summarising and drafting only — no automatic sending

## Roles

| Role | Email | Permissions |
|------|-------|-------------|
| System Administrator | support@rvibs.ac.ke | Full access, user management, role assignment |
| Chairman & CEO / Director | director@rvibs.ac.ke, gitau.john@rvibs.ac.ke | Approve decisions, resolve exceptions, view all |
| Executive Coordinator | (assigned) | Coordinate records, follow-ups, review packs, daily brief |
| RVIBS Principal | (assigned) | Institutional outcomes, delivery, funding |
| ATS Lead | (assigned) | ATS operations and funding |
| Finance | (assigned) | Budgets, award conditions, receipts, reconciliation |

## Technology Stack

- **Backend:** PHP 7.4+ (no external dependencies, no Composer)
- **Database:** MySQL 5.7+ / MariaDB 10.3+
- **Frontend:** HTML, CSS, vanilla JavaScript
- **Auth:** Google Workspace OAuth 2.0 (rvibs.ac.ke domain)
- **Hosting:** cPanel shared hosting
- **Fonts:** Poppins (headings), Inter (body) via Google Fonts CDN
- **Colours:** #14355C (primary), #EC268F (accent)

## Installation

### 1. Upload files to cPanel
Upload all files to your cPanel hosting (e.g. `public_html/exec` or a subdomain).

### 2. Create the database
In cPanel → MySQL Databases:
1. Create a new database (e.g. `rvibs_exec_office`)
2. Create a database user and password
3. Add the user to the database with all privileges

### 3. Configure the application
Edit `config/config.php` and set:
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` — your MySQL credentials
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` — from Google Cloud Console
- `GOOGLE_REDIRECT_URI` — your domain URL + `/oauth-callback.php`
- `APP_URL` — your domain URL

### 4. Set up Google OAuth
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create or select a project
3. Enable Google+ API
4. Configure OAuth consent screen (Internal, restrict to rvibs.ac.ke)
5. Create OAuth 2.0 credentials (Web application)
6. Add your redirect URI (e.g. `https://exec.rvibs.ac.ke/oauth-callback.php`)
7. Copy Client ID and Client Secret to `config/config.php`

### 5. Run the installer
Visit `https://yourdomain.com/install.php` in your browser and click "Run Installation". This creates all database tables and seeds initial data.

### 6. Delete installer
Delete `install.php` and optionally `dev-login.php` from your server.

### 7. Log in
Visit `https://yourdomain.com/login.php` and sign in with your @rvibs.ac.ke Google Workspace account.

The system administrator (support@rvibs.ac.ke) can assign roles to other users via User Management.

## File Structure

```
rvibs-executive-office/
├── config/
│   ├── config.php          # Database + Google OAuth config
│   └── database.php        # PDO database connection
├── includes/
│   ├── auth.php            # Authentication, helpers, permissions
│   ├── google_auth.php     # Google OAuth 2.0 implementation
│   ├── header.php          # HTML head, sidebar, topbar
│   └── footer.php          # Footer, scripts
├── assets/
│   ├── css/
│   │   └── style.css       # Complete design system
│   └── js/
│       └── app.js          # Sidebar, modals, toasts, tabs
├── database/
│   └── schema.sql          # Full database schema + seed data
├── .htaccess               # Security headers, directory protection
├── install.php             # One-time installer (delete after use)
├── index.php               # Redirects to login or dashboard
├── login.php               # Google login page
├── oauth-callback.php      # Google OAuth callback
├── dev-login.php           # Dev login (remove in production)
├── logout.php              # Session destroy
├── dashboard.php           # CEO dashboard
├── outcomes.php            # 90-day outcomes
├── commitments.php         # Commitments + deadline tracking
├── ideas.php               # Ideas pipeline
├── decisions.php           # Delegated authority
├── escalations.php         # Material exceptions
├── weekly-review.php       # Weekly review
├── daily-brief.php         # Daily brief
├── funding-readiness.php   # Institutional readiness
├── funding-opportunities.php # Opportunity lifecycle + eligibility
├── funding-applications.php # Applications + reviews + submission
├── funding-awards.php      # Awards + disbursements
├── funding-closeout.php    # Delivery milestones + closeout
├── partners.php            # Partner relationships
├── communications.php      # Communications Agent
├── admin-users.php         # User management (system admin)
├── admin-settings.php      # App settings (system admin)
└── audit-log.php           # Audit log (system admin)
```

## Security Notes

- All database queries use PDO prepared statements (SQL injection protection)
- CSRF tokens on all POST forms
- Google Workspace domain restriction (rvibs.ac.ke only)
- Session-based authentication with secure cookie settings
- Audit logging on all actions
- Config files protected from direct web access via .htaccess
- Role-based access control on every page
