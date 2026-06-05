#!/bin/sh
# Ensure /data is writable by www-data regardless of host volume mount ownership
chown -R www-data:www-data /data 2>/dev/null || true
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf