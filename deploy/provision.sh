#!/usr/bin/env bash
# Prepara un droplet Ubuntu 24.04 para Fuji Recipes. Una sola vez, como root:
#
#   ssh root@<ip>
#   curl -fsSLO https://raw.githubusercontent.com/invaderDMG/fuji-recipes/main/deploy/provision.sh
#   DOMAIN=fuji.tudominio.com EMAIL=tu@correo.com bash provision.sh
#
# Crea el usuario «fuji» (con tus claves SSH de root), instala PHP 8.4, Nginx,
# Tesseract y Composer, clona el repo en /home/fuji/fuji-recipes, configura un
# pool de PHP-FPM propio y pide el certificado HTTPS. El DNS de DOMAIN tiene
# que apuntar ya al droplet.
set -euo pipefail

: "${DOMAIN:?Falta DOMAIN=fuji.tudominio.com}"
: "${EMAIL:?Falta EMAIL=tu@correo.com (avisos del certificado HTTPS)}"
APP_USER=fuji
APP_DIR=/home/$APP_USER/fuji-recipes
REPO=${REPO:-https://github.com/invaderDMG/fuji-recipes.git}

export DEBIAN_FRONTEND=noninteractive
echo 'Acquire::Retries "5";' > /etc/apt/apt.conf.d/80-retries  # el PPA a veces da 503
apt-get update
apt-get install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt-get update
apt-get install -y nginx certbot python3-certbot-nginx \
    php8.4-fpm php8.4-cli php8.4-sqlite3 php8.4-intl php8.4-mbstring php8.4-xml \
    php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath \
    sqlite3 python3-venv tesseract-ocr make rsync unzip git ufw

if ! command -v composer >/dev/null; then
    curl -sS https://getcomposer.org/installer | php8.4 -- --install-dir=/usr/local/bin --filename=composer
fi

# Usuario de la app: dueño del código y de los datos, y usuario del pool de PHP
if ! id "$APP_USER" >/dev/null 2>&1; then
    adduser --disabled-password --gecos '' "$APP_USER"
    install -d -m 700 -o "$APP_USER" -g "$APP_USER" /home/$APP_USER/.ssh
    install -m 600 -o "$APP_USER" -g "$APP_USER" /root/.ssh/authorized_keys /home/$APP_USER/.ssh/authorized_keys
fi
chmod 711 /home/$APP_USER   # Nginx tiene que llegar a web/public
if [ ! -d "$APP_DIR/.git" ]; then
    su "$APP_USER" -c "git clone $REPO $APP_DIR"
fi
HERE=$APP_DIR/deploy

sed "s|__APP_USER__|$APP_USER|g" "$HERE/php-fpm.conf" > /etc/php/8.4/fpm/pool.d/$APP_USER.conf
systemctl restart php8.4-fpm

sed -e "s|__DOMAIN__|$DOMAIN|g" -e "s|__APP_DIR__|$APP_DIR|g" -e "s|__APP_USER__|$APP_USER|g" \
    "$HERE/nginx.conf" > /etc/nginx/sites-available/fuji-recipes
ln -sf /etc/nginx/sites-available/fuji-recipes /etc/nginx/sites-enabled/fuji-recipes
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

certbot --nginx -d "$DOMAIN" -m "$EMAIL" --agree-tos --non-interactive --redirect

echo
echo "Listo. Ahora, desde el Mac: make push-data DROPLET=$APP_USER@$DOMAIN"
