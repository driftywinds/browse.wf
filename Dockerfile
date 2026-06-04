# browse.wf self-hosted Dockerfile
# Multi-architecture: linux/amd64, linux/arm64
# Uses Apache + mod_php with URL rewriting for clean URLs (no .php extension)

FROM php:8.3-apache

# Enable Apache mod_rewrite for clean URLs
RUN a2enmod rewrite

# ── Fetch warframe-public-export-plus JSON data via npm ──────────────────────
# Install Node.js, pull just the one package we need by name (not npm ci),
# copy its data directory into the web root as a plain real directory, then
# remove Node.js and node_modules — neither is needed at runtime.
#
# Why `npm install <pkg>` and not `npm ci --omit=dev`?
# warframe-public-export-plus is a devDependency in package.json so --omit=dev
# skips it entirely. Installing by name fetches it regardless of category.
#
# Why copy instead of symlink?
# Apache requires FollowSymLinks for mod_rewrite to work, but also blocks
# symlinks that resolve outside the docroot unless explicitly permitted in a
# matching <Directory> block. Copying avoids all of that cleanly.
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy source files
COPY . /var/www/html/

WORKDIR /var/www/html

RUN npm install warframe-public-export-plus \
    && cp -r node_modules/warframe-public-export-plus /var/www/html/warframe-public-export-plus \
    && rm -rf node_modules \
    && apt-get purge -y nodejs \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

# ── Patch hardcoded origin ────────────────────────────────────────────────────
# The compiled JS fetches from https://browse.wf/warframe-public-export-plus/...
# Strip the origin so all fetches become relative (/warframe-public-export-plus/...).
# oracle.browse.wf is left as-is — it's the live Warframe world-state service.
RUN sed -i 's|https://browse\.wf||g' \
        /var/www/html/common.js \
        /var/www/html/typestripped/index.js \
        /var/www/html/typestripped/live.js \
        /var/www/html/typestripped/arbys.js \
        /var/www/html/typestripped/profile.js \
        /var/www/html/typestripped/prime-vault.js

# ── Apache virtual-host ───────────────────────────────────────────────────────
# FollowSymLinks is required by Apache whenever mod_rewrite is active — it's a
# hard security requirement in Apache itself (see AH00670). There are no actual
# symlinks in the image (we copied warframe-public-export-plus as a real dir)
# so enabling it here carries no real risk.
RUN cat > /etc/apache2/sites-available/000-default.conf <<'EOF'
ServerName localhost

<VirtualHost *:80>
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options FollowSymLinks
        AllowOverride None
        Require all granted

        RewriteEngine On

        # Clean URLs: skip if the path is already a real file or directory
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_URI} !^/warframe-public-export-plus/
        RewriteCond %{REQUEST_URI} !^/supplemental-data/
        RewriteCond %{REQUEST_URI} !^/typestripped/
        RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI}.php -f
        RewriteRule ^(.+)$ $1.php [L]

        # Route unresolved /Lotus/* to 404.php (Warframe asset JSON handler)
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_URI} ^/Lotus/
        RewriteRule ^ /404.php [L]
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Fix file ownership so Apache can read everything
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80