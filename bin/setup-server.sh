#!/bin/bash
# ==========================================
# Ubuntu 26.04 VPS Setup for Craft CMS 5
# Run as root or with sudo
# ==========================================

set -e

DOMAIN="carlosmartin.dev"
DEPLOY_USER="deploy"
PHP_VERSION="8.3"

echo "=== 1. System update ==="
apt update && apt upgrade -y

echo "=== 2. Install Nginx ==="
apt install -y nginx
systemctl enable nginx
systemctl start nginx

echo "=== 3. Install PHP $PHP_VERSION & extensions ==="
apt install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt update

apt install -y \
    php$PHP_VERSION-fpm \
    php$PHP_VERSION-cli \
    php$PHP_VERSION-mysql \
    php$PHP_VERSION-gd \
    php$PHP_VERSION-imagick \
    php$PHP_VERSION-intl \
    php$PHP_VERSION-zip \
    php$PHP_VERSION-mbstring \
    php$PHP_VERSION-bcmath \
    php$PHP_VERSION-curl \
    php$PHP_VERSION-xml \
    php$PHP_VERSION-soap

systemctl enable php$PHP_VERSION-fpm
systemctl start php$PHP_VERSION-fpm

echo "=== 4. Install MySQL 8.0 ==="
apt install -y mysql-server
systemctl enable mysql
systemctl start mysql

# Secure installation - you'll be prompted
mysql_secure_installation

echo "=== 5. Install Composer ==="
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"

echo "=== 6. Install Git & other tools ==="
apt install -y git unzip curl acl certbot python3-certbot-nginx

echo "=== 7. Create deploy user ==="
adduser --disabled-password --gecos "" $DEPLOY_USER
usermod -aG www-data $DEPLOY_USER

# Set up SSH key for deployer
mkdir -p /home/$DEPLOY_USER/.ssh
touch /home/$DEPLOY_USER/.ssh/authorized_keys
chmod 700 /home/$DEPLOY_USER/.ssh
chmod 600 /home/$DEPLOY_USER/.ssh/authorized_keys
chown -R $DEPLOY_USER:$DEPLOY_USER /home/$DEPLOY_USER/.ssh

echo "=== 8. Create project directories ==="
mkdir -p /var/www/$DOMAIN
chown -R $DEPLOY_USER:www-data /var/www/$DOMAIN
chmod -R 775 /var/www/$DOMAIN

echo "=== 9. Configure PHP-FPM ==="
sed -i "s/upload_max_filesize = .*/upload_max_filesize = 32M/" /etc/php/$PHP_VERSION/fpm/php.ini
sed -i "s/post_max_size = .*/post_max_size = 64M/" /etc/php/$PHP_VERSION/fpm/php.ini
sed -i "s/memory_limit = .*/memory_limit = 256M/" /etc/php/$PHP_VERSION/fpm/php.ini
sed -i "s/max_execution_time = .*/max_execution_time = 120/" /etc/php/$PHP_VERSION/fpm/php.ini

systemctl restart php$PHP_VERSION-fpm

echo "=== 10. Configure Nginx virtual host ==="

cat > /etc/nginx/sites-available/$DOMAIN << 'NGINX_CONF'
server {
    listen 80;
    listen [::]:80;
    server_name carlosmartin.dev www.carlosmartin.dev;
    root /var/www/carlosmartin.dev/web;

    index index.php;

    charset utf-8;

    location / {
        try_files $uri/index.html $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico {
        access_log off;
        log_not_found off;
    }

    location = /robots.txt {
        access_log off;
        log_not_found off;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param HTTP_HOST $host;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Deny access to Craft config files
    location ~ /craft\.(json|php) {
        deny all;
        return 404;
    }

    # Deny access to storage
    location ~ /storage/ {
        deny all;
        return 404;
    }
}
NGINX_CONF

ln -sf /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test and reload
nginx -t && systemctl reload nginx

echo "=== 11. Set up SSL with Certbot ==="
certbot --nginx -d $DOMAIN -d www.$DOMAIN --non-interactive --agree-tos -m cmartinespinosa@gmail.com

echo "=== 12. Set ACL permissions for storage ==="
cd /var/www/$DOMAIN
setfacl -R -m u:$DEPLOY_USER:rwx storage web/cpresources
setfacl -R -m u:www-data:rwx storage web/cpresources
setfacl -dR -m u:$DEPLOY_USER:rwx storage web/cpresources
setfacl -dR -m u:www-data:rwx storage web/cpresources

echo ""
echo "=========================================="
echo "  SETUP COMPLETE"
echo "=========================================="
echo ""
echo "Next steps:"
echo "  1. Add your public SSH key to /home/$DEPLOY_USER/.ssh/authorized_keys"
echo "  2. From your local machine, run:"
echo "     ssh deploy@<server-ip>"
echo "  3. Clone the repo:"
echo "     git clone git@github.com:cmartinespinosa/carlosmartin.dev.git /var/www/$DOMAIN"
echo "  4. Copy .env and configure:"
echo "     cp .env.example.production /var/www/$DOMAIN/.env"
echo "  5. Set up the database:"
echo "     mysql -u root -p -e \"CREATE DATABASE carlosmartin_dev;\""
echo "  6. Install dependencies:"
echo "     cd /var/www/$DOMAIN && composer install --no-dev --optimize-autoloader"
echo "  7. Run Craft setup:"
echo "     php craft setup/app-id"
echo "     php craft setup/security-key"
echo "     php craft migrate/up"
echo "     php craft portfoliomodule/install"
echo "  8. Update deploy.php with your server details"
echo "  9. Deploy from local: vendor/bin/dep deploy production"
echo "=========================================="
