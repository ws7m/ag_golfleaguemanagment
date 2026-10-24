#!/usr/bin/env bash
#
# AG Golf League Management - Linux installer
#
# Downloads the league software from GitHub, installs everything it needs
# (PHP and its extensions, Composer, a web server), sets it up and starts it.
# Run it again later to update to the newest version: your settings (.env)
# and database are kept.
#
# Works on Debian, Ubuntu, Fedora, and RHEL/Rocky/AlmaLinux 9 or newer.
#
# Usage (as root, or with sudo):
#   sudo bash install.sh
#   sudo bash install.sh --domain golf.example.com --https you@example.com
#
# Options:
#   --domain NAME      Web address the site is reached at (e.g. golf.example.com).
#                      Without it the site answers on this machine's IP address.
#   --https EMAIL      Get a free HTTPS certificate from Let's Encrypt (needs
#                      --domain pointing at this machine, and ports 80/443 open).
#   --dir PATH         Where to install (default /var/www/ag_golfleague).
#   --timezone ZONE    League timezone, e.g. America/Denver (default: this
#                      machine's timezone).
#   --admin LOGIN      Login for the first admin account (default admin1).
#   --repo URL         Git repository (default the GitHub repository below).
#   --branch NAME      Branch to install (default main).
#   -y, --yes          Don't ask questions; use the defaults. The admin password
#                      must then be given as ADMIN_PASSWORD in the environment.
#   -h, --help         Show this help.
#
# The admin password is asked for (hidden) on a new install, or read from the
# ADMIN_PASSWORD environment variable. It is never written to a file.

set -euo pipefail

REPO_URL="https://github.com/ws7m/ag_golfleaguemanagment.git"
BRANCH="main"
APP_DIR="/var/www/ag_golfleague"
DOMAIN=""
HTTPS_EMAIL=""
TIMEZONE=""
ADMIN_LOGIN="admin1"
ASSUME_YES=0
SITE_NAME="ag_golfleague"
MIN_PHP="8.2"

# ---------------------------------------------------------------------------
# Output helpers
# ---------------------------------------------------------------------------

step() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
info() { printf '    %s\n' "$*"; }
warn() { printf '\033[1;33mWarning:\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31mError:\033[0m %s\n' "$*" >&2; exit 1; }

usage() { sed -n '2,/^$/p' "$0" | sed -e 's/^# \{0,1\}//' -e '/^!/d'; exit 0; }

# ---------------------------------------------------------------------------
# Options
# ---------------------------------------------------------------------------

while [[ $# -gt 0 ]]; do
    case "$1" in
        --domain)   DOMAIN="${2:-}"; shift 2 ;;
        --https)    HTTPS_EMAIL="${2:-}"; shift 2 ;;
        --dir)      APP_DIR="${2:-}"; shift 2 ;;
        --timezone) TIMEZONE="${2:-}"; shift 2 ;;
        --admin)    ADMIN_LOGIN="${2:-}"; shift 2 ;;
        --repo)     REPO_URL="${2:-}"; shift 2 ;;
        --branch)   BRANCH="${2:-}"; shift 2 ;;
        -y|--yes)   ASSUME_YES=1; shift ;;
        -h|--help)  usage ;;
        *)          die "Unknown option: $1 (see --help)" ;;
    esac
done

[[ $EUID -eq 0 ]] || die "Run this as root, e.g.: sudo bash $0"
[[ -n "$APP_DIR" && "$APP_DIR" == /* ]] || die "--dir must be a full path, e.g. /var/www/ag_golfleague"
[[ -z "$HTTPS_EMAIL" || -n "$DOMAIN" ]] || die "--https needs --domain (the name people type to reach the site)."
[[ "$ADMIN_LOGIN" =~ ^[A-Za-z0-9]+$ ]] || die "--admin can only contain letters and numbers."
if [[ -n "$DOMAIN" && ! "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]]; then
    die "--domain should be just the name, e.g. golf.example.com (no http:// or slashes)."
fi

# ---------------------------------------------------------------------------
# Which Linux is this?
# ---------------------------------------------------------------------------

step "Checking this machine"
[[ -r /etc/os-release ]] || die "Can't tell which Linux this is (/etc/os-release is missing)."
# shellcheck disable=SC1091
. /etc/os-release
OS_ID="${ID:-}"
OS_LIKE="${ID_LIKE:-}"
OS_VERSION="${VERSION_ID:-}"
OS_CODENAME="${VERSION_CODENAME:-}"

if command -v apt-get >/dev/null 2>&1; then
    PKG="apt"
elif command -v dnf >/dev/null 2>&1; then
    PKG="dnf"
else
    die "This installer supports Debian/Ubuntu (apt) and Fedora/RHEL (dnf). Found neither."
fi
info "${PRETTY_NAME:-$OS_ID $OS_VERSION} (package manager: $PKG)"

pkg_install() {
    if [[ $PKG == apt ]]; then
        DEBIAN_FRONTEND=noninteractive apt-get install -y -q "$@"
    else
        dnf install -y -q "$@"
    fi
}

pkg_available() {
    if [[ $PKG == apt ]]; then
        apt-cache show "$1" >/dev/null 2>&1
    else
        dnf -q list --available "$1" >/dev/null 2>&1 || rpm -q "$1" >/dev/null 2>&1
    fi
}

is_installed() {
    if [[ $PKG == apt ]]; then
        dpkg -s "$1" >/dev/null 2>&1
    else
        rpm -q "$1" >/dev/null 2>&1
    fi
}

# ---------------------------------------------------------------------------
# Basic tools
# ---------------------------------------------------------------------------

step "Installing basic tools (git, curl, unzip, openssl)"
if [[ $PKG == apt ]]; then
    apt-get update -q
    pkg_install ca-certificates curl git unzip openssl lsb-release gnupg
else
    pkg_install ca-certificates curl git unzip openssl tar
fi

# ---------------------------------------------------------------------------
# PHP 8.2 or newer
# ---------------------------------------------------------------------------

version_ge() { [[ "$(printf '%s\n%s\n' "$2" "$1" | sort -V | head -n1)" == "$2" ]]; }

# The PHP version the package manager would install, e.g. "8.2".
candidate_php_version() {
    local v=""
    if [[ $PKG == apt ]]; then
        v="$(apt-cache policy php-cli 2>/dev/null | awk '/Candidate:/ {print $2}' | sed -E 's/^[0-9]+://; s/^([0-9]+\.[0-9]+).*/\1/')"
        # The "php-cli" package's version is a Debian number such as 2:8.2+93;
        # the real PHP version is in the package it depends on (php8.2-cli).
        local dep
        dep="$(apt-cache depends php-cli 2>/dev/null | grep -oE 'php[0-9]+\.[0-9]+-cli' | head -n1 || true)"
        [[ -n "$dep" ]] && v="${dep#php}" && v="${v%-cli}"
    else
        v="$(dnf -q repoquery --qf '%{version}\n' php-cli 2>/dev/null | sort -V | tail -n1 | cut -d. -f1,2)"
    fi
    printf '%s' "$v"
}

step "Installing PHP $MIN_PHP or newer"
PHP_VER="$(candidate_php_version)"
if [[ -z "$PHP_VER" ]] || ! version_ge "$PHP_VER" "$MIN_PHP"; then
    info "This system's own PHP is ${PHP_VER:-not available}; adding a source for a newer PHP."
    if [[ $PKG == apt ]]; then
        if [[ "$OS_ID" == ubuntu ]]; then
            pkg_install software-properties-common
            add-apt-repository -y ppa:ondrej/php
        else
            # Debian: packages.sury.org, the same maintainer's repository.
            curl -fsSL https://packages.sury.org/php/apt.gpg -o /usr/share/keyrings/sury-php.gpg
            echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ ${OS_CODENAME:-$(lsb_release -sc)} main" \
                > /etc/apt/sources.list.d/sury-php.list
        fi
        apt-get update -q
        PHP_VER="8.3"
    else
        # RHEL-family 9 offers newer PHP as a module stream.
        if dnf -q module list php >/dev/null 2>&1; then
            dnf -y -q module reset php
            dnf -y -q module enable php:8.2 || dnf -y -q module enable php:8.3
        fi
        PHP_VER="$(candidate_php_version)"
    fi
fi
[[ -n "$PHP_VER" ]] || die "Couldn't find a PHP $MIN_PHP+ package for this system."
version_ge "$PHP_VER" "$MIN_PHP" || die "Only PHP $PHP_VER is available here; this software needs PHP $MIN_PHP or newer."
info "Using PHP $PHP_VER"

if [[ $PKG == apt ]]; then
    # With the ondrej/sury source, ask for the exact version so the newest
    # one doesn't get mixed in; otherwise use the system's default packages.
    if pkg_available "php${PHP_VER}-cli"; then P="php${PHP_VER}"; else P="php"; fi
    pkg_install "$P-cli" "$P-fpm" "$P-intl" "$P-mbstring" "$P-sqlite3" "$P-xml" "$P-gd" "$P-curl" "$P-zip"
else
    pkg_install php-cli php-fpm php-intl php-mbstring php-pdo php-xml php-gd php-process
fi

PHP_BIN="$(command -v "php${PHP_VER}" 2>/dev/null || command -v php)"
ACTUAL_PHP="$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
version_ge "$ACTUAL_PHP" "$MIN_PHP" || die "PHP $ACTUAL_PHP was installed, but $MIN_PHP or newer is needed."
for ext in intl mbstring sqlite3 dom gd curl; do
    "$PHP_BIN" -m | grep -qi "^$ext\$" || die "The PHP '$ext' extension is missing."
done
info "PHP $ACTUAL_PHP with intl, mbstring, sqlite3, dom, gd and curl"

# PHP-FPM: the service that runs PHP for the web server.
if systemctl list-unit-files "php${ACTUAL_PHP}-fpm.service" >/dev/null 2>&1 \
    && systemctl list-unit-files "php${ACTUAL_PHP}-fpm.service" | grep -q fpm; then
    FPM_SERVICE="php${ACTUAL_PHP}-fpm"
else
    FPM_SERVICE="php-fpm"
fi
systemctl enable --now "$FPM_SERVICE" >/dev/null
info "PHP-FPM service: $FPM_SERVICE (running)"

# The PHP-FPM socket and the user PHP runs as (it must own writable/).
FPM_SOCKET=""
for s in "/run/php/php${ACTUAL_PHP}-fpm.sock" /run/php-fpm/www.sock /var/run/php-fpm/www.sock; do
    [[ -S "$s" ]] && FPM_SOCKET="$s" && break
done
[[ -n "$FPM_SOCKET" ]] || FPM_SOCKET="$(find /run /var/run -name '*.sock' -path '*php*' 2>/dev/null | head -n1 || true)"
[[ -n "$FPM_SOCKET" ]] || die "Couldn't find the PHP-FPM socket."
FPM_POOL=""
for f in "/etc/php/$ACTUAL_PHP/fpm/pool.d/www.conf" /etc/php-fpm.d/www.conf; do
    [[ -f "$f" ]] && FPM_POOL="$f" && break
done
PHP_USER="$(awk -F= '/^\s*user\s*=/ {gsub(/[ \t]/, "", $2); print $2; exit}' "${FPM_POOL:-/dev/null}" 2>/dev/null || true)"
[[ -n "$PHP_USER" ]] || PHP_USER="$([[ $PKG == apt ]] && echo www-data || echo apache)"
info "PHP-FPM socket: $FPM_SOCKET (runs as user $PHP_USER)"

# ---------------------------------------------------------------------------
# Composer (PHP package manager), checked against its published signature
# ---------------------------------------------------------------------------

step "Installing Composer"
if command -v composer >/dev/null 2>&1; then
    info "Composer is already installed: $(composer --version 2>/dev/null | head -n1)"
else
    tmp="$(mktemp -d)"
    expected="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o "$tmp/composer-setup.php"
    actual="$("$PHP_BIN" -r "echo hash_file('sha384', '$tmp/composer-setup.php');")"
    [[ "$expected" == "$actual" ]] || { rm -rf "$tmp"; die "The Composer installer's signature didn't match. Try again later."; }
    "$PHP_BIN" "$tmp/composer-setup.php" --quiet --install-dir=/usr/local/bin --filename=composer
    rm -rf "$tmp"
    info "Installed $(composer --version 2>/dev/null | head -n1)"
fi

# ---------------------------------------------------------------------------
# Web server: use nginx or Apache if one is already installed, else nginx
# ---------------------------------------------------------------------------

step "Checking for a web server"
if command -v nginx >/dev/null 2>&1; then
    WEB="nginx"; WEB_SERVICE="nginx"
    info "nginx is installed; using it."
elif command -v apache2 >/dev/null 2>&1 || [[ -x /usr/sbin/apache2 ]]; then
    WEB="apache"; WEB_SERVICE="apache2"
    info "Apache is installed; using it."
elif command -v httpd >/dev/null 2>&1 || [[ -x /usr/sbin/httpd ]]; then
    WEB="apache"; WEB_SERVICE="httpd"
    info "Apache is installed; using it."
else
    info "No web server found; installing nginx."
    pkg_install nginx
    WEB="nginx"; WEB_SERVICE="nginx"
fi

# On RHEL-family systems PHP-FPM runs as "apache" by default; let nginx use
# its socket.
if [[ $WEB == nginx && $PKG == dnf && -n "$FPM_POOL" ]] && grep -q '^listen.acl_users' "$FPM_POOL"; then
    grep -q '^listen.acl_users.*nginx' "$FPM_POOL" || sed -i 's/^listen.acl_users\s*=\s*\(.*\)$/listen.acl_users = \1,nginx/' "$FPM_POOL"
    systemctl restart "$FPM_SERVICE"
fi

# ---------------------------------------------------------------------------
# Download (or update) the software
# ---------------------------------------------------------------------------

FIRST_INSTALL=1
step "Downloading AG Golf League Management into $APP_DIR"
if [[ -d "$APP_DIR/.git" ]]; then
    FIRST_INSTALL=0
    info "Already installed; updating to the newest version of '$BRANCH'."
    git -C "$APP_DIR" fetch --quiet origin "$BRANCH"
    git -C "$APP_DIR" checkout --quiet "$BRANCH"
    git -C "$APP_DIR" reset --quiet --hard "origin/$BRANCH"
elif [[ -e "$APP_DIR" && -n "$(ls -A "$APP_DIR" 2>/dev/null)" ]]; then
    die "$APP_DIR exists and isn't empty (and isn't an earlier install). Choose another folder with --dir."
else
    mkdir -p "$(dirname "$APP_DIR")"
    git clone --quiet --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
fi
info "Version: $(git -C "$APP_DIR" log -1 --format='%h %s')"

step "Installing the software's PHP packages"
cd "$APP_DIR"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --quiet
info "Done."

# ---------------------------------------------------------------------------
# Settings (.env) - created once, then kept on updates
# ---------------------------------------------------------------------------

step "Settings"
if [[ -z "$DOMAIN" ]]; then
    HOST="$(hostname -I 2>/dev/null | awk '{print $1}')"
    HOST="${HOST:-localhost}"
else
    HOST="$DOMAIN"
fi
SCHEME="$([[ -n "$HTTPS_EMAIL" ]] && echo https || echo http)"
BASE_URL="$SCHEME://$HOST/"

if [[ -z "$TIMEZONE" ]]; then
    TIMEZONE="$(timedatectl show -p Timezone --value 2>/dev/null || true)"
    [[ -n "$TIMEZONE" ]] || TIMEZONE="$(cat /etc/timezone 2>/dev/null || echo UTC)"
fi
"$PHP_BIN" -r 'exit(in_array($argv[1], DateTimeZone::listIdentifiers(), true) ? 0 : 1);' "$TIMEZONE" \
    || die "'$TIMEZONE' isn't a timezone name PHP knows, e.g. America/Denver."

if [[ ! -f .env ]]; then
    cat > .env <<EOF
#--------------------------------------------------------------------
# AG Golf League Management settings for this server.
# Created by install.sh. Keep this file private and keep a copy:
# the encryption key below unlocks the saved email server password.
#--------------------------------------------------------------------

CI_ENVIRONMENT = production

app.baseURL = '$BASE_URL'
app.appTimezone = '$TIMEZONE'

encryption.key = hex2bin:$(openssl rand -hex 32)
EOF
    info "Created .env (site address $BASE_URL, timezone $TIMEZONE)."
else
    # Keep everything, but follow a new --domain / --https.
    if [[ -n "$DOMAIN" ]]; then
        sed -i "s#^app.baseURL = .*#app.baseURL = '$BASE_URL'#" .env
        info "Kept .env; site address set to $BASE_URL."
    else
        info "Kept the existing .env."
        BASE_URL="$(sed -n "s/^app.baseURL = '\(.*\)'/\1/p" .env)"
    fi
fi
chmod 640 .env

# Files belong to root; PHP may only write in writable/ (database, logs,
# sessions, cache) and read .env.
chown -R root:root "$APP_DIR"
chgrp "$PHP_USER" .env
chown -R "$PHP_USER:$PHP_USER" writable
find writable -type d -exec chmod 750 {} +

# SELinux (RHEL family): allow the web server to write in writable/ and to
# connect to an email server.
if command -v getenforce >/dev/null 2>&1 && [[ "$(getenforce)" != Disabled ]]; then
    info "Setting SELinux labels."
    pkg_install policycoreutils-python-utils >/dev/null 2>&1 || true
    semanage fcontext -a -t httpd_sys_rw_content_t "$APP_DIR/writable(/.*)?" 2>/dev/null \
        || semanage fcontext -m -t httpd_sys_rw_content_t "$APP_DIR/writable(/.*)?"
    restorecon -R "$APP_DIR" >/dev/null
    setsebool -P httpd_can_network_connect 1
fi

# ---------------------------------------------------------------------------
# Database
# ---------------------------------------------------------------------------

step "Setting up the database"
DB_FILE="$APP_DIR/writable/golfleague.db"
NEW_DB=0
[[ -f "$DB_FILE" ]] || NEW_DB=1
if [[ $NEW_DB -eq 0 ]]; then
    backup="$APP_DIR/writable/golfleague-backup-$(date +%Y%m%d-%H%M%S).db"
    cp -p "$DB_FILE" "$backup"
    info "Backed up the database to $backup"
fi
run_as_php() { runuser -u "$PHP_USER" -- "$@"; }
run_as_php "$PHP_BIN" spark migrate >/dev/null
info "Database is up to date."

if [[ $NEW_DB -eq 1 ]]; then
    step "Creating the first admin account ($ADMIN_LOGIN)"
    if [[ -z "${ADMIN_PASSWORD:-}" ]]; then
        [[ $ASSUME_YES -eq 0 && -r /dev/tty ]] || die "Set ADMIN_PASSWORD in the environment for an unattended install."
        while :; do
            read -rsp "    Choose a password for $ADMIN_LOGIN (8+ characters): " ADMIN_PASSWORD </dev/tty; echo
            read -rsp "    Type it again: " again </dev/tty; echo
            [[ "$ADMIN_PASSWORD" == "$again" && ${#ADMIN_PASSWORD} -ge 8 ]] && break
            warn "The passwords didn't match or were shorter than 8 characters. Try again."
        done
    fi
    ADMIN_LOGIN="$ADMIN_LOGIN" ADMIN_PASSWORD="$ADMIN_PASSWORD" \
        runuser -u "$PHP_USER" -- "$PHP_BIN" spark db:seed AdminSeeder >/dev/null
    unset ADMIN_PASSWORD again
    info "Admin account created."
fi

# ---------------------------------------------------------------------------
# Web server site
# ---------------------------------------------------------------------------

step "Setting up the $WEB site"
SERVER_NAME="${DOMAIN:-_}"
if [[ $WEB == nginx ]]; then
    if [[ -d /etc/nginx/sites-available ]]; then
        NGINX_CONF="/etc/nginx/sites-available/$SITE_NAME"
    else
        NGINX_CONF="/etc/nginx/conf.d/$SITE_NAME.conf"
    fi
    LISTEN="listen 80;"
    # Without a domain this site is the default for the machine's address.
    [[ -z "$DOMAIN" ]] && LISTEN="listen 80 default_server;"
    cat > "$NGINX_CONF" <<EOF
# AG Golf League Management (written by install.sh)
server {
    $LISTEN
    server_name $SERVER_NAME;
    root $APP_DIR/public;
    index index.php;
    client_max_body_size 10m;

    location / {
        try_files \$uri \$uri/ /index.php\$is_args\$args;
    }

    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass unix:$FPM_SOCKET;
        # Emailing tee sheets to a big league can take a while.
        fastcgi_read_timeout 300;
    }

    location ~ /\. {
        deny all;
    }
}
EOF
    if [[ -d /etc/nginx/sites-enabled ]]; then
        ln -sf "$NGINX_CONF" "/etc/nginx/sites-enabled/$SITE_NAME"
        if [[ -z "$DOMAIN" && -L /etc/nginx/sites-enabled/default ]]; then
            rm /etc/nginx/sites-enabled/default
            info "Turned off nginx's default welcome page."
        fi
    elif [[ -z "$DOMAIN" ]] && grep -q 'default_server' /etc/nginx/nginx.conf; then
        # Fedora/RHEL: the stock server block claims default_server.
        sed -i 's/\(listen\s\+\(\[::\]:\)\?80\)\s\+default_server/\1/' /etc/nginx/nginx.conf
        info "Turned off nginx's default welcome page."
    fi
    nginx -t -q || die "The nginx settings check failed (see above)."
else
    if [[ $WEB_SERVICE == apache2 ]]; then
        APACHE_CONF="/etc/apache2/sites-available/$SITE_NAME.conf"
        a2enmod -q proxy_fcgi setenvif rewrite >/dev/null
    else
        APACHE_CONF="/etc/httpd/conf.d/$SITE_NAME.conf"
    fi
    SERVER_NAME_LINE=""
    [[ -n "$DOMAIN" ]] && SERVER_NAME_LINE="ServerName $DOMAIN"
    cat > "$APACHE_CONF" <<EOF
# AG Golf League Management (written by install.sh)
<VirtualHost *:80>
    $SERVER_NAME_LINE
    DocumentRoot $APP_DIR/public

    <Directory $APP_DIR/public>
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch "\.php\$">
        SetHandler "proxy:unix:$FPM_SOCKET|fcgi://localhost"
    </FilesMatch>

    # Emailing tee sheets to a big league can take a while.
    ProxyTimeout 300
</VirtualHost>
EOF
    if [[ $WEB_SERVICE == apache2 ]]; then
        a2ensite -q "$SITE_NAME" >/dev/null
        if [[ -z "$DOMAIN" ]] && [[ -L /etc/apache2/sites-enabled/000-default.conf ]]; then
            a2dissite -q 000-default >/dev/null
            info "Turned off Apache's default welcome page."
        fi
        apache2ctl -t 2>/dev/null || die "The Apache settings check failed (run: apache2ctl -t)."
    else
        httpd -t 2>/dev/null || die "The Apache settings check failed (run: httpd -t)."
    fi
fi
systemctl enable "$WEB_SERVICE" >/dev/null 2>&1
systemctl restart "$FPM_SERVICE" "$WEB_SERVICE"
info "$WEB_SERVICE is running."

# Open the firewall for web traffic if one is on.
if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q 'Status: active'; then
    ufw allow 80/tcp >/dev/null
    [[ -n "$HTTPS_EMAIL" ]] && ufw allow 443/tcp >/dev/null
    info "Opened the firewall (ufw) for web traffic."
elif command -v firewall-cmd >/dev/null 2>&1 && firewall-cmd --state >/dev/null 2>&1; then
    firewall-cmd -q --permanent --add-service=http
    [[ -n "$HTTPS_EMAIL" ]] && firewall-cmd -q --permanent --add-service=https
    firewall-cmd -q --reload
    info "Opened the firewall (firewalld) for web traffic."
fi

# ---------------------------------------------------------------------------
# HTTPS (optional)
# ---------------------------------------------------------------------------

if [[ -n "$HTTPS_EMAIL" ]]; then
    step "Getting an HTTPS certificate for $DOMAIN"
    if [[ $WEB == nginx ]]; then
        pkg_install certbot python3-certbot-nginx
        certbot --nginx -n --agree-tos -m "$HTTPS_EMAIL" -d "$DOMAIN" --redirect
    else
        if [[ $PKG == apt ]]; then pkg_install certbot python3-certbot-apache; else pkg_install certbot python3-certbot-apache mod_ssl; fi
        certbot --apache -n --agree-tos -m "$HTTPS_EMAIL" -d "$DOMAIN" --redirect
    fi
    info "HTTPS is on; the certificate renews automatically."
fi

# ---------------------------------------------------------------------------
# Check it works
# ---------------------------------------------------------------------------

step "Checking the site"
code="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: ${DOMAIN:-$HOST}" http://127.0.0.1/login || true)"
if [[ "$code" == 200 || ( -n "$HTTPS_EMAIL" && "$code" == 301 ) ]]; then
    info "The login page answers (HTTP $code)."
else
    warn "The login page answered HTTP $code. Check $APP_DIR/writable/logs/ and the $WEB_SERVICE error log."
fi

cat <<EOF

AG Golf League Management is installed.

  Site:          $BASE_URL
  Admin login:   $([[ $NEW_DB -eq 1 ]] && echo "$ADMIN_LOGIN (with the password you chose)" || echo "your existing admin accounts")
  Installed in:  $APP_DIR
  Database:      $DB_FILE
  Settings:      $APP_DIR/.env (keep a private copy; it holds the encryption key)

Next steps:
  - Log in, then open Settings to enter the email server details for sending
    tee sheets, and send yourself a test email.
  - Back up $DB_FILE and $APP_DIR/.env regularly.
  - To update to the newest version later, run this script again.
EOF
[[ -z "$HTTPS_EMAIL" ]] && cat <<EOF
  - The site uses plain http, so passwords cross the network unencrypted.
    For a site on the internet, point a domain name at this machine and run:
      sudo bash install.sh --domain your.domain --https you@example.com
EOF
exit 0
