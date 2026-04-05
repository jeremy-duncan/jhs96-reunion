# JHS96 Reunion Site — Maintenance Runbook

## Overview
- **Live site:** https://judson96.org
- **Admin panel:** https://judson96.org/admin
- **GitHub repo:** https://github.com/jeremy-duncan/jhs96-reunion
- **Server:** 108.39.81.108 (Ubuntu 24, LAMP)
- **SSH user:** tachyon (sudo access)

---

## Stack
- Apache 2.4 + mod_rewrite + mod_headers
- MySQL 8 — database: `jhs96`, user: `jhs96user`
- PHP 8.3 — API at `/var/www/html/php/api.php`
- Vanilla JS + CSS frontend (no framework)
- GitHub for version control, server pulls from `main` branch

## File Structure
```
/var/www/html/
├── index.html          # Single-page app frontend
├── css/style.css       # All styling
├── js/app.js           # All frontend logic (talks to PHP API)
├── images/             # Logo, Red Campus photo
├── uploads/            # User-uploaded photos (not in git)
├── php/
│   ├── api.php         # All API endpoints (in git)
│   └── config.php      # DB credentials + admin password (NOT in git)
└── admin/
    └── index.php       # Admin panel (deployed by setup script)
```

---

## IMPORTANT: config.php is NOT in git
`php/config.php` contains DB credentials and the admin password.
It is in `.gitignore` and must never be committed.
**Always use the safe deploy command below — never plain `git reset --hard`.**

---

## Safe Deploy Command (use every time)
```bash
cd /var/www/html && sudo cp php/config.php /tmp/config.php.bak && sudo git fetch origin && sudo git reset --hard origin/main && sudo cp /tmp/config.php.bak php/config.php && sudo systemctl reload apache2 && echo "Done"
```

This backs up config.php, pulls latest from GitHub, restores config.php, reloads Apache.

---

## Changing Admin Password
```bash
sudo nano /var/www/html/php/config.php
# Change the ADMIN_PASSWORD line
```

---

## Database Access
```bash
mysql -u jhs96user -p'REDACTED' jhs96
```
Tables: `events`, `rsvps`, `updates_feed`, `photos`

---

## Common Tasks via Claude
Start a new Claude conversation and say:
> "I need to update the JHS96 reunion site at judson96.org"

Claude will have the project context saved in memory. For code changes, Claude will:
1. Edit the files locally
2. Generate a GitHub token via the browser
3. Push to GitHub
4. Delete the token immediately
5. Give you the deploy command to run on the server

---

## GitHub Push Workflow (Claude handles this)
1. Go to: https://github.com/settings/tokens/new?description=jhs96-push&scopes=repo
2. Generate token, use it to push
3. Delete token immediately after

---

## Security Notes
- `.git` directory is blocked via `.htaccess`
- HSTS is active (forces HTTPS for 1 year)
- API rate limiting: 3 RSVPs/hr, 5 updates/hr, 10 uploads/hr per IP
- Admin login: 5 attempts per 15 min before lockout
- `ServerTokens Prod` hides Apache version
- Run audits periodically by asking Claude to do a security review

---

## Useful Server Commands
```bash
# Check Apache errors
sudo tail -50 /var/log/apache2/jhs96-error.log

# Restart Apache
sudo systemctl restart apache2

# Check disk space
df -h

# MySQL backup
mysqldump -u jhs96user -p'REDACTED' jhs96 > ~/jhs96-backup-$(date +%Y%m%d).sql
```
