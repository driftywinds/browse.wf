# browse.wf self-hosted Dockerfile
# Multi-architecture: linux/amd64, linux/arm64
# Uses Apache + mod_php with URL rewriting for clean URLs (no .php extension)

FROM php:8.3-apache

# Enable Apache mod_rewrite for clean URLs
RUN a2enmod rewrite

# ── System deps ───────────────────────────────────────────────────────────────
# nodejs   — used at build time only to pull warframe-public-export-plus
# supervisor — runs Apache + notifyd daemon side-by-side at runtime
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs supervisor \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable PHP extensions needed at runtime
# libcurl4-openssl-dev is required before docker-php-ext-install curl can compile
RUN apt-get update \
    && apt-get install -y libcurl4-openssl-dev libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install curl pdo pdo_sqlite

# Copy source files
COPY . /var/www/html/

# Overwrite 404.php with our pre-patched version that adds an image proxy handler.
COPY 404.php /var/www/html/404.php

WORKDIR /var/www/html

RUN npm install warframe-public-export-plus \
    && cp -r node_modules/warframe-public-export-plus /var/www/html/warframe-public-export-plus \
    && rm -rf node_modules \
    && apt-get purge -y nodejs \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

# ── Patch hardcoded origin ────────────────────────────────────────────────────
RUN sed -i 's|https://browse\.wf||g' \
        /var/www/html/common.js \
        /var/www/html/typestripped/index.js \
        /var/www/html/typestripped/live.js \
        /var/www/html/typestripped/arbys.js \
        /var/www/html/typestripped/profile.js \
        /var/www/html/typestripped/prime-vault.js

# ── Apache virtual-host ───────────────────────────────────────────────────────
RUN cat > /etc/apache2/sites-available/000-default.conf <<'EOF'
ServerName localhost

<VirtualHost *:80>
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options FollowSymLinks
        AllowOverride None
        Require all granted

        RewriteEngine On

        # Protect notif/ internals — only allow the explicit API routes above
        RewriteRule ^notif/db\.php$ - [F,L]

        # Route /notif/* API calls to their PHP files
        RewriteRule ^notif/(save|test|status)$ notif/$1.php [L]

        # Clean URLs: skip if the path is already a real file or directory
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_URI} !^/warframe-public-export-plus/
        RewriteCond %{REQUEST_URI} !^/supplemental-data/
        RewriteCond %{REQUEST_URI} !^/typestripped/
        RewriteCond %{REQUEST_URI} !^/notif/
        RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI}.php -f
        RewriteRule ^(.+)$ $1.php [L]

        # Route unresolved /Lotus/* to 404.php (Warframe asset JSON + image handler)
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_URI} ^/Lotus/
        RewriteRule ^ /404.php [L]
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# ── supervisord config ────────────────────────────────────────────────────────
# Runs Apache and the notification daemon together in one container.
# notifyd logs go to stdout so `docker logs` shows them.
RUN cat > /etc/supervisor/conf.d/browse-wf.conf <<'EOF'
[supervisord]
nodaemon=true
logfile=/dev/null
logfile_maxbytes=0

[program:apache2]
command=/usr/sbin/apache2ctl -D FOREGROUND
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:notifyd]
command=php /var/www/html/notifyd.php
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
; Give Apache a couple seconds to start before the daemon tries localhost requests
startsecs=3
EOF

# Ensure /data dir exists (will be overridden by a volume mount in production)
RUN mkdir -p /data && chown www-data:www-data /data

# Fix file ownership so Apache + notifyd can read everything
RUN chown -R www-data:www-data /var/www/html

# Entrypoint fixes /data ownership at runtime (host volume mounts can reset it)
RUN chmod +x /var/www/html/docker-entrypoint.sh

EXPOSE 80

CMD ["/var/www/html/docker-entrypoint.sh"]