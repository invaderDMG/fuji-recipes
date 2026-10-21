#!/usr/bin/env bash
# Prepara un droplet Ubuntu 24.04 para Fuji Recipes, como root:
#
#   ssh root@<ip>
#   curl -fsSLO https://raw.githubusercontent.com/invaderDMG/fuji-recipes/main/deploy/provision.sh
#   DOMAIN=fuji.tudominio.com EMAIL=tu@correo.com bash provision.sh
#
# Crea el usuario «fuji» (con las claves SSH de root), instala PHP 8.4,
# Tesseract y Composer, clona el repo en /home/fuji/fuji-recipes, configura un
# pool de PHP-FPM propio y pide el certificado HTTPS. El DNS de DOMAIN tiene
# que apuntar ya al droplet. Se puede repetir sin miedo.
#
# Servidor web: si Apache ya está en marcha (el droplet sirve otras webs), añade
# un vhost y no toca nada más: ni el resto de sitios, ni el PHP que usan, ni el
# firewall. Si no, instala Nginx y abre SSH, 80 y 443 con ufw.
set -euo pipefail

: "${DOMAIN:?Falta DOMAIN=fuji.tudominio.com}"
: "${EMAIL:?Falta EMAIL=tu@correo.com (avisos del certificado HTTPS)}"
APP_USER=fuji
APP_DIR=/home/$APP_USER/fuji-recipes
REPO=${REPO:-https://github.com/invaderDMG/fuji-recipes.git}

if systemctl is-active --quiet apache2; then WEB=apache; else WEB=nginx; fi
echo "Servidor web: $WEB"

export DEBIAN_FRONTEND=noninteractive
echo 'Acquire::Retries "5";' > /etc/apt/apt.conf.d/80-retries  # el PPA a veces da 503
apt-get update
apt-get install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt-get update
apt-get install -y certbot \
    php8.4-fpm php8.4-cli php8.4-sqlite3 php8.4-intl php8.4-mbstring php8.4-xml \
    php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath \
    sqlite3 python3-venv tesseract-ocr make rsync unzip git
if [ "$WEB" = apache ]; then
    apt-get install -y python3-certbot-apache
else
    apt-get install -y nginx python3-certbot-nginx ufw
fi

# También si hay un Composer 1 antiguo: no funciona con PHP 8.4 ni con Packagist
if ! composer --version 2>/dev/null | grep -q 'Composer version 2'; then
    curl -sS https://getcomposer.org/installer | php8.4 -- --install-dir=/usr/local/bin --filename=composer
fi

# Usuario de la app: dueño del código y de los datos, y usuario del pool de PHP
if ! id "$APP_USER" >/dev/null 2>&1; then
    adduser --disabled-password --gecos '' "$APP_USER"
fi
install -d -m 700 -o "$APP_USER" -g "$APP_USER" /home/$APP_USER/.ssh
touch /home/$APP_USER/.ssh/authorized_keys
# Añade las claves de root que falten, sin duplicar ni quitar las que ya tenga
if [ -s /root/.ssh/authorized_keys ]; then
    sort -u /root/.ssh/authorized_keys /home/$APP_USER/.ssh/authorized_keys -o /home/$APP_USER/.ssh/authorized_keys
fi
chown "$APP_USER:$APP_USER" /home/$APP_USER/.ssh/authorized_keys
chmod 600 /home/$APP_USER/.ssh/authorized_keys
chmod 711 /home/$APP_USER   # el servidor web tiene que llegar a web/public

if [ -d "$APP_DIR/.git" ]; then
    su "$APP_USER" -c "git -C $APP_DIR pull --ff-only"
else
    su "$APP_USER" -c "git clone $REPO $APP_DIR"
fi
HERE=$APP_DIR/deploy
render() {
    sed -e "s|__DOMAIN__|$DOMAIN|g" -e "s|__APP_DIR__|$APP_DIR|g" -e "s|__APP_USER__|$APP_USER|g" "$1"
}

# reload y no restart: los demás pools (otras webs) no se cortan
render "$HERE/php-fpm.conf" > /etc/php/8.4/fpm/pool.d/$APP_USER.conf
php-fpm8.4 -t
systemctl reload php8.4-fpm

if [ "$WEB" = apache ]; then
    a2enmod -q proxy_fcgi setenvif rewrite headers
    render "$HERE/apache.conf" > /etc/apache2/sites-available/fuji-recipes.conf
    a2ensite -q fuji-recipes
    apache2ctl configtest
    systemctl reload apache2
    certbot --apache -d "$DOMAIN" -m "$EMAIL" --agree-tos --non-interactive --redirect
else
    render "$HERE/nginx.conf" > /etc/nginx/sites-available/fuji-recipes
    ln -sf /etc/nginx/sites-available/fuji-recipes /etc/nginx/sites-enabled/fuji-recipes
    rm -f /etc/nginx/sites-enabled/default
    nginx -t
    systemctl reload nginx

    ufw allow OpenSSH
    ufw allow 'Nginx Full'
    ufw --force enable

    certbot --nginx -d "$DOMAIN" -m "$EMAIL" --agree-tos --non-interactive --redirect
fi

echo
if [ ! -s /home/$APP_USER/.ssh/authorized_keys ]; then
    echo "AVISO: $APP_USER no tiene claves SSH. Desde el Mac:"
    echo "  cat ~/.ssh/id_ed25519.pub | ssh root@$DOMAIN 'cat >> /home/$APP_USER/.ssh/authorized_keys'"
fi
echo "Listo. Ahora, desde el Mac: make push-data DROPLET=$APP_USER@$DOMAIN"
