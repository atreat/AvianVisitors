#!/usr/bin/env bash
source /etc/birdnet/birdnet.conf
my_dir=$HOME/BirdNET-Pi/scripts
set -x

# Find the active PHP-FPM Unix socket. The path is version-specific on
# modern Raspberry Pi OS (e.g. /run/php/php8.2-fpm.sock); the generic
# /run/php/php-fpm.sock only exists if a compat shim is installed, so
# hardcoding it breaks Caddy's php_fastcgi handler on stock Bookworm.
FPM_SOCK=$(ls /run/php/php*-fpm.sock 2>/dev/null | head -n1)
FPM_SOCK=${FPM_SOCK:-/run/php/php-fpm.sock}

[ -d /etc/caddy ] || mkdir /etc/caddy
if [ -f /etc/caddy/Caddyfile ];then
  cp /etc/caddy/Caddyfile{,.original}
fi
if [ -n "${CADDY_PWD:-}" ]; then
  HASHWORD=$(caddy hash-password --plaintext "${CADDY_PWD}")
  # Caddy 2.8 renamed `basicauth` to `basic_auth`. The new spelling is
  # accepted by current Caddy releases and avoids relying on the deprecated
  # alias. All mutating/admin endpoints share this single password.
  AUTH_BLOCK=$(printf 'basic_auth @protected {\n    birdnet %s\n  }' "${HASHWORD}")
else
  # A blank password must never silently leave management endpoints public.
  # The collage remains viewable, but its drawer/admin and private media are
  # unavailable until an administrator sets CADDY_PWD.
  AUTH_BLOCK='respond @protected 403'
fi

cat << EOF > /etc/caddy/Caddyfile
http:// ${BIRDNETPI_URL} {
  root * ${EXTRACTED}

  # Keep the public experience to the static collage and read-only bird data.
  # The upstream BirdNET-Pi control surface includes a terminal, a file
  # manager, and database tooling; none are required by AvianVisitors.
  @disabled path /index.php /views.php /config.php /play.php /spectrogram.php /overview.php /stats.php /todays_detections.php /history.php /weekly_report.php /scripts/* /terminal* /log* /stats* /phpsysinfo*
  respond @disabled 404

  # Password-protected routes. These include every endpoint that changes Pi
  # configuration or services, plus direct media directories and the stream.
  @protected path /avian/api/menu.php /avian/api/config.php /avian/api/birdnet-status.php /stream /By_Date/* /Charts/* /Processed* /Raw/*
  ${AUTH_BLOCK}

  header {
    X-Content-Type-Options "nosniff"
    X-Frame-Options "DENY"
    Referrer-Policy "same-origin"
    Permissions-Policy "camera=(), geolocation=(), microphone=()"
  }

  # The HTML shell must always revalidate so UI deploys and re-rendered
  # illustrations show up on the next load; versioned assets (?v=) keep
  # caching normally.
  @shell path / /index.html
  header @shell Cache-Control "no-cache"

  # No directory listing: recordings are only served through the protected
  # API above, never exposed as a browsable filesystem.
  file_server

  # AvianVisitors overlay drops an index.html alongside BirdNET-Pi's
  # index.php. Do not fall back to the legacy PHP homepage for unknown paths.
  php_fastcgi unix/${FPM_SOCK} {
    try_files {path} {path}/index.html =404
  }
}
EOF

sudo caddy fmt --overwrite /etc/caddy/Caddyfile
# Fail loudly on a Caddyfile caddy can't parse rather than reloading a broken
# config and reporting success.
sudo caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile || {
  echo "generated Caddyfile failed validation; not reloading caddy" >&2
  exit 1
}
# reload-or-restart so this also works at install time, when caddy may not be
# running yet (a plain reload would fail there); tolerate a not-yet-ready unit.
sudo systemctl reload-or-restart caddy 2>/dev/null || true
