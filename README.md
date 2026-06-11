# carlosmartin.dev

Personal portfolio website built with **Craft CMS 5**, **Tailwind CSS** (CDN), **Alpine.js** (CDN), and **Font Awesome**.

## Project Structure

```
├── config/
│   ├── app.php              # Module bootstrap registration
│   ├── general.php          # Craft general config
│   └── routes.php           # Custom routes
├── modules/
│   └── portfoliomodule/     # Custom portfolio module
│       ├── Module.php
│       ├── console/controllers/InstallController.php
│       ├── migrations/Install.php
│       └── services/PortfolioService.php
├── templates/
│   └── index.twig           # Single-page portfolio template
├── web/
│   ├── audio/tetris-theme.mp3
│   ├── images/carlos.jpg
│   └── index.php
├── deploy.php               # Deployer deployment config
├── composer.json
└── craft                    # Craft CLI entry point
```

## CMS Structure

All sections, fields, entry types, and field layouts are created via the Install migration. No project config is used.

### Sections

| Section | Type | Entries | Handles |
|---------|------|---------|---------|
| Homepage | Single | 1 | `heroTitle`, `heroSubtitle`, `aboutHeading`, `aboutContent`, `footerTagline` |
| Skill Cards | Channel | 3 | `iconClass`, `shortDescription`, `languagesTitle`, `languagesText`, `toolsTitle`, `toolsList` |
| Experience | Channel | 4 | `company`, `role`, `period`, `location`, `expDescription` |
| Social Links | Channel | 2 | `platform`, `socialUrl`, `socialIconClass` |

### Template Queries (templates/index.twig)

```twig
{% set homepage   = craft.entries().section('homepage').one() %}
{% set skillCards  = craft.entries().section('skillCards').orderBy('title asc').all() %}
{% set experience  = craft.entries().section('experience').orderBy('postDate desc').all() %}
{% set socialLinks = craft.entries().section('socialLinks').all() %}
```

All field values fallback to defaults via Twig's `??` operator.

### Module

The `portfoliomodule` module (`modules/portfoliomodule/`) provides:

- **Install migration** (`migrations/Install.php`): creates all sections, fields, entry types, field layouts, and seeds default entries
- **Install controller** (`console/controllers/InstallController.php`): CLI commands to install/uninstall
- **PortfolioService** (`services/PortfolioService.php`): PHP getters for each section

### Console Commands

```bash
# Create all sections, fields, and seed default entries
php craft portfoliomodule/install

# Remove all sections and fields
php craft portfoliomodule/uninstall

# After updating content in Craft CP, sync project config
php craft project-config/apply
```

### Key Notes

- All URL/Link fields use `PlainText` type (not Craft's Link field) for simplicity
- Experience entries are ordered by `postDate DESC`
- Dropdown fields return `SingleOptionFieldData` objects — access value via `.value`
- In Craft 5, content is stored in `elements_sites.content` (JSON column), not `cm_content`

## Frontend

No build tooling. All assets loaded from CDN.

```
Tailwind CSS  → <script src="https://cdn.tailwindcss.com">
Alpine.js     → <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js">
Font Awesome  → <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
Google Fonts  → Inter via Google Fonts CSS API
```

All JavaScript (Tetris game, background animation, smooth scrolling) is inline in `templates/index.twig`.

### Tetris Background

- 3-layer parallax canvas animation in the hero section
- Classic Tetris pieces fall at different speeds and opacities
- 15 piece variations including rotations

### Tetris Game Overlay

- Click "Play" button to open a GameBoy-styled overlay
- Full Tetris game with keyboard controls: arrows + space for hard drop
- Korobeiniki theme music (toggle via Music button, muted by default)
- Powered by the Audio and Canvas APIs

## Deployment

### Prerequisites

- PHP 8.2+ on the server
- MySQL 8.0+
- Composer installed on the server
- SSH access configured

### First-time Server Setup

```bash
# SSH into the server and clone the repo
ssh user@your-server.com
git clone git@github.com:cmartinespinosa/carlosmartin.dev.git /var/www/your-domain

# Configure environment
cd /var/www/your-domain
cp .env.example.production .env
# Edit .env with your database credentials, security key, etc.

# Install dependencies
composer install --no-dev --optimize-autoloader

# Set permissions
chmod -R 775 storage web/cpresources
chmod 755 craft

# Run Craft setup
php craft setup/app-id
php craft setup/security-key

# Run migrations
php craft migrate/up

# Run the portfolio installer
php craft portfoliomodule/install

# Point your web server root to /var/www/your-domain/web
```

### Production Deploys with Deployer

1. **Configure `deploy.php`** with your server details:

```php
host('production')
    ->setHostname('your-server.com')
    ->setRemoteUser('your-user')
    ->setDeployPath('/var/www/your-domain')
    ->setIdentityFile('~/.ssh/id_ed25519');
```

2. **Deploy from your local machine:**

```bash
vendor/bin/dep deploy production
```

This runs:

```
deploy:prepare                # Clone repo + create release dir
deploy:vendors                # composer install --no-dev
craft:clear-caches/compiled   # Clear compiled classes
craft:migrate/all             # Run pending migrations
craft:project-config/apply    # Sync project config
craft:gc                      # Garbage collection
craft:clear-caches/all        # Clear all caches
deploy:publish                # Symlink release as current
```

Keeps the last 3 releases on the server. Rollback with:

```bash
vendor/bin/dep rollback production
```

## Environment Files

| File | Purpose |
|------|---------|
| `.env.example.dev` | Local development template |
| `.env.example.production` | Production template (if needed) |
| `.env` | Actual environment (git-ignored) |

### Key Environment Variables

```
CRAFT_APP_ID=
CRAFT_ENVIRONMENT=dev|production
CRAFT_SECURITY_KEY=
CRAFT_DEV_MODE=true|false
CRAFT_ALLOW_ADMIN_CHANGES=true|false
CRAFT_DISALLOW_ROBOTS=true|false
```

## License

Handcrafted by Carlos Martin.
