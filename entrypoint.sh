#!/bin/bash
set -eu

# Force mpm_prefork (required for mod_php in php:8.2-apache).
find /etc/apache2/mods-enabled -name 'mpm_*' -delete
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

# Sync script/ from image into the Railway volume.
#   - Code/asset subdirs: always overwrite so repo updates propagate.
#   - User-data subdirs (config, uploads, apps): only seed if missing.
echo "[entrypoint] Syncing script/ from image (preserving: config, uploads, apps)..."
shopt -s dotglob
for src in /var/www/html/script_seed/*; do
    name=$(basename "$src")
    dest="/var/www/html/script/$name"
    case "$name" in
        config|uploads|apps)
            if [ ! -e "$dest" ]; then
                echo "[entrypoint]   seeding missing /script/$name"
                cp -r "$src" "$dest"
            fi
            ;;
        *)
            cp -rf "$src" "/var/www/html/script/"
            ;;
    esac
done
shopt -u dotglob
chown -R www-data:www-data /var/www/html/script

# DB schema setup. Non-fatal: if it fails (e.g. board.support unreachable,
# DB env vars missing) we still want Apache up so the user can reach
# whatever pages don't need the DB and can read the error.
echo "[entrypoint] Running DB setup..."
php /var/www/html/setup.php || echo "[entrypoint] setup.php exited non-zero -- continuing anyway"

echo "[entrypoint] Starting Apache..."
exec apache2-foreground
