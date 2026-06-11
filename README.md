# carlosmartin.dev

Personal portfolio website built with **Craft CMS 5**, **Tailwind CSS** (CDN), **Alpine.js** (CDN), and **Font Awesome**.

## Table of Contents

- [Project Structure](#project-structure)
- [CMS Structure](#cms-structure)
  - [Sections](#sections)
  - [Template Queries](#template-queries)
  - [Module](#module)
  - [Console Commands](#console-commands)
  - [Key Notes](#key-notes)
- [Frontend](#frontend)
  - [Tetris Background](#tetris-background)
  - [Tetris Game Overlay](#tetris-game-overlay)
- [Deployment](#deployment)
  - [Manual Server Setup](#manual-server-setup)
  - [Deployer](#production-deploys-with-deployer)
- [Environment Files](#environment-files)
- [License](#license)

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

### Manual Server Setup

Complete walkthrough from a bare Ubuntu 26.04 VPS to a running Craft CMS site.

#### 1. System Dependencies

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server composer git unzip curl certbot python3-certbot-nginx acl
```

#### 2. PHP 8.5 + Extensions

Ubuntu 26.04 ships with PHP 8.5 natively:

```bash
sudo apt install -y php8.5-fpm php8.5-cli php8.5-mysql php8.5-gd php8.5-intl \
    php8.5-zip php8.5-mbstring php8.5-bcmath php8.5-curl php8.5-xml php8.5-soap php8.5-bz2
```

#### 3. MySQL Database

```bash
sudo mysql -e "CREATE DATABASE my_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
PASS=$(openssl rand -base64 24)
sudo mysql -e "CREATE USER 'craft'@'localhost' IDENTIFIED BY '${PASS}';"
sudo mysql -e "GRANT ALL PRIVILEGES ON my_database.* TO 'craft'@'localhost'; FLUSH PRIVILEGES;"
echo "DB_PASSWORD=${PASS}"
```

#### 4. Clone & Configure

```bash
sudo mkdir -p /var/www && sudo chown ubuntu:ubuntu /var/www
git clone git@github.com:cmartinespinosa/carlosmartin.dev.git /var/www/carlosmartin.dev
cd /var/www/carlosmartin.dev
cp .env.example.production .env
```

Edit `.env`:

```
CRAFT_APP_ID=MyApp
CRAFT_ENVIRONMENT=production
CRAFT_DB_DATABASE=my_database
CRAFT_DB_USER=craft
CRAFT_DB_PASSWORD=<generated-password>
CRAFT_SECURITY_KEY=
CRAFT_DEV_MODE=false
CRAFT_ALLOW_ADMIN_CHANGES=false
CRAFT_DISALLOW_ROBOTS=false
```

#### 5. Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
mkdir -p storage/runtime storage/logs storage/backups
chmod -R 777 storage
php craft setup/security-key
```

#### 6. Install Craft CMS

```bash
# Remove project config if exists (fresh install)
rm -f config/project/project.yaml

php craft install/craft --interactive=0 \
    --email="admin@example.com" \
    --username="admin" \
    --password="YourPassword123!" \
    --site-name="Carlos Martin" \
    --site-url="https://carlosmartin.dev" \
    --language="en-US"

# Apply the portfolio module (creates sections, fields, seeds content)
php craft portfoliomodule/install
```

#### 7. Nginx Config

Create `/etc/nginx/sites-available/carlosmartin.dev`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name carlosmartin.dev www.carlosmartin.dev;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name carlosmartin.dev www.carlosmartin.dev;

    ssl_certificate /etc/letsencrypt/live/carlosmartin.dev/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/carlosmartin.dev/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    root /var/www/carlosmartin.dev/web;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_HOST $host;
    }

    location = /robots.txt {
        allow all;
        log_not_found off;
        access_log off;
    }

    location ~ /\. { deny all; }

    location ~* \.(jpg|jpeg|gif|png|ico|css|js|svg|webp|woff|woff2|ttf|eot|mp3|wav)$ {
        expires max;
        log_not_found off;
    }
}
```

Enable and test:

```bash
sudo ln -sf /etc/nginx/sites-available/carlosmartin.dev /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl restart nginx
```

#### 8. SSL (Let's Encrypt)

```bash
sudo certbot --nginx -d carlosmartin.dev -d www.carlosmartin.dev \
    --non-interactive --agree-tos -m admin@example.com
```

#### 9. Permissions

```bash
sudo chown -R www-data:www-data storage web/uploads
sudo chmod -R 755 storage web/uploads
```

#### 10. Verify

- Visit `https://carlosmartin.dev`
- Craft CP: `https://carlosmartin.dev/admin`

### Production Deploys with Deployer

#### Prerequisites

- SSH key on the server (added as deploy key to GitHub repo)
- Deploy user on the server with sudo access
- Composer + PHP on the server

#### Configure `deploy.php`

```php
host('production')
    ->setHostname('your-server.com')
    ->setRemoteUser('deploy')
    ->setDeployPath('/var/www/carlosmartin.dev')
    ->setIdentityFile('~/.ssh/id_ed25519');
```

#### Deploy

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

Keeps the last 3 releases. Rollback with:

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
