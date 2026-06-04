# browse.wf self-hosted Dockerfile
# Multi-architecture: linux/amd64, linux/arm64
# Uses Apache + mod_php with URL rewriting for clean URLs (no .php extension)

FROM php:8.3-apache

# Install Node.js 20 for npm (needed to fetch warframe-public-export-plus data files)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite for clean URLs
RUN a2enmod rewrite

# Set document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html

# Copy all source files into the image
COPY . /var/www/html/

# Run npm ci inside the app directory to download warframe-public-export-plus
# (the npm package contains all the Warframe JSON data files used by PHP and the frontend)
WORKDIR /var/www/html
RUN npm ci --omit=dev

# Create the symlink that the PHP files and web server expect:
#   /var/www/html/warframe-public-export-plus -> node_modules/warframe-public-export-plus
# (.gitignore lists this as a symlink that isn't committed to the repo)
RUN ln -sf /var/www/html/node_modules/warframe-public-export-plus \
           /var/www/html/warframe-public-export-plus

# Patch the hardcoded "https://browse.wf" origin out of the compiled JS and common.js
# so all warframe-public-export-plus fetches use a relative path ("/warframe-public-export-plus/...")
# and all image lookups fall back to the local /Lotus/* 404-handler correctly.
# oracle.browse.wf is intentionally left pointing at the live Warframe world-state service
# since that requires a separate backend to replicate.
RUN sed -i 's|https://browse\.wf||g' \
        /var/www/html/common.js \
        /var/www/html/typestripped/index.js \
        /var/www/html/typestripped/live.js \
        /var/www/html/typestripped/arbys.js \
        /var/www/html/typestripped/profile.js \
        /var/www/html/typestripped/prime-vault.js

# Write Apache virtual-host config:
#   1. Serves PHP files for clean URLs (/live -> live.php, etc.)
#   2. Routes unresolved /Lotus/* paths to 404.php (the Warframe asset lookup handler)
#   3. Allows symlinks (FollowSymLinks) so warframe-public-export-plus symlink is served
RUN cat > /etc/apache2/sites-available/000-default.conf <<'EOF'
<VirtualHost *:80>
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options FollowSymLinks
        AllowOverride None
        Require all granted

        # Clean URL rewriting: /live -> /live.php, etc.
        # Skips rewriting if the request matches a real file or directory.
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_URI} !^/warframe-public-export-plus/
        RewriteCond %{REQUEST_URI} !^/supplemental-data/
        RewriteCond %{REQUEST_URI} !^/typestripped/
        RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI}.php -f
        RewriteRule ^(.+)$ $1.php [L]

        # Route unresolved /Lotus/* paths to 404.php (Warframe asset JSON handler)
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_URI} ^/Lotus/
        RewriteRule ^ /404.php [L]
    </Directory>

    # Serve the warframe-public-export-plus JSON data directly (via symlink)
    <Directory /var/www/html/node_modules/warframe-public-export-plus>
        Options FollowSymLinks
        AllowOverride None
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Fix file ownership so Apache can read everything
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
